<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EngagementTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['app.frontend_url' => 'https://example.test']);
        $this->author = User::factory()->create(['username' => 'aziza', 'telegram_channel' => 'https://t.me/aziza_notes']);
        $this->token = $this->author->issueBotToken();
    }

    private function bot(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_the_channel_username_is_read_from_any_way_of_writing_it(): void
    {
        foreach (['@aziza_notes', 'aziza_notes', 'https://t.me/aziza_notes', 't.me/aziza_notes/12'] as $written) {
            $this->author->telegram_channel = $written;
            $this->assertSame('aziza_notes', $this->author->channelUsername(), $written);
        }

        foreach (['https://t.me/+AbCdEf123', '', 'https://t.me/joinchat/xyz'] as $private) {
            $this->author->telegram_channel = $private;
            $this->assertNull($this->author->channelUsername(), $private);
        }
    }

    public function test_a_channel_post_knows_its_message_and_links_to_it(): void
    {
        $this->postJson('/api/telegram/posts', [
            'source' => 'telegram',
            'external_id' => 'tg:-1001234:57',
            'title' => 'From the channel',
            'html' => '<p>x</p>',
        ], $this->bot())->assertCreated();

        $this->getJson('/api/public/authors/aziza/posts/from-the-channel')
            ->assertJsonPath('telegram_post', 'aziza_notes/57')
            ->assertJsonPath('views', 0)
            ->assertJsonPath('reactions', []);
    }

    public function test_the_bot_reports_where_it_announced_an_admin_post(): void
    {
        $post = $this->author->posts()->create(['title' => 'Written here', 'status' => 'draft']);

        $this->putJson("/api/telegram/posts/{$post->id}/channel-message", ['message_id' => 88], $this->bot())->assertOk();

        $this->assertSame(88, $post->fresh()->telegram_message_id);
        $this->assertSame('https://t.me/aziza_notes/88', $post->fresh()->telegramPostUrl());
    }

    public function test_reactions_replace_the_last_count_and_skip_zeroes(): void
    {
        $post = $this->author->posts()->create(['title' => 'Liked', 'status' => 'published', 'published_at' => now()->subMinute()]);

        $this->putJson("/api/telegram/posts/{$post->id}/reactions", ['reactions' => [
            ['emoji' => '👍', 'count' => 3],
            ['emoji' => '🔥', 'count' => 9],
            ['emoji' => '😢', 'count' => 0],
        ]], $this->bot())->assertOk();

        $this->getJson('/api/public/authors/aziza/posts/liked')
            ->assertJsonPath('reactions', [['emoji' => '🔥', 'count' => 9], ['emoji' => '👍', 'count' => 3]]);

        $this->putJson("/api/telegram/posts/{$post->id}/reactions", ['reactions' => []], $this->bot())->assertOk();
        $this->assertSame([], $post->fresh()->reactions);

        $stranger = User::factory()->create()->issueBotToken();
        $this->putJson("/api/telegram/posts/{$post->id}/reactions", ['reactions' => []], ['Authorization' => "Bearer {$stranger}"])
            ->assertNotFound();
    }

    public function test_a_view_counts_toward_the_total_and_the_day(): void
    {
        $post = $this->author->posts()->create(['title' => 'Read me', 'status' => 'published', 'published_at' => now()->subMinute()]);

        $this->postJson('/api/public/authors/aziza/posts/read-me/view')->assertOk()->assertJsonPath('views', 1);
        $this->postJson('/api/public/authors/aziza/posts/read-me/view')->assertOk()->assertJsonPath('views', 2);

        $this->assertSame(2, (int) DB::table('post_views')->where('post_id', $post->id)->value('views'));

        $this->author->posts()->create(['title' => 'Draft', 'status' => 'draft']);
        $this->postJson('/api/public/authors/aziza/posts/draft/view')->assertNotFound();
    }

    public function test_views_from_one_address_are_capped(): void
    {
        $this->author->posts()->create(['title' => 'Busy', 'status' => 'published', 'published_at' => now()->subMinute()]);

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/public/authors/aziza/posts/busy/view')->assertOk();
        }
        $this->postJson('/api/public/authors/aziza/posts/busy/view')->assertTooManyRequests();
    }
}
