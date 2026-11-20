<?php

namespace Tests\Feature;

use App\Jobs\PublishToTelegram;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SchedulingAndPinningTest extends TestCase
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

    private function publish(string $title, array $attributes = []): Post
    {
        return $this->author->posts()->create([
            'title' => $title,
            'html' => "<p>{$title}</p>",
            'status' => 'published',
            'published_at' => now(),
            ...$attributes,
        ]);
    }

    public function test_a_scheduled_post_stays_hidden_and_unannounced_until_its_time(): void
    {
        $post = $this->publish('Tomorrow', ['published_at' => now()->addDay()]);

        $this->assertTrue($post->fresh()->isScheduled());
        $this->assertTrue($post->fresh()->announce_pending);
        Queue::assertNotPushed(PublishToTelegram::class);
        $this->getJson('/api/public/authors/aziza/posts')->assertJsonCount(0, 'data');
        $this->getJson('/api/public/authors/aziza/posts/tomorrow')->assertNotFound();

        $this->artisan('posts:announce-due')->assertSuccessful();
        Queue::assertNotPushed(PublishToTelegram::class);

        $this->travel(25)->hours();

        $this->artisan('posts:announce-due')->assertSuccessful();
        Queue::assertPushed(PublishToTelegram::class, 1);
        $this->assertFalse($post->fresh()->announce_pending);
        $this->getJson('/api/public/authors/aziza/posts')->assertJsonPath('data.0.title', 'Tomorrow');

        // Running again does not announce it twice.
        $this->artisan('posts:announce-due')->assertSuccessful();
        Queue::assertPushed(PublishToTelegram::class, 1);
    }

    public function test_an_old_unannounced_post_is_never_sent_by_the_scheduler(): void
    {
        $this->publish('Long ago', ['published_at' => now()->subYear()]);
        Queue::fake();

        $this->artisan('posts:announce-due')->assertSuccessful();

        Queue::assertNotPushed(PublishToTelegram::class);
    }

    public function test_publishing_without_a_date_publishes_now(): void
    {
        $post = $this->publish('Now', ['published_at' => null]);

        $this->assertNotNull($post->published_at);
        Queue::assertPushed(PublishToTelegram::class);
    }

    public function test_pinning_a_post_unpins_the_previous_one_of_the_same_author_only(): void
    {
        $first = $this->publish('First', ['pinned_at' => now()]);
        $other = User::factory()->create(['username' => 'dmitry']);
        $theirs = $other->posts()->create(['title' => 'Theirs', 'status' => 'published', 'pinned_at' => now()]);

        $second = $this->publish('Second');
        $second->update(['pinned_at' => now()]);

        $this->assertNull($first->fresh()->pinned_at);
        $this->assertNotNull($second->fresh()->pinned_at);
        $this->assertNotNull($theirs->fresh()->pinned_at);
    }

    public function test_the_first_page_carries_the_pinned_post(): void
    {
        $this->publish('Pinned one', ['published_at' => now()->subWeek(), 'pinned_at' => now()]);
        $this->publish('Newest');

        $this->getJson('/api/public/authors/aziza/posts')
            ->assertOk()
            ->assertJsonPath('pinned.title', 'Pinned one')
            ->assertJsonPath('pinned.pinned', true)
            ->assertJsonPath('data.0.title', 'Newest');

        $this->getJson('/api/public/authors/aziza/posts?page=2')->assertJsonMissingPath('pinned');
    }

    public function test_pinning_in_the_channel_pins_on_the_site(): void
    {
        $token = $this->author->issueBotToken();
        $post = $this->publish('From the channel', ['source' => 'telegram']);

        $this->postJson("/api/telegram/posts/{$post->id}/pin", [], ['Authorization' => "Bearer {$token}"])
            ->assertOk();
        $this->assertNotNull($post->fresh()->pinned_at);

        $stranger = User::factory()->create(['username' => 'stranger'])->issueBotToken();
        $this->postJson("/api/telegram/posts/{$post->id}/pin", [], ['Authorization' => "Bearer {$stranger}"])
            ->assertNotFound();
    }
}
