<?php

namespace Tests\Feature;

use App\Filament\Resources\PageRequests\Pages\ListPageRequests;
use App\Models\PageRequest;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class PageRequestTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'internal-test-secret';

    private const ALI = [
        'telegram_user_id' => 42,
        'name' => 'Ali Valiyev',
        'telegram_username' => 'ali_writes',
        'language' => 'ru',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['telegram.internal_secret' => self::SECRET]);
    }

    private function report(array $data = self::ALI, string $secret = self::SECRET)
    {
        return $this->withToken($secret)->postJson('/api/internal/page-requests', $data);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    // --- The bot's side ---------------------------------------------------

    public function test_the_route_does_not_exist_without_a_secret(): void
    {
        config(['telegram.internal_secret' => null]);

        $this->report()->assertNotFound();
        $this->assertDatabaseCount('page_requests', 0);
    }

    public function test_a_wrong_secret_is_refused(): void
    {
        $this->report(secret: 'guess')->assertUnauthorized();
        $this->withHeaders([])->postJson('/api/internal/page-requests', self::ALI)->assertUnauthorized();
        $this->assertDatabaseCount('page_requests', 0);
    }

    public function test_a_request_is_recorded(): void
    {
        $this->report()->assertCreated()->assertJson(['status' => 'new', 'times_requested' => 1]);

        $this->assertDatabaseHas('page_requests', [
            'telegram_user_id' => 42,
            'name' => 'Ali Valiyev',
            'telegram_username' => 'ali_writes',
            'language' => 'ru',
            'status' => PageRequest::STATUS_NEW,
        ]);
    }

    public function test_asking_again_counts_instead_of_adding_a_row(): void
    {
        $this->report();
        $this->report([...self::ALI, 'name' => 'Ali V.'])
            ->assertOk()
            ->assertJson(['times_requested' => 2]);

        $this->assertDatabaseCount('page_requests', 1);
        $this->assertSame('Ali V.', PageRequest::first()->name);
    }

    public function test_a_dismissed_request_comes_back_when_they_ask_again(): void
    {
        $this->report();
        PageRequest::first()->update(['status' => PageRequest::STATUS_DISMISSED]);

        $this->report()->assertJson(['status' => PageRequest::STATUS_NEW]);
    }

    public function test_someone_who_is_already_an_author_stays_one(): void
    {
        $this->report();
        PageRequest::first()->update(['status' => PageRequest::STATUS_ACCEPTED, 'user_id' => User::factory()->create()->id]);

        $this->report()->assertJson(['status' => PageRequest::STATUS_ACCEPTED]);
    }

    public function test_the_payload_is_validated(): void
    {
        $this->report(['telegram_user_id' => 'x', 'telegram_username' => 'has space'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['telegram_user_id', 'name', 'telegram_username']);
    }

    // --- The admin's side -------------------------------------------------

    public function test_only_the_admin_sees_page_requests(): void
    {
        PageRequest::record(self::ALI);

        $this->actingAs(User::factory()->create())
            ->get('/admin/page-requests')
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->get('/admin/page-requests')
            ->assertOk()
            ->assertSee('Ali Valiyev')
            ->assertSee('@ali_writes');
    }

    public function test_the_admin_creates_an_author_from_a_request(): void
    {
        $request = PageRequest::record(self::ALI);

        $this->actingAs($this->admin());

        // Name and username are not passed: they come from the prefilled form.
        Livewire::test(ListPageRequests::class)
            ->callAction(TestAction::make('createAuthor')->table($request), data: [
                'email' => 'ali@example.com',
                'password' => 'a-long-password',
            ])
            ->assertHasNoFormErrors();

        $author = User::where('email', 'ali@example.com')->firstOrFail();
        $this->assertSame('ali-writes', $author->username);
        $this->assertSame('ru', $author->locale);
        $this->assertFalse($author->isAdmin());
        $this->assertTrue(Hash::check('a-long-password', $author->password));

        $request->refresh();
        $this->assertSame(PageRequest::STATUS_ACCEPTED, $request->status);
        $this->assertSame($author->id, $request->user_id);
    }

    public function test_create_author_refuses_a_taken_username(): void
    {
        User::factory()->create(['username' => 'taken']);
        $request = PageRequest::record(self::ALI);

        $this->actingAs($this->admin());

        Livewire::test(ListPageRequests::class)
            ->callAction(TestAction::make('createAuthor')->table($request), data: [
                'email' => 'ali@example.com',
                'username' => 'taken',
                'password' => 'a-long-password',
            ])
            ->assertHasFormErrors(['username' => 'unique']);

        $this->assertNull(User::where('email', 'ali@example.com')->first());
    }

    public function test_the_admin_dismisses_a_request(): void
    {
        $request = PageRequest::record(self::ALI);

        $this->actingAs($this->admin());

        Livewire::test(ListPageRequests::class)
            ->callAction(TestAction::make('dismiss')->table($request));

        $this->assertSame(PageRequest::STATUS_DISMISSED, $request->refresh()->status);
    }

    public function test_the_suggested_username_skips_invalid_or_taken_names(): void
    {
        User::factory()->create(['username' => 'ali-writes']);

        $this->assertNull(PageRequest::record(self::ALI)->suggestedUsername());
        $this->assertNull(PageRequest::record([...self::ALI, 'telegram_user_id' => 7, 'telegram_username' => null])->suggestedUsername());
        $this->assertSame('admin-x', PageRequest::record([...self::ALI, 'telegram_user_id' => 8, 'telegram_username' => 'Admin_X'])->suggestedUsername());
    }

    public function test_the_migration_makes_the_oldest_account_the_admin(): void
    {
        $first = User::factory()->create();
        User::factory()->create();

        $migration = require database_path('migrations/2026_11_20_000001_create_page_requests_table.php');
        $migration->down();
        $migration->up();

        $this->assertSame([$first->id], User::where('is_admin', true)->pluck('id')->all());
    }
}
