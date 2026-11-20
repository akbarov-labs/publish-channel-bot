<?php

namespace App\Filament\Resources\PageRequests\Tables;

use App\Models\PageRequest;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PageRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (PageRequest $record): ?string => $record->telegram_username ? '@'.$record->telegram_username : null),
                TextColumn::make('language')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => PageRequest::STATUSES[$state] ?? $state)
                    ->colors([
                        'warning' => PageRequest::STATUS_NEW,
                        'success' => PageRequest::STATUS_ACCEPTED,
                        'gray' => PageRequest::STATUS_DISMISSED,
                    ]),
                TextColumn::make('user.username')
                    ->label('Page')
                    ->url(fn (PageRequest $record): ?string => $record->user?->username ? $record->user->siteUrl() : null, shouldOpenInNewTab: true)
                    ->placeholder('—'),
                TextColumn::make('times_requested')
                    ->label('Asked')
                    ->numeric()
                    ->tooltip('How many times they pressed "Request a page"'),
                TextColumn::make('last_requested_at')
                    ->label('Last asked')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('last_requested_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(PageRequest::STATUSES)
                    ->default(PageRequest::STATUS_NEW),
            ])
            ->recordActions([
                Action::make('message')
                    ->label('Message')
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->url(fn (PageRequest $record): string => $record->chatUrl(), shouldOpenInNewTab: true),
                self::createAuthorAction(),
                Action::make('dismiss')
                    ->label('Dismiss')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('They drop off the list. If they press "Request a page" again, they come back.')
                    ->visible(fn (PageRequest $record): bool => $record->status === PageRequest::STATUS_NEW)
                    ->action(fn (PageRequest $record) => $record->update(['status' => PageRequest::STATUS_DISMISSED]))
                    ->successNotificationTitle('Dismissed'),
            ]);
    }

    /**
     * Makes the author's admin account from the request. The password is shown
     * in the form, prefilled, so it can be copied and sent to them before
     * saving; only its hash is kept.
     */
    private static function createAuthorAction(): Action
    {
        return Action::make('createAuthor')
            ->label('Create author')
            ->icon(Heroicon::OutlinedUserPlus)
            ->color('success')
            ->visible(fn (PageRequest $record): bool => $record->user_id === null)
            ->modalHeading(fn (PageRequest $record): string => "Create an author for {$record->name}")
            ->modalDescription('Copy the email and password before saving, then send them in Telegram with a link to the admin. The password is not shown again.')
            ->modalSubmitActionLabel('Create author')
            ->fillForm(fn (PageRequest $record): array => [
                'name' => $record->name,
                'username' => $record->suggestedUsername(),
                'password' => Str::password(14, symbols: false),
            ])
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    // Explicit: the record here is the page request, not a user.
                    ->unique(table: User::class, column: 'email', ignoreRecord: false)
                    ->helperText('What they sign in with. Telegram does not share emails, so ask them for one.'),
                TextInput::make('username')
                    ->required()
                    ->minLength(3)
                    ->maxLength(32)
                    ->regex(User::USERNAME_PATTERN)
                    ->rule(Rule::notIn(User::RESERVED_USERNAMES))
                    ->unique(table: User::class, column: 'username', ignoreRecord: false)
                    ->dehydrateStateUsing(fn (?string $state): string => strtolower(trim((string) $state)))
                    ->prefix('https://')
                    ->suffix('.'.self::frontendHost())
                    ->helperText('Their subdomain. They can change it later on their Profile.'),
                TextInput::make('password')
                    ->required()
                    ->minLength(8)
                    ->helperText('Generated for you. Copy it now.'),
            ])
            ->action(function (PageRequest $record, array $data): void {
                $author = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'username' => $data['username'],
                    'password' => $data['password'],
                    'locale' => in_array($record->language, array_keys(User::LOCALES), true) ? $record->language : 'uz',
                ]);

                $record->update(['status' => PageRequest::STATUS_ACCEPTED, 'user_id' => $author->id]);

                Notification::make()
                    ->title("{$author->name} can now sign in")
                    ->body("Their page: {$author->siteUrl()}")
                    ->success()
                    ->send();
            });
    }

    private static function frontendHost(): string
    {
        return (string) (parse_url((string) config('app.frontend_url'), PHP_URL_HOST) ?: 'localhost');
    }
}
