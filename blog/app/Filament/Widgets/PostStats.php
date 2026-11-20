<?php

namespace App\Filament\Widgets;

use App\Models\Post;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/** The author's numbers at a glance: views, posts, reactions. */
class PostStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    /** Views per day for the author's posts, oldest first, zero-filled. */
    public static function dailyViews(int $userId, int $days): array
    {
        $since = now()->subDays($days - 1)->toDateString();

        $counts = DB::table('post_views')
            ->join('posts', 'posts.id', '=', 'post_views.post_id')
            ->where('posts.user_id', $userId)
            ->where('post_views.day', '>=', $since)
            ->groupBy('post_views.day')
            ->selectRaw('post_views.day as day, sum(post_views.views) as total')
            ->get()
            ->mapWithKeys(fn ($row) => [substr((string) $row->day, 0, 10) => (int) $row->total]);

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $series[$day] = $counts[$day] ?? 0;
        }

        return $series;
    }

    protected function getStats(): array
    {
        $userId = (int) auth()->id();
        $daily = static::dailyViews($userId, 60);
        $recent = array_sum(array_slice($daily, 30));
        $before = array_sum(array_slice($daily, 0, 30));

        $posts = Post::where('user_id', $userId);
        $published = (clone $posts)->visible()->count();
        $scheduled = (clone $posts)->where('status', 'published')->where('published_at', '>', now())->count();
        $drafts = (clone $posts)->where('status', 'draft')->count();
        $reactions = (clone $posts)->whereNotNull('reactions')->pluck('reactions')
            ->sum(fn ($list) => collect($list)->sum('count'));

        $trend = match (true) {
            $before === 0 && $recent === 0 => 'No views yet',
            $before === 0 => 'New this month',
            default => sprintf('%+d%% on the 30 days before', round(($recent - $before) / $before * 100)),
        };

        return [
            Stat::make('Views, last 30 days', number_format($recent))
                ->description($trend)
                ->chart(array_values(array_slice($daily, 30)))
                ->color($recent >= $before ? 'success' : 'gray'),
            Stat::make('Views, all time', number_format((int) (clone $posts)->sum('views_count'))),
            Stat::make('Published posts', number_format($published))
                ->description(collect([
                    $scheduled ? "{$scheduled} scheduled" : null,
                    $drafts ? "{$drafts} drafts" : null,
                ])->filter()->implode(' · ') ?: null),
            Stat::make('Reactions in the channel', number_format($reactions)),
        ];
    }
}
