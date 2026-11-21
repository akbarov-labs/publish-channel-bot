<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TopicsAndSearchTest extends TestCase
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

    private function publish(string $title, string $html, array $attributes = []): Post
    {
        return $this->author->posts()->create([
            'title' => $title,
            'html' => $html,
            'status' => 'published',
            'published_at' => now(),
            ...$attributes,
        ]);
    }

    public function test_hashtags_are_found_and_lookalikes_are_not(): void
    {
        $this->assertSame(
            ['travel', 'Oʻzbekiston', 'kitoblar'],
            Post::hashtagsIn('<p>#travel to <b>#Oʻzbekiston</b>. C# and #1 and <a href="#top">#kitoblar</a> #TRAVEL</p>'),
        );
    }

    public function test_hashtags_in_a_channel_post_become_topics(): void
    {
        $post = $this->publish('Samarqand', '<p>Day one #travel #Samarqand</p>');

        $this->assertEqualsCanonicalizing(['Samarqand', 'travel'], $post->tags()->pluck('name')->all());

        // An edit adds new hashtags and keeps the old topics.
        $post->update(['html' => '<p>Day one, edited #food</p>']);
        $this->assertEqualsCanonicalizing(['food', 'Samarqand', 'travel'], $post->fresh()->tags()->pluck('name')->all());
    }

    public function test_topics_are_per_author(): void
    {
        $this->publish('Mine', '<p>#travel</p>');
        $other = User::factory()->create(['username' => 'dmitry']);
        $other->posts()->create(['title' => 'Theirs', 'html' => '<p>#travel</p>', 'status' => 'published']);

        $this->assertSame(2, \App\Models\Tag::where('slug', 'travel')->count());
    }

    public function test_an_author_lists_posts_by_topic_and_their_topics(): void
    {
        $this->publish('One', '<p>#travel</p>', ['published_at' => now()->subDay()]);
        $this->publish('Two', '<p>#travel #food</p>');
        $this->publish('Three', '<p>nothing</p>');
        $this->author->posts()->create(['title' => 'Draft', 'html' => '<p>#secret</p>', 'status' => 'draft']);

        $this->getJson('/api/public/authors/aziza/posts?topic=Travel')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Two')
            ->assertJsonPath('data.0.topics.0.slug', 'food')
            ->assertJsonMissingPath('pinned');

        $this->getJson('/api/public/authors/aziza/posts?topic=nope')->assertNotFound();

        $this->getJson('/api/public/authors/aziza/topics')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'travel')
            ->assertJsonPath('data.0.posts_count', 2)
            ->assertJsonCount(2, 'data'); // the draft's #secret is not listed
    }

    public function test_search_matches_every_word_in_title_or_text(): void
    {
        $this->publish('Mountain trip', '<p>Snow and wind</p>');
        $this->publish('City walk', '<p>Wind in the streets</p>');
        $this->publish('100% sure', '<p>plain</p>');

        $titles = fn (string $q) => array_column($this->getJson('/api/public/authors/aziza/posts?q='.urlencode($q))->json('data'), 'title');

        $this->assertEqualsCanonicalizing(['Mountain trip', 'City walk'], $titles('wind'));
        $this->assertSame(['Mountain trip'], $titles('WIND snow'));
        $this->assertSame(['100% sure'], $titles('100%'));
        $this->assertSame([], $titles('nothing-like-this'));
    }

    public function test_listings_carry_a_reading_time(): void
    {
        $this->publish('Short', '<p>A few words.</p>');
        $this->publish('Long', '<p>'.str_repeat('word ', 1000).'</p>', ['published_at' => now()->subHour()]);

        $this->getJson('/api/public/authors/aziza/posts')
            ->assertJsonPath('data.0.reading_minutes', 1)
            ->assertJsonPath('data.1.reading_minutes', 5);
    }
}
