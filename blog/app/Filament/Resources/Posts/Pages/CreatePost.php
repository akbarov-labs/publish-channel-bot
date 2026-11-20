<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }

    /** The form's topics, plus any hashtags in the body. */
    protected function afterCreate(): void
    {
        $this->record->syncTopics([
            ...($this->data['topics'] ?? []),
            ...\App\Models\Post::hashtagsIn($this->record->html),
        ]);
    }
}
