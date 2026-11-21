<?php

namespace Tests\Feature;

use App\Filament\Resources\Authors\Pages\EditAuthor;
use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Filament\Resources\Authors\Pages\ViewAuthor;
use App\Filament\Resources\Authors\RelationManagers\PostsRelationManager;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AuthorsAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        config(['app.frontend_url' => 'https://example.test']);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['username' => 'boss']);
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    private function author(string $username = 'aziza'): User
    {
        $author = User::factory()->create(['username' => $username, 'password' => 'old-password']);
        $author->posts()->create(['title' => 'Aziza writes', 'status' => 'published', 'published_at' => now()->subHour()]);
        $author->posts()->create(['title' => 'Aziza drafts', 'status' => 'draft']);

        return $author;
    }

    // --- Who sees it ----------------------------------------------------------

    public function test_only_the_admin_sees_authors(): void
    {
        $author = $this->author();

        $this->actingAs($author)->get('/admin/authors')->assertForbidden();
        $this->actingAs($author)->get("/admin/authors/{$author->id}")->assertForbidden();

        // A new browser: the session guard signs out on a change of user.
        $this->flushSession();

        $this->actingAs($this->admin())
            ->get('/admin/authors')
            ->assertOk()
            ->assertSee($author->email);
    }

    public function test_the_admin_sees_an_authors_page_with_all_their_posts(): void
    {
        $admin = $this->admin();
        $author = $this->author();

        $this->actingAs($admin)->get("/admin/authors/{$author->id}")->assertOk()->assertSee('aziza.example.test');

        Livewire::actingAs($admin)
            ->test(PostsRelationManager::class, ['ownerRecord' => $author, 'pageClass' => ViewAuthor::class])
            ->assertOk()
            ->assertSee('Aziza writes')
            ->assertSee('Aziza drafts');
    }

    public function test_the_admin_edits_an_authors_account(): void
    {
        $admin = $this->admin();
        $author = $this->author();

        Livewire::actingAs($admin)
            ->test(EditAuthor::class, ['record' => $author->getRouteKey()])
            ->fillForm(['email' => 'new@example.test', 'username' => 'aziza-new'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('new@example.test', $author->fresh()->email);
        $this->assertSame('aziza-new', $author->fresh()->username);

        Livewire::actingAs($admin)
            ->test(EditAuthor::class, ['record' => $author->getRouteKey()])
            ->fillForm(['username' => 'admin'])
            ->call('save')
            ->assertHasFormErrors(['username']);
    }

    // --- Passwords --------------------------------------------------------------

    public function test_the_admin_sets_a_new_password(): void
    {
        $admin = $this->admin();
        $author = $this->author();

        Livewire::actingAs($admin)
            ->test(ListAuthors::class)
            ->callAction(TestAction::make('setPassword')->table($author), ['password' => 'brand-new-pass'])
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('brand-new-pass', $author->fresh()->password));
        $this->assertFalse(Hash::check('old-password', $author->fresh()->password));
    }

    public function test_a_password_must_be_long_enough(): void
    {
        $author = $this->author();

        Livewire::actingAs($this->admin())
            ->test(ListAuthors::class)
            ->callAction(TestAction::make('setPassword')->table($author), ['password' => 'short'])
            ->assertHasFormErrors(['password']);

        $this->assertTrue(Hash::check('old-password', $author->fresh()->password));
    }

    // --- Blocking ---------------------------------------------------------------

    public function test_the_admin_blocks_and_unblocks_an_author(): void
    {
        $admin = $this->admin();
        $author = $this->author();

        Livewire::actingAs($admin)
            ->test(ListAuthors::class)
            ->callAction(TestAction::make('block')->table($author));

        $this->assertTrue($author->fresh()->isBlocked());

        Livewire::actingAs($admin)
            ->test(ViewAuthor::class, ['record' => $author->getRouteKey()])
            ->assertActionHidden('block')
            ->callAction('unblock');

        $this->assertFalse($author->fresh()->isBlocked());
    }

    public function test_the_admin_cannot_be_blocked(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(ListAuthors::class)
            ->assertActionHidden(TestAction::make('block')->table($admin));
    }

    public function test_a_blocked_author_cannot_sign_in_or_keep_their_session(): void
    {
        $author = $this->author();
        $this->actingAs($author)->get('/admin/posts')->assertOk();

        $author->block();

        $this->actingAs($author->fresh())->get('/admin/posts')->assertForbidden();
        $this->assertFalse($author->fresh()->canAccessPanel(filament()->getDefaultPanel()));
    }

    public function test_a_blocked_authors_bot_is_refused(): void
    {
        $author = $this->author();
        $token = $author->issueBotToken();
        $author->block();

        $this->withToken($token)->getJson('/api/telegram/ping')->assertForbidden();
    }

    public function test_a_blocked_author_leaves_the_reader_site_and_comes_back(): void
    {
        $author = $this->author();
        $this->getJson('/api/public/authors/aziza')->assertOk();

        $author->block();

        $this->getJson('/api/public/authors/aziza')->assertNotFound();
        $this->getJson('/api/public/authors/aziza/posts')->assertNotFound();
        $this->getJson('/api/public/authors/aziza/sitemap')->assertNotFound();
        $this->assertSame([], $this->getJson('/api/public/authors')->json('data'));
        $this->assertSame([], $this->getJson('/api/public/posts')->json('data'));
        $this->get('/api/tls/allowed?domain=aziza.example.test')->assertNotFound();

        $author->unblock();

        $this->getJson('/api/public/authors/aziza')->assertOk();
        $this->assertCount(1, $this->getJson('/api/public/posts')->json('data'));
    }
}
