<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
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
