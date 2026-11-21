<?php

namespace Tests\Feature;

use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** The post form's extra fields: topics, scheduling, pinning. */
class AdminPostFormTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->author = User::factory()->create(['username' => 'aziza']);
        $this->actingAs($this->author);
    }

    public function test_topics_from_the_form_and_the_body_are_saved(): void
    {
        Livewire::test(CreatePost::class)
            ->fillForm([
                'title' => 'Samarqand',
                'slug' => 'samarqand',
                'html' => '<p>Day one #travel</p>',
                'topics' => ['History'],
                'status' => 'published',
                'post_type' => 'article',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = $this->author->posts()->firstOrFail();
        $this->assertEqualsCanonicalizing(['History', 'travel'], $post->tags->pluck('name')->all());
        $this->assertNotNull($post->published_at, 'publishing without a date publishes now');

        // Removing a topic in the form removes it; the body's hashtag stays.
        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->assertSchemaStateSet(['topics' => ['History', 'travel']])
            ->fillForm(['topics' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['travel'], $post->fresh()->tags->pluck('name')->all());
    }

    public function test_a_post_can_be_scheduled_and_pinned_from_the_form(): void
    {
        Livewire::test(CreatePost::class)
            ->fillForm([
                'title' => 'Later',
                'slug' => 'later',
                'status' => 'published',
                'post_type' => 'article',
                'published_at' => now()->addDays(2)->format('Y-m-d H:i'),
                'pinned_at' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = $this->author->posts()->firstOrFail();
        $this->assertTrue($post->isScheduled());
        $this->assertNotNull($post->pinned_at);
    }
}
