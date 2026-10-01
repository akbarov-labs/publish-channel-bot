<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_requires_a_login(): void
    {
        $this->get('/admin/posts')->assertRedirect('/admin/login');
    }

    public function test_an_author_can_list_their_posts(): void
    {
        Http::fake();
        $author = User::factory()->create();
        $author->posts()->create(['title' => 'Hello world', 'status' => 'published', 'published_at' => now()]);

        $this->actingAs($author)
            ->get('/admin/posts')
            ->assertOk()
            ->assertSee('Hello world');
    }

    public function test_an_author_does_not_see_other_authors_posts(): void
    {
        Http::fake();
        $other = User::factory()->create();
        $other->posts()->create(['title' => 'Somebody else', 'status' => 'draft']);
        $post = $other->posts()->first();

        $this->actingAs(User::factory()->create())
            ->get('/admin/posts')
            ->assertOk()
            ->assertDontSee('Somebody else');

        $this->actingAs(User::factory()->create())
            ->get("/admin/posts/{$post->id}/edit")
            ->assertNotFound();
    }

    public function test_the_post_form_loads(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/posts/create')
            ->assertOk();
    }

    public function test_the_profile_page_loads(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/profile')
            ->assertOk()
            ->assertSee('Generate bot token');
    }
}
