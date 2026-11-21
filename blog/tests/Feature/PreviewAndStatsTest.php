<?php

namespace Tests\Feature;

use App\Filament\Widgets\PostStats;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PreviewAndStatsTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['app.frontend_url' => 'https://example.test']);
        $this->author = User::factory()->create(['username' => 'aziza']);
    }

    public function test_a_draft_is_readable_only_through_its_preview_link(): void
    {
        $post = $this->author->posts()->create(['title' => 'Not yet', 'html' => '<p>Secret</p>', 'status' => 'draft']);

        $url = $post->previewUrl();
        $this->assertStringStartsWith('https://aziza.example.test/preview/', $url);
        $token = basename($url);

        $this->getJson('/api/public/authors/aziza/posts/not-yet')->assertNotFound();
        $this->getJson("/api/public/authors/aziza/preview/{$token}")
            ->assertOk()
            ->assertJsonPath('title', 'Not yet')
            ->assertJsonPath('html', '<p>Secret</p>')
            ->assertJsonPath('preview', true)
            ->assertJsonPath('older', null);

        // Another author's address, or a guessed token, shows nothing.
        User::factory()->create(['username' => 'dmitry']);
        $this->getJson("/api/public/authors/dmitry/preview/{$token}")->assertNotFound();
        $this->getJson('/api/public/authors/aziza/preview/'.str_repeat('a', 40))->assertNotFound();

        // A new link retires the old one.
        $renewed = basename($post->previewUrl(renew: true));
        $this->assertNotSame($token, $renewed);
        $this->getJson("/api/public/authors/aziza/preview/{$token}")->assertNotFound();
        $this->getJson("/api/public/authors/aziza/preview/{$renewed}")->assertOk();
    }

    public function test_the_same_link_is_kept_until_renewed(): void
    {
        $post = $this->author->posts()->create(['title' => 'Stable', 'status' => 'draft']);

        $this->assertSame($post->previewUrl(), $post->fresh()->previewUrl());
    }

    public function test_daily_views_are_zero_filled_and_belong_to_the_author(): void
    {
        $mine = $this->author->posts()->create(['title' => 'Mine', 'status' => 'published', 'published_at' => now()->subWeek()]);
        $theirs = User::factory()->create()->posts()->create(['title' => 'Theirs', 'status' => 'published', 'published_at' => now()->subWeek()]);

        $mine->recordView();
        $mine->recordView();
        $theirs->recordView();
        $this->travel(-2)->days();
        $mine->recordView();
        $this->travelBack();

        $daily = PostStats::dailyViews($this->author->id, 7);

        $this->assertCount(7, $daily);
        $this->assertSame(2, $daily[now()->toDateString()]);
        $this->assertSame(1, $daily[now()->subDays(2)->toDateString()]);
        $this->assertSame(3, array_sum($daily));
    }

    public function test_the_dashboard_shows_the_stats(): void
    {
        $post = $this->author->posts()->create(['title' => 'Popular', 'status' => 'published', 'published_at' => now()->subDay()]);
        $post->recordView();

        $this->actingAs($this->author)->get('/admin')->assertOk();

        // Dashboard widgets load lazily, so render them directly.
        \Livewire\Livewire::test(PostStats::class)
            ->assertSee('Views, last 30 days')
            ->assertSee('Published posts');
        \Livewire\Livewire::test(\App\Filament\Widgets\TopPosts::class)
            ->assertSee('Popular');
        \Livewire\Livewire::test(\App\Filament\Widgets\ViewsChart::class)->assertOk();
    }

    public function test_the_edit_page_offers_a_preview(): void
    {
        $post = $this->author->posts()->create(['title' => 'Draft', 'status' => 'draft']);

        $this->actingAs($this->author)
            ->get("/admin/posts/{$post->id}/edit")
            ->assertOk()
            ->assertSee('/preview/', false);
    }
}
