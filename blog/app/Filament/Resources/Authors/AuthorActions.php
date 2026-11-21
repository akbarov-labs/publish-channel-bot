<?php

namespace App\Filament\Resources\Authors;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
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

    /**
     * A gift, a comp or a trial: N more days of Premium, counted from the end
     * of the Premium they already have. Kept as a payment row with no money.
     */
    public static function grantPremium(): Action
    {
        return Action::make('grantPremium')
            ->label('Grant Premium')
            ->icon(Heroicon::OutlinedSparkles)
            ->color('warning')
            ->modalHeading(fn (User $record): string => "Premium for {$record->name}")
            ->modalDescription(fn (User $record): string => $record->isPremium()
                ? 'They have Premium until '.$record->premium_until->format('d M Y').'. The days are added after that.'
                : 'Premium starts now.')
            ->modalSubmitActionLabel('Grant')
            ->schema(self::grantSchema())
            ->action(function (User $record, array $data): void {
                $payment = $record->grantPremium((int) $data['days'], auth()->user(), $data['note'] ?: null);

                Notification::make()
                    ->title("{$record->name} has Premium until ".$payment->premium_until->format('d M Y'))
                    ->success()
                    ->send();
            });
    }

    /** The same for many authors at once: the launch trial, say. */
    public static function grantPremiumToMany(): BulkAction
    {
        return BulkAction::make('grantPremium')
            ->label('Grant Premium')
            ->icon(Heroicon::OutlinedSparkles)
            ->color('warning')
            ->modalHeading('Grant Premium to the selected authors')
            ->modalDescription('Each gets the days after the end of the Premium they already have. Blocked authors are skipped.')
            ->modalSubmitActionLabel('Grant')
            ->schema(self::grantSchema())
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data): void {
                $granted = $records
                    ->reject(fn (User $user): bool => $user->isBlocked())
                    ->each(fn (User $user) => $user->grantPremium((int) $data['days'], auth()->user(), $data['note'] ?: null))
                    ->count();

                Notification::make()
                    ->title($granted.' '.Str::plural('author', $granted).' got '.$data['days'].' days of Premium')
                    ->success()
                    ->send();
            });
    }

    private static function grantSchema(): array
    {
        return [
            TextInput::make('days')
                ->numeric()
                ->integer()
                ->required()
                ->minValue(1)
                ->maxValue(3660)
                ->default(30)
                ->suffix('days'),
            TextInput::make('note')
                ->maxLength(255)
                ->placeholder('Launch trial, thank-you, refund…')
                ->helperText('Only you see it, in their payments.'),
        ];
    }
}
