<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/** Views of the author's posts on the site, day by day. */
class ViewsChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Views on your site';

    protected ?string $description = 'Each reader counts once per visit.';

    protected ?string $maxHeight = '260px';

    public ?string $filter = '30';

    protected function getFilters(): ?array
    {
        return ['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days'];
    }

    protected function getData(): array
    {
        $daily = PostStats::dailyViews((int) auth()->id(), (int) ($this->filter ?: 30));

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

    protected function getType(): string
    {
        return 'line';
    }
}
