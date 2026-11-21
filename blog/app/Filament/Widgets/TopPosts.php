<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

/** The author's most read posts over the last 30 days. */
class TopPosts extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Most read, last 30 days';

    public function table(Table $table): Table
    {
        $since = now()->subDays(29)->toDateString();

        return $table
            ->query(
                Post::query()
                    ->where('user_id', auth()->id())
                    ->visible()
                    ->addSelect([
                        'recent_views' => DB::table('post_views')
                            ->selectRaw('coalesce(sum(views), 0)')
                            ->whereColumn('post_views.post_id', 'posts.id')
                            ->where('day', '>=', $since),
                    ])
                    ->where('views_count', '>', 0)
            )
            ->defaultSort('recent_views', 'desc')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('title')->limit(60)->url(fn (Post $record) => PostResource::getUrl('edit', ['record' => $record])),
                TextColumn::make('recent_views')->label('Last 30 days')->numeric()->sortable(),
                TextColumn::make('views_count')->label('All time')->numeric()->sortable(),
                TextColumn::make('reactions')
                    ->label('Reactions')
                    ->state(fn (Post $record) => collect($record->reactions)->sum('count') ?: null)
                    ->numeric(),
                TextColumn::make('published_at')->date('d M Y')->label('Published'),
            ])
            ->emptyStateHeading('No views yet')
            ->emptyStateDescription('Views appear here once readers open your posts on the site.');
    }
}
