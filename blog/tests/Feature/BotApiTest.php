<?php

namespace Tests\Feature;

use App\Jobs\PublishToTelegram;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BotApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-bot-token';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'telegram.api_token' => self::TOKEN,
            'telegram.webhook_url' => 'https://bot.test/webhooks/site/secret-123',
            'telegram.webhook_secret' => 'secret-123',
        ]);
    }

    private function asBot(): array
    {
        return ['Authorization' => 'Bearer '.self::TOKEN];
    }

    public function test_the_bot_can_verify_its_credentials(): void
    {
        $this->getJson('/api/telegram/ping', $this->asBot())
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_a_wrong_token_is_rejected(): void
    {
        $this->getJson('/api/telegram/ping', ['Authorization' => 'Bearer nope'])
            ->assertUnauthorized();
    }

    public function test_an_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/telegram/ping')->assertUnauthorized();
    }

    public function test_a_channel_post_becomes_a_published_post(): void
    {
        $response = $this->postJson('/api/telegram/posts', [
            'source' => 'telegram',
            'external_id' => 'tg:-100123:45',
            'title' => 'From the channel',
            'html' => '<p>Body text</p>',
            'post_type' => 'article',
        ], $this->asBot())->assertCreated();

        $post = Post::firstOrFail();
        $this->assertSame('From the channel', $post->title);
        $this->assertSame('from-the-channel', $post->slug);
        $this->assertSame('published', $post->status);
        $this->assertSame('telegram', $post->source);
        $this->assertSame('Body text', $post->excerpt);
        $this->assertSame((string) $post->id, $response->json('id'));
    }

    public function test_an_album_becomes_a_carousel_with_its_order_kept(): void
    {
        $this->postJson('/api/telegram/posts', [
            'source' => 'telegram',
            'external_id' => 'tg:-100123:46',
            'title' => 'Trip photos',
            'html' => '<p>Album</p>',
            'post_type' => 'gallery',
            'media' => [
                ['type' => 'photo', 'url' => 'https://cdn.test/1.jpg'],
                ['type' => 'photo', 'url' => 'https://cdn.test/2.jpg'],
                ['type' => 'photo', 'url' => 'https://cdn.test/3.jpg'],
            ],
        ], $this->asBot())->assertCreated();

        $post = Post::with('media')->firstOrFail();
        $this->assertSame('gallery', $post->post_type);
        $this->assertCount(3, $post->media);
        $this->assertSame([0, 1, 2], $post->media->pluck('position')->all());
        $this->assertSame('https://cdn.test/1.jpg', $post->media->first()->url);
        $this->assertSame('https://cdn.test/1.jpg', $post->cover_image);
    }

    public function test_redelivery_of_the_same_message_does_not_duplicate_the_post(): void
    {
        $payload = [
            'source' => 'telegram',
            'external_id' => 'tg:-100123:47',
            'title' => 'Only once',
            'html' => '<p>Body</p>',
        ];

        $first = $this->postJson('/api/telegram/posts', $payload, $this->asBot())->assertCreated();
        $second = $this->postJson('/api/telegram/posts', $payload, $this->asBot())->assertOk();

        $this->assertSame(1, Post::count());
        $this->assertSame($first->json('id'), $second->json('id'));
    }

    public function test_an_edited_channel_post_updates_the_article(): void
    {
        $post = Post::create([
            'title' => 'Before',
            'html' => '<p>old</p>',
            'status' => 'published',
            'source' => 'telegram',
            'external_id' => 'tg:-100123:48',
            'published_at' => now(),
        ]);

        $this->patchJson("/api/telegram/posts/{$post->id}", [
            'title' => 'After',
            'html' => '<p>new</p>',
        ], $this->asBot())->assertOk();

        $this->assertSame('After', $post->fresh()->title);
        $this->assertSame('<p>new</p>', $post->fresh()->html);
    }

    public function test_a_telegram_sourced_post_is_never_sent_back_to_telegram(): void
    {
        Queue::fake();

        $this->postJson('/api/telegram/posts', [
            'source' => 'telegram',
            'external_id' => 'tg:-100123:49',
            'title' => 'Loop guard',
            'html' => '<p>Body</p>',
        ], $this->asBot())->assertCreated();

        Queue::assertNothingPushed();
    }

    public function test_publishing_from_the_admin_notifies_the_bot(): void
    {
        Http::fake(['bot.test/*' => Http::response(['status' => 'ok'])]);

        $post = Post::create([
            'title' => 'Written in the admin',
            'html' => '<p>Body</p>',
            'status' => 'published',
            'source' => 'web',
            'published_at' => now(),
        ]);

        Http::assertSent(function ($request) use ($post) {
            $body = $request->body();
            $expected = 'sha256='.hash_hmac('sha256', $body, 'secret-123');

            return $request->hasHeader('X-Signature', $expected)
                && json_decode($body, true)['post']['id'] === (string) $post->id;
        });

        $this->assertNotNull($post->fresh()->telegram_notified_at);
    }

    public function test_a_draft_does_not_notify_the_bot(): void
    {
        Queue::fake();

        Post::create(['title' => 'Draft', 'html' => '<p>x</p>', 'status' => 'draft']);

        Queue::assertNothingPushed();
    }

    public function test_the_public_feed_only_lists_published_posts(): void
    {
        Http::fake();
        Post::create(['title' => 'Live', 'status' => 'published', 'published_at' => now()]);
        Post::create(['title' => 'Hidden', 'status' => 'draft']);

        $response = $this->getJson('/api/public/posts')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Live', $response->json('data.0.title'));
    }

    public function test_a_post_can_be_fetched_by_slug_with_its_carousel(): void
    {
        Http::fake();
        $post = Post::create(['title' => 'Gallery post', 'post_type' => 'gallery', 'status' => 'published', 'published_at' => now()]);
        $post->media()->create(['url' => 'https://cdn.test/1.jpg', 'type' => 'photo', 'position' => 0]);

        $this->getJson('/api/public/posts/gallery-post')
            ->assertOk()
            ->assertJsonPath('post_type', 'gallery')
            ->assertJsonPath('media.0.url', 'https://cdn.test/1.jpg');
    }
}
