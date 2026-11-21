<?php

namespace App\Filament\Resources\Authors\RelationManagers;

use App\Models\Post;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * An author's posts as the admin sees them: read-only, with a link to each
 * one on the site. Authors edit their own posts; the admin does not.
 */
class PostsRelationManager extends RelationManager
{
    protected static string $relationship = 'posts';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->limit(70)
                    ->description(fn (Post $record): string => $record->slug),
                TextColumn::make('post_type')
                    ->label('Type')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state, Post $record): string => $record->isScheduled() ? 'scheduled' : $state)
                    ->color(fn (string $state, Post $record): string => match (true) {
                        $record->isScheduled() => 'warning',
                        $state === 'published' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('source')
                    ->badge()
                    ->colors(['primary' => Post::SOURCE_TELEGRAM, 'gray' => Post::SOURCE_WEB]),
                TextColumn::make('views_count')
                    ->label('Views')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('published_at')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(['draft' => 'Draft', 'published' => 'Published']),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Post $record): string => $record->url(), shouldOpenInNewTab: true)
                    ->visible(fn (Post $record): bool => $record->isPublished() && ! $record->isScheduled()),
            ]);
    }
}
