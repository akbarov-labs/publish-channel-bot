<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Caddy's on-demand TLS "ask" endpoint: may Caddy get a certificate for this
 * host? Authors live at <username>.<reader domain>, so their certificates are
 * issued the first time someone opens their space, not up front. Without this
 * check anyone could make Caddy request certificates for random subdomains
 * and spend the domain's Let's Encrypt rate limit.
 *
 * Answers 200 for the reader domain's own author hosts. Production and staging
 * share one edge Caddy and therefore one ask URL, which lands on either
 * stack's blog, so a host under the other stack's domain is passed to that
 * stack once (TLS_ASK_PEER_URL) and its answer returned.
 */
class TlsAskController extends Controller
{
    private const FORWARDED_HEADER = 'X-Tls-Ask-Forwarded';

    public function __invoke(Request $request): Response
    {
        $domain = strtolower(trim((string) $request->query('domain')));

        if ($domain === '') {
            return $this->deny();
        }

        $root = parse_url((string) config('app.frontend_url'), PHP_URL_HOST);

        if (is_string($root) && $root !== '' && str_ends_with($domain, '.'.$root)) {
            $label = substr($domain, 0, -strlen($root) - 1);

            return $this->isAuthorHost($label) ? response('', 200) : $this->deny();
        }

        return $this->askPeer($request, $domain);
    }

    private function isAuthorHost(string $label): bool
    {
        if ($label === 'www') {
            return true;
        }

        if (! preg_match(User::USERNAME_PATTERN, $label) || in_array($label, User::RESERVED_USERNAMES, true)) {
            return false;
        }

        return User::active()->where('username', $label)->exists();
    }

    private function askPeer(Request $request, string $domain): Response
    {
        $peer = (string) config('services.tls_ask.peer_url');

        // One hop only: a peer never forwards what it was forwarded.
        if ($peer === '' || $request->hasHeader(self::FORWARDED_HEADER)) {
            return $this->deny();
        }

        try {
            $answer = Http::timeout(3)
                ->withHeaders([self::FORWARDED_HEADER => '1'])
                ->get($peer, ['domain' => $domain]);
        } catch (Throwable) {
            return $this->deny();
        }

        return $answer->successful() ? response('', 200) : $this->deny();
    }

    private function deny(): Response
    {
        return response('', 404);
    }
}
