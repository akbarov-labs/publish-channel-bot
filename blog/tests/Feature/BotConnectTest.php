<?php

namespace Tests\Feature;

use App\Models\PageRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotConnectTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'internal-test-secret';

    private const HOOK = [
        'telegram_user_id' => 42,
        'webhook_url' => 'https://bot.test/webhooks/site/abcdefghijklmnop1234',
        'webhook_secret' => 'abcdefghijklmnop1234',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['telegram.internal_secret' => self::SECRET]);
    }

    private function connect(array $data = self::HOOK, string $secret = self::SECRET)
    {
        return $this->withToken($secret)->postJson('/api/internal/connect', $data);
    }

    private function authorFromRequest(int $telegramId = 42): User
    {
        $author = User::factory()->create(['username' => 'ali']);
        PageRequest::create([
            'telegram_user_id' => $telegramId,
            'name' => 'Ali',
            'status' => PageRequest::STATUS_ACCEPTED,
            'user_id' => $author->id,
            'times_requested' => 1,
            'last_requested_at' => now(),
        ]);

        return $author;
    }

    public function test_an_author_made_from_a_page_request_is_connected_outright(): void
    {
        $author = $this->authorFromRequest();

        $response = $this->connect()->assertOk()
            ->assertJsonPath('author', 'ali')
            ->assertJsonPath('api_base', url('/api/telegram'));

        $token = $response->json('token');
        $this->assertSame($author->id, User::findByBotToken($token)?->id);

        $author->refresh();
        $this->assertSame(self::HOOK['webhook_url'], $author->webhook_url);
        $this->assertSame(self::HOOK['webhook_secret'], $author->webhook_secret);

        // The token works on the bot's API straight away.
        $this->withToken($token)->getJson('/api/telegram/ping')->assertOk();
    }

    public function test_someone_without_an_author_account_is_told_where_to_get_a_token(): void
    {
        // Asked for a page, but no author was made yet.
        PageRequest::record(['telegram_user_id' => 42, 'name' => 'Ali']);

        $this->connect()->assertNotFound()
            ->assertJsonPath('api_base', url('/api/telegram'))
            ->assertJsonPath('admin_url', url('/admin/profile'))
            ->assertJsonMissingPath('token');
    }

    public function test_connect_needs_the_internal_secret(): void
    {
        $this->authorFromRequest();

        $this->connect(secret: 'guess')->assertUnauthorized();

        config(['telegram.internal_secret' => null]);
        $this->connect()->assertNotFound()->assertJsonMissingPath('token');
    }

    public function test_the_author_token_sets_the_webhook(): void
    {
        $author = User::factory()->create();
        $token = $author->issueBotToken();

        $this->withToken($token)->putJson('/api/telegram/webhook', [
            'url' => 'https://bot.test/webhooks/site/zyxwvutsrqponmlk',
            'secret' => 'zyxwvutsrqponmlk',
        ])->assertOk();

        $author->refresh();
        $this->assertSame('https://bot.test/webhooks/site/zyxwvutsrqponmlk', $author->webhook_url);
        $this->assertSame('zyxwvutsrqponmlk', $author->webhook_secret);

        $this->withToken('wrong')->putJson('/api/telegram/webhook', [
            'url' => 'https://evil.test/hook',
            'secret' => 'zyxwvutsrqponmlk',
        ])->assertUnauthorized();
    }
}
