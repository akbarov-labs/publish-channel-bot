<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PublicApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['app.frontend_url' => 'https://example.test']);
    }

    private function publish(User $author, string $title, array $attributes = []): Post
    {
        return $author->posts()->create([
            'title' => $title,
            'html' => '<p>Body of '.$title.'</p>',
            'status' => 'published',
            'published_at' => now(),
            ...$attributes,
        ]);
    }

    public function test_the_root_feed_lists_published_posts_from_every_author(): void
    {
        $aziza = User::factory()->create(['username' => 'aziza']);
        $dmitry = User::factory()->create(['username' => 'dmitry']);
        $this->publish($aziza, 'Older', ['published_at' => now()->subDay()]);
        $this->publish($dmitry, 'Newer');
        $aziza->posts()->create(['title' => 'Draft', 'status' => 'draft']);

        $response = $this->getJson('/api/public/posts')->assertOk();

        $this->assertSame(['Newer', 'Older'], array_column($response->json('data'), 'title'));
        $response->assertJsonPath('data.0.author.username', 'dmitry')
            ->assertJsonPath('data.0.url', 'https://dmitry.example.test/posts/newer')
            ->assertJsonMissingPath('data.0.html');
    }

    public function test_the_directory_lists_only_authors_with_posts_and_hides_private_fields(): void
    {
        $aziza = User::factory()->create(['username' => 'aziza', 'telegram_channel' => '@aziza_notes']);
        User::factory()->create(['username' => 'quiet']);
        $this->publish($aziza, 'One');
        $this->publish($aziza, 'Two');

        $response = $this->getJson('/api/public/authors')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.username', 'aziza')
            ->assertJsonPath('data.0.posts_count', 2)
            ->assertJsonPath('data.0.telegram_url', 'https://t.me/aziza_notes')
            ->assertJsonPath('data.0.site_url', 'https://aziza.example.test');

        $author = $response->json('data.0');
        foreach (['email', 'password', 'bot_token_hash', 'webhook_secret', 'webhook_url'] as $private) {
            $this->assertArrayNotHasKey($private, $author);
        }
    }

    public function test_an_authors_feed_contains_only_their_posts(): void
    {
        $aziza = User::factory()->create(['username' => 'aziza']);
        $dmitry = User::factory()->create(['username' => 'dmitry']);
        $this->publish($aziza, 'Hers');
        $this->publish($dmitry, 'His');

        $response = $this->getJson('/api/public/authors/aziza/posts')->assertOk();

        $this->assertSame(['Hers'], array_column($response->json('data'), 'title'));
        $this->getJson('/api/public/authors/nobody/posts')->assertNotFound();
    }

    public function test_two_authors_can_use_the_same_slug(): void
    {
        $aziza = User::factory()->create(['username' => 'aziza']);
        $dmitry = User::factory()->create(['username' => 'dmitry']);
        $this->publish($aziza, 'Hello world');
        $this->publish($dmitry, 'Hello world');

        $this->getJson('/api/public/authors/aziza/posts/hello-world')
            ->assertOk()
            ->assertJsonPath('author.username', 'aziza')
            ->assertJsonPath('html', '<p>Body of Hello world</p>');
        $this->getJson('/api/public/authors/dmitry/posts/hello-world')
            ->assertOk()
            ->assertJsonPath('author.username', 'dmitry');
    }

    public function test_a_post_comes_with_its_carousel_and_neighbours(): void
    {
        $aziza = User::factory()->create(['username' => 'aziza']);
        $this->publish($aziza, 'First', ['published_at' => now()->subDays(2)]);
        $gallery = $this->publish($aziza, 'Gallery', ['post_type' => 'gallery', 'published_at' => now()->subDay()]);
        $this->publish($aziza, 'Third');
        $gallery->media()->create(['url' => 'https://cdn.test/1.jpg', 'type' => 'photo', 'position' => 0]);
        $gallery->media()->create(['url' => 'posts/2.jpg', 'type' => 'photo', 'position' => 1]);

        $response = $this->getJson('/api/public/authors/aziza/posts/gallery')
            ->assertOk()
            ->assertJsonPath('post_type', 'gallery')
            ->assertJsonPath('media.0.url', 'https://cdn.test/1.jpg')
            ->assertJsonPath('older.slug', 'first')
            ->assertJsonPath('newer.slug', 'third');

        // Admin uploads are stored as disk paths and must reach the site as URLs.
        $this->assertStringStartsWith('http', $response->json('media.1.url'));
        $this->assertSame(['third', 'first'], array_column($response->json('more'), 'slug'));
    }

    public function test_a_missing_excerpt_is_derived_without_running_blocks_together(): void
    {
        $aziza = User::factory()->create(['username' => 'aziza']);
        $post = $aziza->posts()->create([
            'title' => 'Admin post',
            'html' => '<h3>Heading</h3><p>First&nbsp;line</p><ul><li>One</li><li>Two</li></ul>',
            'status' => 'draft',
        ]);

        $this->assertSame('Heading. First line. One. Two', $post->excerpt);
    }

    public function test_drafts_are_not_reachable_by_slug(): void
    {
        $aziza = User::factory()->create(['username' => 'aziza']);
        $aziza->posts()->create(['title' => 'Secret', 'status' => 'draft']);

        $this->getJson('/api/public/authors/aziza/posts/secret')->assertNotFound();
    }

    public function test_an_author_profile_has_stats(): void
    {
        $aziza = User::factory()->create(['username' => 'aziza', 'bio' => 'Notes from Tashkent']);
        $this->publish($aziza, 'One');

        $this->getJson('/api/public/authors/aziza')
            ->assertOk()
            ->assertJsonPath('name', $aziza->name)
            ->assertJsonPath('bio', 'Notes from Tashkent')
            ->assertJsonPath('posts_count', 1)
            ->assertJsonMissingPath('email');
    }
}
