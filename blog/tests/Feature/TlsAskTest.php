<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TlsAskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.frontend_url' => 'https://example.test']);
        User::factory()->create(['username' => 'aziza']);
    }

    public function test_an_existing_authors_host_may_get_a_certificate(): void
    {
        $this->get('/api/tls/allowed?domain=aziza.example.test')->assertOk();
        $this->get('/api/tls/allowed?domain=AZIZA.example.test')->assertOk();
        $this->get('/api/tls/allowed?domain=www.example.test')->assertOk();
    }

    public function test_unknown_reserved_and_foreign_hosts_are_refused(): void
    {
        Http::preventStrayRequests();

        $this->get('/api/tls/allowed?domain=nobody.example.test')->assertNotFound();
        $this->get('/api/tls/allowed?domain=admin.example.test')->assertNotFound();
        $this->get('/api/tls/allowed?domain=a.b.example.test')->assertNotFound();
        $this->get('/api/tls/allowed?domain=aziza.evil.test')->assertNotFound();
        $this->get('/api/tls/allowed?domain=example.test')->assertNotFound();
        $this->get('/api/tls/allowed')->assertNotFound();
    }

    public function test_a_host_of_the_other_stack_is_asked_of_that_stack_once(): void
    {
        config(['services.tls_ask.peer_url' => 'http://peer/api/tls/allowed']);
        Http::fake([
            'peer/api/tls/allowed?domain=dmitry.staging.test' => Http::response('', 200),
            '*' => Http::response('', 404),
        ]);

        $this->get('/api/tls/allowed?domain=dmitry.staging.test')->assertOk();
        $this->get('/api/tls/allowed?domain=random.staging.test')->assertNotFound();

        Http::assertSent(fn (Request $request) => $request->hasHeader('X-Tls-Ask-Forwarded', '1'));
    }

    public function test_a_forwarded_question_is_never_forwarded_again(): void
    {
        config(['services.tls_ask.peer_url' => 'http://peer/api/tls/allowed']);
        Http::fake();

        $this->get('/api/tls/allowed?domain=dmitry.staging.test', ['X-Tls-Ask-Forwarded' => '1'])
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_the_blog_root_sends_people_to_the_admin(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }
}
