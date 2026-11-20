<?php

namespace Tests\Feature;

use App\Filament\Resources\Series\Pages\CreateSeries;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class SeriesTest extends TestCase
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

    public function test_a_post_knows_its_series_and_the_parts_are_in_order(): void
    {
        $series = $this->author->series()->create(['title' => 'Silk Road']);
        $this->assertSame('silk-road', $series->slug);

        $make = fn (string $title, ?int $position, string $status = 'published') => $this->author->posts()->create([
            'title' => $title,
            'html' => '<p>x</p>',
            'status' => $status,
            'published_at' => now()->subMinutes(10),
            'series_id' => $series->id,
            'series_position' => $position,
        ]);
        $make('Bukhara', 2);
        $make('Samarqand', 1);
        $make('Unnumbered', null);
        $make('Draft part', 3, 'draft');

        $this->getJson('/api/public/authors/aziza/posts/bukhara')
            ->assertOk()
            ->assertJsonPath('series.title', 'Silk Road')
            ->assertJsonPath('series.parts.*.slug', ['samarqand', 'bukhara', 'unnumbered']);

        $this->getJson('/api/public/authors/aziza/series/silk-road')
            ->assertOk()
            ->assertJsonPath('description', null)
            ->assertJsonCount(3, 'posts')
            ->assertJsonPath('posts.0.title', 'Samarqand');

        $this->getJson('/api/public/authors/aziza/series/nope')->assertNotFound();
    }

    public function test_a_post_outside_a_series_has_none(): void
    {
        $this->author->posts()->create(['title' => 'Alone', 'status' => 'published', 'published_at' => now()->subMinute()]);

        $this->getJson('/api/public/authors/aziza/posts/alone')->assertJsonPath('series', null);
    }

    public function test_an_author_makes_a_series_in_the_admin_and_sees_only_their_own(): void
    {
        $this->actingAs($this->author);

        Livewire::test(CreateSeries::class)
            ->fillForm(['title' => 'Notes from Khiva'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('notes-from-khiva', $this->author->series()->value('slug'));

        User::factory()->create()->series()->create(['title' => 'Somebody else']);
        $this->get('/admin/series')->assertOk()->assertSee('Notes from Khiva')->assertDontSee('Somebody else');
    }

    public function test_footnotes_stay_out_of_the_excerpt(): void
    {
        $this->assertSame(
            'Intro with a note. More text.',
            \App\Models\Post::excerptFrom('<p>Intro with a note.[^1]</p><p>More text.</p><p>[^1]: The note itself.</p>'),
        );
    }
}
