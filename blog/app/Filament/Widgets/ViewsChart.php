<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/** Views of the author's posts on the site, day by day. */
class ViewsChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Views on your site';

    protected ?string $maxHeight = '260px';

    public ?string $filter = '30';

    protected function getFilters(): ?array
    {
        $filters = ['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days'];

        // A year of history comes with Premium.
        return $this->hasPremium() ? $filters + ['365' => 'Last year'] : $filters;
    }

    public function getDescription(): string|Htmlable|null
    {
        if (! $this->hasPremium()) {
            return 'Each reader counts once per visit. A year of history and a CSV come with Premium.';
        }

        return new HtmlString(
            'Each reader counts once per visit. <a href="'.e(url('/admin/stats.csv')).'" class="underline">Download a year as CSV</a>'
        );
    }

    protected function getData(): array
    {
        $days = array_key_exists((string) $this->filter, $this->getFilters()) ? (int) $this->filter : 30;
        $daily = PostStats::dailyViews((int) auth()->id(), $days);

        return [
            'datasets' => [[
                'label' => 'Views',
                'data' => array_values($daily),
                'fill' => true,
                'tension' => 0.3,
            ]],
            'labels' => array_map(fn (string $day) => Carbon::parse($day)->format('d M'), array_keys($daily)),
        ];
    }

    private function hasPremium(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isPremium();
    }

    protected function getType(): string
    {
        return 'line';
    }
}
