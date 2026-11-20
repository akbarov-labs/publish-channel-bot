<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AudioAndPollTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private array $bot;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['app.frontend_url' => 'https://example.test']);
        $this->author = User::factory()->create(['username' => 'aziza']);
        $this->bot = ['Authorization' => 'Bearer '.$this->author->issueBotToken()];
    }

    public function test_an_audio_post_keeps_its_tracks(): void
    {
        $this->postJson('/api/telegram/posts', [
            'source' => 'telegram',
            'external_id' => 'tg:-1001:1',
            'title' => 'Morning thoughts',
            'post_type' => 'audio',
            'media' => [['type' => 'audio', 'url' => 'https://cdn.test/a.ogg']],
        ], $this->bot)->assertCreated();

        $this->getJson('/api/public/authors/aziza/posts/morning-thoughts')
            ->assertJsonPath('post_type', 'audio')
            ->assertJsonPath('media.0.type', 'audio')
            ->assertJsonPath('cover_image', null);
    }

    public function test_a_poll_is_stored_with_its_options(): void
    {
        $this->postJson('/api/telegram/posts', [
            'source' => 'telegram',
            'external_id' => 'tg:-1001:2',
            'title' => 'Tea or coffee?',
            'post_type' => 'poll',
            'poll' => [
                'question' => 'Tea or coffee?',
                'options' => [['text' => 'Tea', 'votes' => 7], ['text' => 'Coffee', 'votes' => 3]],
                'total_votes' => 10,
                'closed' => false,
            ],
        ], $this->bot)->assertCreated();

        $this->getJson('/api/public/authors/aziza/posts/tea-or-coffee')
            ->assertJsonPath('post_type', 'poll')
            ->assertJsonPath('poll.options.1.text', 'Coffee')
            ->assertJsonPath('poll.total_votes', 10)
            ->assertJsonPath('poll.quiz', false);
    }

    public function test_a_malformed_poll_is_rejected(): void
    {
        $this->postJson('/api/telegram/posts', [
            'source' => 'telegram',
            'external_id' => 'tg:-1001:3',
            'title' => 'Broken',
            'post_type' => 'poll',
            'poll' => ['question' => 'No options'],
        ], $this->bot)->assertUnprocessable();
    }
}
