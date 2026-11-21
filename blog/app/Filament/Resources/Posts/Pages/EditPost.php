<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Shows the post as readers will see it, published or not. Anyone
            // with the link can open it, so it can go to an editor or a friend.
            Action::make('preview')
                ->label('Preview')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn (Post $record): string => $record->previewUrl(), shouldOpenInNewTab: true),
            ActionGroup::make([
                Action::make('renewPreview')
                    ->label('New preview link')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->modalDescription('The old preview link stops working. Use this if it reached someone it should not have.')
                    ->action(function (Post $record): void {
                        $url = $record->previewUrl(renew: true);
                        Notification::make()->title('New preview link')->body($url)->success()->persistent()->send();
                    }),
                DeleteAction::make(),
            ]),
        ];
    }

    /** The form's topics, plus any hashtags in the body. */
    protected function afterSave(): void
    {
        $this->record->syncTopics([
            ...($this->data['topics'] ?? []),
            ...\App\Models\Post::hashtagsIn($this->record->html),
        ]);
    }
}
