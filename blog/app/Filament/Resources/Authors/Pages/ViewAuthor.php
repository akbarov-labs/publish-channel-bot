<?php

namespace App\Filament\Resources\Authors\Pages;

use App\Filament\Resources\Authors\AuthorActions;
use App\Filament\Resources\Authors\AuthorResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/** An author's account, with their posts underneath. */
class ViewAuthor extends ViewRecord
{
    protected static string $resource = AuthorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->color('gray'),
            AuthorActions::setPassword(),
            AuthorActions::grantPremium(),
            AuthorActions::unblock(),
            AuthorActions::block(),
        ];
    }
}
