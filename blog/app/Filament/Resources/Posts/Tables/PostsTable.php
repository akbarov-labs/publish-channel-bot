<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Models\Post;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover_image')
                    ->label('')
                    ->circular(),
                TextColumn::make('title')
                    ->searchable()
                    ->limit(60)
                    ->description(fn (Post $record): string => $record->slug),
                TextColumn::make('post_type')
                    ->label('Type')
                    ->badge()
                    ->colors([
                        'gray' => Post::TYPE_ARTICLE,
                        'info' => Post::TYPE_GALLERY,
                        'warning' => Post::TYPE_VIDEO,
                    ]),
                TextColumn::make('status')
                    ->badge()
                    ->colors(['success' => 'published', 'gray' => 'draft']),
                TextColumn::make('source')
                    ->badge()
                    ->colors(['primary' => Post::SOURCE_TELEGRAM, 'gray' => Post::SOURCE_WEB]),
                TextColumn::make('media_count')
                    ->counts('media')
                    ->label('Images')
                    ->toggleable(),
                IconColumn::make('telegram_notified_at')
                    ->label('In channel')
                    ->boolean()
                    ->tooltip('Whether this post has been announced in the Telegram channel'),
                TextColumn::make('published_at')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(['draft' => 'Draft', 'published' => 'Published']),
                SelectFilter::make('post_type')
                    ->label('Type')
                    ->options([
                        Post::TYPE_ARTICLE => 'Article',
                        Post::TYPE_GALLERY => 'Gallery',
                        Post::TYPE_VIDEO => 'Video',
                    ]),
                SelectFilter::make('source')
                    ->options([Post::SOURCE_WEB => 'Written here', Post::SOURCE_TELEGRAM => 'From Telegram']),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Post $record): string => $record->url(), shouldOpenInNewTab: true)
                    ->visible(fn (Post $record): bool => $record->isPublished()),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
