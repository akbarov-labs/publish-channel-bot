<?php

namespace App\Filament\Resources\Authors;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/** What the admin can do to an account, from the list and from its page. */
class AuthorActions
{
    /**
     * A new password, prefilled so it can be copied and sent to the author
     * before saving. Only its hash is kept, as at Create author.
     */
    public static function setPassword(): Action
    {
        return Action::make('setPassword')
            ->label('Set new password')
            ->icon(Heroicon::OutlinedKey)
            ->color('gray')
            ->modalHeading(fn (User $record): string => "New password for {$record->name}")
            ->modalDescription('Copy it before saving and send it to them in Telegram. It is not shown again. Their other open sessions are signed out.')
            ->modalSubmitActionLabel('Save password')
            ->fillForm(fn (): array => ['password' => Str::password(14, symbols: false)])
            ->schema([
                TextInput::make('password')
                    ->required()
                    ->minLength(8)
                    ->maxLength(255)
                    ->helperText('Generated for you. You can type your own instead.'),
            ])
            ->action(function (User $record, array $data): void {
                $record->forceFill([
                    'password' => $data['password'],
                    'remember_token' => Str::random(60),
                ])->save();

                Notification::make()
                    ->title("{$record->name}'s password is changed")
                    ->body("They sign in at ".url('/admin/login')." with {$record->email}.")
                    ->success()
                    ->send();
            });
    }

    public static function block(): Action
    {
        return Action::make('block')
            ->label('Block')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (User $record): string => "Block {$record->name}?")
            ->modalDescription('They are signed out and cannot sign in, posts from their channel stop reaching the site, and their page and posts disappear from it. Nothing is deleted: unblocking brings everything back.')
            ->modalSubmitActionLabel('Block')
            // Never the admin: that would lock the platform out of its own admin.
            ->visible(fn (User $record): bool => ! $record->isBlocked() && ! $record->isAdmin())
            ->action(function (User $record): void {
                $record->block();

                Notification::make()->title("{$record->name} is blocked")->success()->send();
            });
    }

    public static function unblock(): Action
    {
        return Action::make('unblock')
            ->label('Unblock')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(fn (User $record): string => "Unblock {$record->name}?")
            ->modalDescription('They can sign in again, their channel reaches the site again, and their page comes back.')
            ->modalSubmitActionLabel('Unblock')
            ->visible(fn (User $record): bool => $record->isBlocked())
            ->action(function (User $record): void {
                $record->unblock();

                Notification::make()->title("{$record->name} is unblocked")->success()->send();
            });
    }
}
