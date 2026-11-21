<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A year of the signed-in author's views as CSV, one row per post per day
 * it was read, newest first. Comes with Premium; linked from the views chart.
 */
class StatsExportController extends Controller
{
    public const DAYS = 365;

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect('/admin/login');
        }

        abort_unless($user->isPremium() && ! $user->isBlocked(), 403, 'The CSV export comes with Premium.');

        $since = now()->subDays(self::DAYS - 1)->toDateString();
        $rows = DB::table('post_views')
            ->join('posts', 'posts.id', '=', 'post_views.post_id')
            ->where('posts.user_id', $user->id)
            ->where('post_views.day', '>=', $since)
            ->orderByDesc('post_views.day')
            ->orderByDesc('post_views.views')
            ->select(['post_views.day', 'posts.title', 'posts.slug', 'post_views.views']);

        $site = $user->siteUrl();
        $filename = "views-{$user->username}-".now()->toDateString().'.csv';

        return new StreamedResponse(function () use ($rows, $site): void {
            $out = fopen('php://output', 'w');
            // A byte-order mark, so Excel reads Cyrillic and oʻ as UTF-8.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['date', 'post', 'url', 'views'], escape: '');

            foreach ($rows->lazy(500) as $row) {
                fputcsv($out, [
                    substr((string) $row->day, 0, 10),
                    self::safeCell((string) $row->title),
                    $site.'/posts/'.$row->slug,
                    (int) $row->views,
                ], escape: '');
            }

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** A title starting with = + - @ would run as a formula in a spreadsheet. */
    private static function safeCell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
