<?php

namespace App\Filament\Resources\PageRequests;

use App\Filament\Resources\PageRequests\Pages\ListPageRequests;
use App\Filament\Resources\PageRequests\Tables\PageRequestsTable;
use App\Models\PageRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Channel owners waiting for a page. Only the admin sees this; authors never
 * do. Rows are written by the bot, so there is no create or edit form: the
 * table's actions are the whole workflow.
 */
class PageRequestResource extends Resource
{
    protected static ?string $model = PageRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    /** How many are waiting, next to the menu item. */
    public static function getNavigationBadge(): ?string
    {
        $waiting = PageRequest::where('status', PageRequest::STATUS_NEW)->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function table(Table $table): Table
    {
        return PageRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPageRequests::route('/'),
        ];
    }
}
