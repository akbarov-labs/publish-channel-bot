<?php

namespace App\Filament\Resources\Authors\RelationManagers;

use App\Models\Payment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Every period of Premium an author got, newest first: what they paid in
 * Stars, or what the admin granted and why. Read-only; Grant Premium adds rows.
 */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Premium payments';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('provider')
                    ->label('How')
                    ->badge()
                    ->formatStateUsing(fn (Payment $record): string => match (true) {
                        $record->provider === Payment::PROVIDER_STARS && $record->is_recurring => 'Stars, renewal',
                        default => Payment::PROVIDERS[$record->provider] ?? $record->provider,
                    })
                    ->color(fn (string $state): string => $state === Payment::PROVIDER_STARS ? 'warning' : 'gray'),
                TextColumn::make('amount')
                    ->formatStateUsing(fn (Payment $record): string => match (true) {
                        $record->amount === 0 => '—',
                        $record->currency === 'XTR' => number_format($record->amount).' ⭐',
                        default => trim(number_format($record->amount).' '.$record->currency),
                    }),
                TextColumn::make('premium_until')
                    ->label('Premium until')
                    ->dateTime('d M Y'),
                TextColumn::make('note')
                    ->description(fn (Payment $record): ?string => $record->grantedBy ? 'By '.$record->grantedBy->name : null)
                    ->placeholder('—')
                    ->limit(60),
                TextColumn::make('provider_charge_id')
                    ->label('Telegram charge id')
                    ->copyable()
                    ->limit(16)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
