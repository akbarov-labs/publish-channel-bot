<?php

namespace Tests\Feature;

use App\Filament\Pages\EditProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_author_starts_classic_and_picks_another_design(): void
    {
        $author = User::factory()->create(['username' => 'aziza']);
        $this->actingAs($author);

        $this->getJson('/api/public/authors/aziza')->assertJsonPath('template', 'classic');

        Livewire::test(EditProfile::class)
            ->assertSchemaStateSet(['template' => 'classic'])
            ->fillForm(['template' => 'notebook'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('notebook', $author->fresh()->template);
        $this->getJson('/api/public/authors/aziza')->assertJsonPath('template', 'notebook');
    }

    public function test_only_offered_designs_are_accepted(): void
    {
        $author = User::factory()->create();
        $this->actingAs($author);

        Livewire::test(EditProfile::class)
            ->fillForm(['template' => 'neon'])
            ->call('save')
            ->assertHasFormErrors(['template']);
    }

    public function test_the_profile_shows_the_designs(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/profile')
            ->assertOk()
            ->assertSee('Site design')
            ->assertSee('Magazine')
            ->assertSee('Notebook');
    }
}
