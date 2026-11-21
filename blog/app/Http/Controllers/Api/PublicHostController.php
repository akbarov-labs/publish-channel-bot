<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\CustomDomain;
use Illuminate\Http\JsonResponse;

/**
 * Which author a host belongs to, for the reader site's proxy: their
 * subdomain, or a domain of their own. custom_domain is where the space
 * lives now, so the proxy sends a subdomain visit there, and an own domain
 * whose Premium ended back to the subdomain.
 */
class PublicHostController extends Controller
{
    public function __invoke(string $host): JsonResponse
    {
        $host = strtolower($host);
        $root = CustomDomain::target();

        $author = str_ends_with($host, '.'.$root)
            ? User::active()->where('username', substr($host, 0, -strlen($root) - 1))->first()
            : (CustomDomain::enabled()
                ? User::active()->where('custom_domain', $host)->whereNotNull('custom_domain_verified_at')->first()
                : null);

        if ($author === null) {
            return response()->json(['message' => 'No author has this host.'], 404);
        }

        return response()->json([
            'username' => $author->username,
            'custom_domain' => $author->activeCustomDomain(),
        ]);
    }
}
