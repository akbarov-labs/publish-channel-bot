<?php

namespace Tests\Feature;

use App\Filament\Pages\EditProfile;
use App\Filament\Widgets\ViewsChart;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class PremiumFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.frontend_url' => 'https://example.test']);
        $this->freezeSecond();
    }

    private function author(bool $premium = false, array $attributes = []): User
    {
        return User::factory()->create($attributes + [
            'username' => 'aziza',
            'premium_until' => $premium ? now()->addDays(20) : null,
        ]);
    }

    private function views(Post $post, string $day, int $views): void
    {
        DB::table('post_views')->insert(['post_id' => $post->id, 'day' => $day, 'views' => $views]);
    }

    // --- The footer credit ------------------------------------------------------

    public function test_the_reader_api_tells_the_site_to_drop_the_credit_only_for_premium(): void
    {
        $author = $this->author();

        $this->getJson('/api/public/authors/aziza')->assertJsonPath('premium', false);

        $author->grantPremium(30);

        $this->getJson('/api/public/authors/aziza')->assertJsonPath('premium', true);
    }

    // --- Premium designs --------------------------------------------------------

    public function test_a_premium_design_shows_only_while_premium_is_on(): void
    {
        $author = $this->author(premium: true, attributes: ['template' => 'midnight']);

        $this->assertSame('midnight', $author->siteTemplate());
        $this->getJson('/api/public/authors/aziza')->assertJsonPath('template', 'midnight');

        // Premium ends: readers see Classic, the pick is kept.
        $author->forceFill(['premium_until' => now()->subDays(User::PREMIUM_GRACE_DAYS + 1)])->save();

        $this->assertSame('classic', $author->fresh()->siteTemplate());
        $this->getJson('/api/public/authors/aziza')->assertJsonPath('template', 'classic');
        $this->assertSame('midnight', $author->fresh()->template);
    }

    public function test_free_designs_never_need_premium_and_unknown_ones_are_classic(): void
    {
        $this->assertSame('notebook', $this->author(attributes: ['template' => 'notebook'])->siteTemplate());
        $this->assertSame('classic', User::factory()->make(['template' => 'neon'])->siteTemplate());
    }

    public function test_an_author_without_premium_cannot_pick_a_premium_design(): void
    {
        $author = $this->author();
        $this->actingAs($author);

        Livewire::test(EditProfile::class)
            ->fillForm(['template' => 'gazette'])
            ->call('save')
            ->assertHasFormErrors(['template']);

        $this->assertSame('classic', $author->fresh()->template);

        $this->get('/admin/profile')
            ->assertOk()
            ->assertSee('Gazette')
            ->assertSee('Midnight and Gazette come with Premium');
    }

    public function test_an_author_with_premium_picks_one(): void
    {
        $author = $this->author(premium: true);
        $this->actingAs($author);

        Livewire::test(EditProfile::class)
            ->fillForm(['template' => 'gazette'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('gazette', $author->fresh()->template);
        $this->getJson('/api/public/authors/aziza')->assertJsonPath('template', 'gazette');
        $this->get('/admin/profile')->assertDontSee('come with Premium');
    }

    public function test_a_profile_whose_premium_ended_can_still_be_saved(): void
    {
        $author = $this->author(attributes: ['template' => 'midnight', 'premium_until' => now()->subMonth()]);
        $this->actingAs($author);

        Livewire::test(EditProfile::class)
            ->fillForm(['bio' => 'New bio'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('New bio', $author->fresh()->bio);
        $this->assertSame('midnight', $author->fresh()->template);

        // The other Premium design is still out of reach.
        Livewire::test(EditProfile::class)
            ->fillForm(['template' => 'gazette'])
            ->call('save')
            ->assertHasFormErrors(['template']);
    }

    // --- A year of stats --------------------------------------------------------

    public function test_a_year_of_views_comes_with_premium(): void
    {
        $free = $this->author();
        Livewire::actingAs($free)->test(ViewsChart::class)
            ->assertDontSee('Last year')
            ->assertSee('come with Premium')
            ->assertDontSee('stats.csv');

        $paying = $this->author(premium: true, attributes: ['username' => 'vali']);
        Livewire::actingAs($paying)->test(ViewsChart::class)
            ->assertSee('Last year')
            ->assertSee('stats.csv');
    }

    public function test_the_chart_shows_a_whole_year_only_with_premium(): void
    {
        $paying = $this->author(premium: true);
        $chart = Livewire::actingAs($paying)->test(ViewsChart::class)->set('filter', '365');
        $this->assertCount(365, $this->chartLabels($chart->instance()));

        $free = $this->author(attributes: ['username' => 'vali']);
        $chart = Livewire::actingAs($free)->test(ViewsChart::class)->set('filter', '365');
        $this->assertCount(30, $this->chartLabels($chart->instance()));
    }

    /** getData() is protected; the labels are one per day shown. */
    private function chartLabels(ViewsChart $chart): array
    {
        return (fn () => $this->getData())->call($chart)['labels'];
    }

    // --- CSV --------------------------------------------------------------------

    public function test_the_csv_has_a_year_of_the_authors_own_views(): void
    {
        $author = $this->author(premium: true);
        $post = $author->posts()->create(['title' => 'Oʻzbekcha sarlavha', 'slug' => 'first', 'status' => 'published', 'published_at' => now()->subYears(2)]);
        $sly = $author->posts()->create(['title' => '=HYPERLINK("x")', 'slug' => 'sly', 'status' => 'published', 'published_at' => now()->subYear()]);
        $someoneElses = User::factory()->create()->posts()->create(['title' => 'Not mine', 'status' => 'published', 'published_at' => now()->subDay()]);

        $this->views($post, now()->toDateString(), 12);
        $this->views($sly, now()->subDays(3)->toDateString(), 4);
        $this->views($post, now()->subDays(400)->toDateString(), 99);
        $this->views($someoneElses, now()->toDateString(), 50);

        $response = $this->actingAs($author)->get('/admin/stats.csv');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertDownload('views-aziza-'.now()->toDateString().'.csv');

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        $lines = explode("\n", trim(substr($csv, 3)));
        $this->assertSame([
            'date,post,url,views',
            now()->toDateString().',"Oʻzbekcha sarlavha",https://aziza.example.test/posts/first,12',
            now()->subDays(3)->toDateString().',"\'=HYPERLINK(""x"")",https://aziza.example.test/posts/sly,4',
        ], $lines);
    }

    public function test_the_csv_needs_premium_and_a_signed_in_author(): void
    {
        $this->get('/admin/stats.csv')->assertRedirect('/admin/login');

        $this->actingAs($this->author())->get('/admin/stats.csv')->assertForbidden();
    }
}
