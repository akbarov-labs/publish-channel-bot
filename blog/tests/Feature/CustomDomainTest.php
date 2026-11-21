<?php

namespace Tests\Feature;

use App\Filament\Pages\EditProfile;
use App\Models\User;
use App\Support\CustomDomain;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class CustomDomainTest extends TestCase
{
    use RefreshDatabase;

    private const SERVER = '169.58.243.86';

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        config([
            'app.frontend_url' => 'https://posts.test',
            'services.custom_domains.enabled' => true,
            'services.custom_domains.ip' => self::SERVER,
            'services.tls_ask.peer_url' => '',
        ]);
        // posts.test is this server; blog.aziza.uz points here, elsewhere.uz does not.
        CustomDomain::resolveUsing(fn (string $host): array => match ($host) {
            'posts.test', 'blog.aziza.uz', 'aziza.uz' => [self::SERVER],
            'elsewhere.uz' => ['203.0.113.9'],
            default => [],
        });
    }

    protected function tearDown(): void
    {
        CustomDomain::resolveUsing(null);
        parent::tearDown();
    }

    private function author(bool $premium = true, array $attributes = []): User
    {
        return User::factory()->create($attributes + [
            'username' => 'aziza',
            'premium_until' => $premium ? now()->addDays(20) : null,
        ]);
    }

    private function live(array $attributes = []): User
    {
        $author = $this->author(attributes: $attributes);
        $author->forceFill(['custom_domain' => 'blog.aziza.uz'])->save();
        $author->forceFill(['custom_domain_verified_at' => now()])->save();

        return $author;
    }

    /** The Check DNS button, in the domain section's footer. */
    private function checkDns(): TestAction
    {
        return TestAction::make('checkDomain')->schemaComponent('domain', schema: 'form');
    }

    // --- What counts as a domain -------------------------------------------------

    public function test_a_pasted_address_becomes_a_bare_host(): void
    {
        $this->assertSame('blog.aziza.uz', CustomDomain::normalize(' https://Blog.Aziza.UZ:443/about?x=1 '));
        $this->assertSame('aziza.uz', CustomDomain::normalize('aziza.uz.'));
        $this->assertNull(CustomDomain::normalize('  '));
    }

    public function test_the_platforms_own_domain_and_non_domains_are_refused(): void
    {
        $this->assertNull(CustomDomain::problem('blog.aziza.uz'));
        $this->assertNotNull(CustomDomain::problem('posts.test'));
        $this->assertNotNull(CustomDomain::problem('vali.posts.test'));
        $this->assertNotNull(CustomDomain::problem('localhost'));
        $this->assertNotNull(CustomDomain::problem('blog_aziza.uz'));
        $this->assertNotNull(CustomDomain::problem('-x.uz'));
    }

    public function test_a_domain_points_here_through_a_cname_or_the_servers_a_record(): void
    {
        $this->assertTrue(CustomDomain::pointsHere('blog.aziza.uz'));
        $this->assertFalse(CustomDomain::pointsHere('elsewhere.uz'));
        $this->assertFalse(CustomDomain::pointsHere('nowhere.uz'));
    }

    // --- The profile -------------------------------------------------------------

    public function test_a_premium_author_saves_a_domain_and_checks_its_dns(): void
    {
        $author = $this->author();
        $this->actingAs($author);

        Livewire::test(EditProfile::class)
            ->fillForm(['custom_domain' => 'https://Blog.Aziza.uz/'])
            ->call('save')
            ->assertHasNoFormErrors();

        $author->refresh();
        $this->assertSame('blog.aziza.uz', $author->custom_domain);
        $this->assertNull($author->custom_domain_verified_at);
        $this->assertNull($author->activeCustomDomain());

        Livewire::test(EditProfile::class)->callAction($this->checkDns())->assertNotified('blog.aziza.uz is live');

        $author->refresh();
        $this->assertNotNull($author->custom_domain_verified_at);
        $this->assertSame('blog.aziza.uz', $author->activeCustomDomain());
        $this->assertSame('https://blog.aziza.uz', $author->siteUrl());
    }

    public function test_a_domain_that_does_not_point_here_is_not_verified(): void
    {
        $author = $this->author();
        $author->forceFill(['custom_domain' => 'elsewhere.uz'])->save();
        $this->actingAs($author);

        Livewire::test(EditProfile::class)->callAction($this->checkDns())->assertNotified('elsewhere.uz does not point here yet');

        $this->assertNull($author->fresh()->custom_domain_verified_at);
    }

    public function test_changing_the_domain_needs_a_new_check(): void
    {
        $author = $this->live();

        $author->update(['custom_domain' => 'aziza.uz']);

        $this->assertNull($author->fresh()->custom_domain_verified_at);
        $this->assertSame('https://aziza.posts.test', $author->fresh()->siteUrl());
    }

    public function test_the_profile_refuses_bad_taken_and_platform_domains(): void
    {
        User::factory()->create(['username' => 'vali'])->forceFill(['custom_domain' => 'aziza.uz'])->save();
        $this->actingAs($this->author());

        foreach (['not a domain', 'vali.posts.test', 'AZIZA.uz'] as $domain) {
            Livewire::test(EditProfile::class)
                ->fillForm(['custom_domain' => $domain])
                ->call('save')
                ->assertHasFormErrors(['custom_domain']);
        }
    }

    public function test_without_premium_the_field_is_locked_and_the_domain_kept(): void
    {
        $author = $this->author(premium: false);
        $author->forceFill(['custom_domain' => 'blog.aziza.uz'])->save();
        $this->actingAs($author);

        Livewire::test(EditProfile::class)
            ->assertFormFieldDisabled('domain.custom_domain')
            ->fillForm(['custom_domain' => 'aziza.uz', 'bio' => 'Still here'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('blog.aziza.uz', $author->fresh()->custom_domain);
        $this->assertSame('Still here', $author->fresh()->bio);
        $this->get('/admin/profile')->assertSee('Premium has ended')->assertDontSee('Check DNS');
    }

    public function test_the_section_is_hidden_until_the_platform_serves_own_domains(): void
    {
        config(['services.custom_domains.enabled' => false]);
        $author = $this->live();

        $this->actingAs($author)->get('/admin/profile')->assertOk()->assertDontSee('Your own domain');
        $this->assertNull($author->activeCustomDomain());
        $this->assertSame('https://aziza.posts.test', $author->siteUrl());
    }

    // --- When it is live ---------------------------------------------------------

    public function test_only_a_verified_domain_with_premium_is_live(): void
    {
        $author = $this->live();
        $this->assertSame('blog.aziza.uz', $author->activeCustomDomain());

        $author->forceFill(['premium_until' => now()->subDays(User::PREMIUM_GRACE_DAYS + 1)])->save();
        $this->assertNull($author->fresh()->activeCustomDomain());

        $author->forceFill(['premium_until' => now()->addDay()])->save();
        $author->block();
        $this->assertNull($author->fresh()->activeCustomDomain());
    }

    public function test_post_links_and_the_reader_api_use_the_live_domain(): void
    {
        $author = $this->live();
        $author->posts()->create(['title' => 'Hello', 'slug' => 'hello', 'status' => 'published', 'published_at' => now()->subHour()]);

        $this->getJson('/api/public/authors/aziza')
            ->assertJsonPath('custom_domain', 'blog.aziza.uz')
            ->assertJsonPath('site_url', 'https://blog.aziza.uz');

        $this->getJson('/api/public/authors/aziza/posts/hello')
            ->assertJsonPath('url', 'https://blog.aziza.uz/posts/hello');
    }

    // --- Host lookup, for the reader site's proxy ---------------------------------

    public function test_the_proxy_learns_which_author_a_host_is_for(): void
    {
        $this->live();

        $this->getJson('/api/public/hosts/aziza.posts.test')->assertOk()
            ->assertExactJson(['username' => 'aziza', 'custom_domain' => 'blog.aziza.uz']);
        $this->getJson('/api/public/hosts/blog.aziza.uz')->assertOk()
            ->assertExactJson(['username' => 'aziza', 'custom_domain' => 'blog.aziza.uz']);

        $this->getJson('/api/public/hosts/nobody.posts.test')->assertNotFound();
        $this->getJson('/api/public/hosts/unknown.uz')->assertNotFound();
    }

    public function test_an_own_domain_whose_premium_ended_still_names_the_author_so_it_can_redirect(): void
    {
        $author = $this->live();
        $author->forceFill(['premium_until' => now()->subMonth()])->save();

        $this->getJson('/api/public/hosts/blog.aziza.uz')->assertOk()
            ->assertExactJson(['username' => 'aziza', 'custom_domain' => null]);
    }

    public function test_an_unverified_or_blocked_authors_domain_is_unknown(): void
    {
        $author = $this->author();
        $author->forceFill(['custom_domain' => 'blog.aziza.uz'])->save();

        $this->getJson('/api/public/hosts/blog.aziza.uz')->assertNotFound();

        $author->forceFill(['custom_domain_verified_at' => now()])->save();
        $author->block();

        $this->getJson('/api/public/hosts/blog.aziza.uz')->assertNotFound();
    }

    // --- Certificates ------------------------------------------------------------

    public function test_caddy_may_get_a_certificate_for_a_verified_domain_even_after_premium_ends(): void
    {
        $author = $this->live();

        $this->get('/api/tls/allowed?domain=blog.aziza.uz')->assertOk();

        // Still allowed, so the redirect to the subdomain works over https.
        $author->forceFill(['premium_until' => now()->subMonth()])->save();
        $this->get('/api/tls/allowed?domain=blog.aziza.uz')->assertOk();

        $author->block();
        $this->get('/api/tls/allowed?domain=blog.aziza.uz')->assertNotFound();
    }

    public function test_no_certificate_for_an_unverified_domain_or_with_own_domains_off(): void
    {
        $author = $this->author();
        $author->forceFill(['custom_domain' => 'blog.aziza.uz'])->save();

        $this->get('/api/tls/allowed?domain=blog.aziza.uz')->assertNotFound();

        $author->forceFill(['custom_domain_verified_at' => now()])->save();
        config(['services.custom_domains.enabled' => false]);

        $this->get('/api/tls/allowed?domain=blog.aziza.uz')->assertNotFound();
    }
}
