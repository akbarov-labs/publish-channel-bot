<?php

namespace Tests\Feature;

use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Filament\Resources\Authors\Pages\ViewAuthor;
use App\Filament\Resources\Authors\RelationManagers\PaymentsRelationManager;
use App\Models\PageRequest;
use App\Models\Payment;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PremiumTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'internal-test-secret';

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        config([
            'telegram.internal_secret' => self::SECRET,
            'app.frontend_url' => 'https://example.test',
        ]);
        // Whole seconds: the database keeps no fractions.
        $this->freezeSecond();
    }

    private function author(int $telegramId = 42, string $username = 'ali'): User
    {
        $author = User::factory()->create(['username' => $username]);
        PageRequest::create([
            'telegram_user_id' => $telegramId,
            'name' => ucfirst($username),
            'status' => PageRequest::STATUS_ACCEPTED,
            'user_id' => $author->id,
            'times_requested' => 1,
            'last_requested_at' => now(),
        ]);

        return $author;
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['username' => 'boss']);
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    /** A SuccessfulPayment as the bot forwards it. */
    private function payment(array $overrides = []): array
    {
        return $overrides + [
            'telegram_user_id' => 42,
            'telegram_payment_charge_id' => 'stxCharge1',
            'currency' => 'XTR',
            'total_amount' => 250,
            'invoice_payload' => 'premium:42',
            'subscription_expiration_date' => now()->addDays(30)->timestamp,
            'is_recurring' => true,
            'is_first_recurring' => true,
        ];
    }

    private function pay(array $overrides = [], string $secret = self::SECRET)
    {
        return $this->withToken($secret)->postJson('/api/internal/premium/payments', $this->payment($overrides));
    }

    // --- The flag -------------------------------------------------------------

    public function test_premium_lasts_until_premium_until_and_a_few_grace_days(): void
    {
        $author = User::factory()->create();
        $this->assertFalse($author->isPremium());

        $author->forceFill(['premium_until' => now()->addDay()])->save();
        $this->assertTrue($author->isPremium());

        // A renewal that is a day late does not switch anything off.
        $author->forceFill(['premium_until' => now()->subDay()])->save();
        $this->assertTrue($author->isPremium());
        $this->assertTrue(User::premium()->whereKey($author->id)->exists());

        $author->forceFill(['premium_until' => now()->subDays(User::PREMIUM_GRACE_DAYS + 1)])->save();
        $this->assertFalse($author->isPremium());
        $this->assertFalse(User::premium()->whereKey($author->id)->exists());
    }

    public function test_the_reader_api_says_whether_an_author_has_premium_but_not_until_when(): void
    {
        $author = $this->author();
        $author->posts()->create(['title' => 'Hello', 'status' => 'published', 'published_at' => now()->subHour()]);

        $this->getJson('/api/public/authors/ali')->assertOk()->assertJsonPath('premium', false);

        $author->forceFill(['premium_until' => now()->addDays(10)])->save();

        $this->getJson('/api/public/authors/ali')
            ->assertOk()
            ->assertJsonPath('premium', true)
            ->assertJsonMissingPath('premium_until');
    }

    // --- Status, for the bot --------------------------------------------------

    public function test_the_bot_asks_whether_a_telegram_account_is_an_author_and_has_premium(): void
    {
        $author = $this->author();

        $this->withToken(self::SECRET)->postJson('/api/internal/premium/status', ['telegram_user_id' => 42])
            ->assertOk()
            ->assertJson(['author' => 'ali', 'premium' => false, 'premium_until' => null, 'blocked' => false]);

        $author->forceFill(['premium_until' => now()->addDays(5)])->save();

        $this->withToken(self::SECRET)->postJson('/api/internal/premium/status', ['telegram_user_id' => 42])
            ->assertOk()
            ->assertJsonPath('premium', true)
            ->assertJsonPath('premium_until', now()->addDays(5)->toAtomString());
    }

    public function test_status_for_someone_who_is_not_an_author_is_not_found(): void
    {
        // Asked for a page, but no author was made yet.
        PageRequest::record(['telegram_user_id' => 42, 'name' => 'Ali']);

        $this->withToken(self::SECRET)->postJson('/api/internal/premium/status', ['telegram_user_id' => 42])
            ->assertNotFound();
    }

    public function test_status_tells_the_bot_an_author_is_blocked(): void
    {
        $this->author()->block();

        $this->withToken(self::SECRET)->postJson('/api/internal/premium/status', ['telegram_user_id' => 42])
            ->assertOk()
            ->assertJsonPath('blocked', true);
    }

    public function test_the_premium_calls_need_the_internal_secret(): void
    {
        $this->author();

        $this->withToken('wrong')->postJson('/api/internal/premium/status', ['telegram_user_id' => 42])->assertUnauthorized();
        $this->pay(secret: 'wrong')->assertUnauthorized();

        $this->assertSame(0, Payment::count());
    }

    // --- Payments, from the bot -----------------------------------------------

    public function test_a_stars_payment_turns_premium_on_until_the_end_of_the_paid_period(): void
    {
        $author = $this->author();

        $this->pay()->assertCreated()
            ->assertJsonPath('author', 'ali')
            ->assertJsonPath('premium', true)
            ->assertJsonPath('premium_until', now()->addDays(30)->toAtomString());

        $this->assertTrue($author->refresh()->isPremium());

        $payment = Payment::sole();
        $this->assertSame($author->id, $payment->user_id);
        $this->assertSame(Payment::PROVIDER_STARS, $payment->provider);
        $this->assertSame('stxCharge1', $payment->provider_charge_id);
        $this->assertSame(250, $payment->amount);
        $this->assertSame('XTR', $payment->currency);
        $this->assertSame(42, $payment->telegram_user_id);
        $this->assertTrue($payment->is_recurring);
        $this->assertSame('premium:42', $payment->payload['invoice_payload']);
    }

    public function test_the_same_payment_reported_twice_is_counted_once(): void
    {
        $this->author();

        $this->pay()->assertCreated();
        $this->pay()->assertOk()->assertJsonPath('premium', true);

        $this->assertSame(1, Payment::count());
    }

    public function test_a_renewal_moves_premium_to_the_end_of_the_next_period(): void
    {
        $author = $this->author();
        $this->pay()->assertCreated();

        $this->travel(30)->days();

        $this->pay([
            'telegram_payment_charge_id' => 'stxCharge2',
            'subscription_expiration_date' => now()->addDays(30)->timestamp,
            'is_first_recurring' => false,
        ])->assertCreated();

        $this->assertSame(2, Payment::count());
        $this->assertTrue($author->refresh()->premium_until->equalTo(now()->addDays(30)));
    }

    public function test_a_payment_never_cuts_a_longer_gift_short(): void
    {
        $author = $this->author();
        $author->grantPremium(90);

        $this->pay()->assertCreated();

        $this->assertTrue($author->refresh()->premium_until->equalTo(now()->addDays(90)));
    }

    public function test_a_blocked_authors_renewal_is_still_kept(): void
    {
        $author = $this->author();
        $author->block();

        $this->pay()->assertCreated()->assertJsonPath('blocked', true);

        $this->assertSame(1, $author->payments()->count());
    }

    public function test_a_payment_for_someone_who_is_not_an_author_is_refused_and_not_kept(): void
    {
        $this->pay()->assertNotFound();

        $this->assertSame(0, Payment::count());
    }

    public function test_only_star_payments_are_accepted(): void
    {
        $this->author();

        $this->pay(['currency' => 'USD'])->assertUnprocessable()->assertJsonValidationErrors('currency');
        $this->pay(['subscription_expiration_date' => null])->assertUnprocessable();

        $this->assertSame(0, Payment::count());
    }

    // --- The admin ------------------------------------------------------------

    public function test_the_admin_grants_premium_counted_from_the_end_of_what_they_have(): void
    {
        $admin = $this->admin();
        $author = $this->author();

        Livewire::actingAs($admin)
            ->test(ListAuthors::class)
            ->callAction(TestAction::make('grantPremium')->table($author), ['days' => 30, 'note' => 'Launch trial'])
            ->assertHasNoFormErrors();

        $this->assertTrue($author->refresh()->premium_until->equalTo(now()->addDays(30)));

        Livewire::actingAs($admin)
            ->test(ViewAuthor::class, ['record' => $author->getRouteKey()])
            ->callAction('grantPremium', ['days' => 10]);

        $this->assertTrue($author->refresh()->premium_until->equalTo(now()->addDays(40)));

        $gift = $author->payments()->oldest('id')->first();
        $this->assertSame(Payment::PROVIDER_MANUAL, $gift->provider);
        $this->assertSame(0, $gift->amount);
        $this->assertSame($admin->id, $gift->granted_by);
        $this->assertSame('Launch trial', $gift->note);

        Livewire::actingAs($admin)
            ->test(PaymentsRelationManager::class, ['ownerRecord' => $author, 'pageClass' => ViewAuthor::class])
            ->assertOk()
            ->assertSee('Launch trial')
            ->assertSee('Granted by the admin');
    }

    public function test_an_ended_premium_starts_again_from_today(): void
    {
        $author = User::factory()->create(['premium_until' => now()->subMonths(2)]);

        $author->grantPremium(30);

        $this->assertTrue($author->refresh()->premium_until->equalTo(now()->addDays(30)));
    }

    public function test_the_admin_grants_premium_to_many_authors_at_once_skipping_blocked_ones(): void
    {
        $admin = $this->admin();
        $ali = $this->author(42, 'ali');
        $vali = $this->author(43, 'vali');
        $blocked = $this->author(44, 'gani');
        $blocked->block();

        Livewire::actingAs($admin)
            ->test(ListAuthors::class)
            ->selectTableRecords([$ali, $vali, $blocked])
            ->callAction(TestAction::make('grantPremium')->table()->bulk(), ['days' => 30]);

        $this->assertTrue($ali->refresh()->isPremium());
        $this->assertTrue($vali->refresh()->isPremium());
        $this->assertFalse($blocked->refresh()->isPremium());
        $this->assertSame(0, $blocked->payments()->count());
    }

    public function test_the_authors_list_shows_and_filters_by_premium(): void
    {
        $admin = $this->admin();
        $paying = $this->author(42, 'ali');
        $paying->grantPremium(30);
        $free = $this->author(43, 'vali');

        Livewire::actingAs($admin)
            ->test(ListAuthors::class)
            ->filterTable('premium', true)
            ->assertCanSeeTableRecords([$paying])
            ->assertCanNotSeeTableRecords([$free])
            ->filterTable('premium', false)
            ->assertCanSeeTableRecords([$free])
            ->assertCanNotSeeTableRecords([$paying]);
    }

    public function test_only_the_admin_can_grant_premium(): void
    {
        $author = $this->author();

        $this->actingAs($author)->get('/admin/authors')->assertForbidden();
        $this->assertFalse($author->refresh()->isPremium());
    }
}
