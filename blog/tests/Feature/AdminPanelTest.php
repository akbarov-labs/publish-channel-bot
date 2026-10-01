<?php

namespace Tests\Feature;

use App\Models\Post;
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

    public function test_an_author_can_list_posts(): void
    {
        Http::fake();
        Post::create(['title' => 'Hello world', 'status' => 'published', 'published_at' => now()]);

        $this->actingAs(User::factory()->create())
            ->get('/admin/posts')
            ->assertOk()
            ->assertSee('Hello world');
    }

    public function test_the_post_form_loads(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/posts/create')
            ->assertOk();
    }
}
