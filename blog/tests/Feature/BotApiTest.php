<?php

namespace Tests\Feature;

use App\Jobs\PublishToTelegram;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BotApiTest extends TestCase
{
    use RefreshDatabase;

    private const LEGACY_TOKEN = 'legacy-env-token';

    private User $author;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.frontend_url' => 'https://example.test',
            'telegram.api_token' => self::LEGACY_TOKEN,
        ]);

        $this->author = User::factory()->create([
            'username' => 'aziza',
            'webhook_url' => 'https://bot.test/webhooks/site/secret-123',
            'webhook_secret' => 'secret-123',
        ]);
        $this->token = $this->author->issueBotToken();
    }

    private function asBot(?string $token = null): array
    {
        return ['Authorization' => 'Bearer '.($token ?? $this->token)];
    }

    public function test_each_author_has_their_own_token(): void
    {
        $other = User::factory()->create(['username' => 'dmitry']);
        $otherToken = $other->issueBotToken();

        $this->getJson('/api/telegram/ping', $this->asBot())->assertJsonPath('author', 'aziza');
        $this->getJson('/api/telegram/ping', $this->asBot($otherToken))->assertJsonPath('author', 'dmitry');
    }

    public function test_the_legacy_env_token_acts_for_the_first_author(): void
    {
        User::factory()->create(['username' => 'later']);

        $this->getJson('/api/telegram/ping', $this->asBot(self::LEGACY_TOKEN))
            ->assertOk()
            ->assertJsonPath('author', 'aziza');
    }

    public function test_only_the_hash_of_a_bot_token_is_stored(): void
    {
        $this->assertSame(hash('sha256', $this->token), $this->author->fresh()->bot_token_hash);
        $this->assertArrayNotHasKey('bot_token_hash', $this->author->fresh()->toArray());
    }

    public function test_a_channel_post_is_owned_by_the_tokens_author_and_links_to_their_subdomain(): void
    {
        $response = $this->postJson('/api/telegram/posts', [
            'source' => 'telegram',
            'external_id' => 'tg:-100123:44',
            'title' => 'Mine',
            'html' => '<p>Body</p>',
        ], $this->asBot())->assertCreated();

        $this->assertSame($this->author->id, Post::firstOrFail()->user_id);
        $this->assertSame('https://aziza.example.test/posts/mine', $response->json('url'));
    }

    public function test_an_author_cannot_edit_another_authors_post(): void
    {
        $other = User::factory()->create(['username' => 'dmitry']);
        $post = $other->posts()->create([
            'title' => 'Not yours',
            'status' => 'published',
            'source' => 'telegram',
            'published_at' => now(),
        ]);

        $this->patchJson("/api/telegram/posts/{$post->id}", ['title' => 'Hijacked'], $this->asBot())
            ->assertNotFound();

        $this->assertSame('Not yours', $post->fresh()->title);
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
        $post = $this->author->posts()->create([
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

        $post = $this->author->posts()->create([
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
}
