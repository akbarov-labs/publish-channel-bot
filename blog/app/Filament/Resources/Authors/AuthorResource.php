<?php

namespace App\Filament\Resources\Authors;

use App\Filament\Resources\Authors\Pages\EditAuthor;
use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Filament\Resources\Authors\Pages\ViewAuthor;
use App\Filament\Resources\Authors\RelationManagers\PostsRelationManager;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Every account on the platform, for the admin only: who they are, their
 * posts, a new password when they forget theirs, and blocking. Accounts are
 * made from Page requests (or make:filament-user), so there is no create page.
 */
class AuthorResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'author';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    /** How many are blocked, next to the menu item. */
    public static function getNavigationBadge(): ?string
    {
        $blocked = User::whereNotNull('blocked_at')->count();

        return $blocked > 0 ? "{$blocked} blocked" : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount([
            'posts',
            'posts as published_posts_count' => fn (Builder $query) => $query->where('status', 'published'),
        ])->withMax('posts as latest_post_at', 'published_at');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->helperText('What they sign in with.'),
            TextInput::make('username')
                ->required()
                ->minLength(3)
                ->maxLength(32)
                ->regex(User::USERNAME_PATTERN)
                ->rule(Rule::notIn(User::RESERVED_USERNAMES))
                ->unique(ignoreRecord: true)
                ->dehydrateStateUsing(fn (?string $state): string => strtolower(trim((string) $state)))
                ->prefix('https://')
                ->suffix('.'.self::frontendHost())
                ->helperText('Their subdomain. Changing it moves their page; old links stop working.'),
            Select::make('locale')
                ->label('Site language')
                ->options(User::LOCALES)
                ->required()
                ->native(false),
        ])->columns(2);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Account')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('name'),
                    TextEntry::make('email')->copyable(),
                    TextEntry::make('status')
                        ->state(fn (User $record): string => self::statusOf($record))
                        ->badge()
                        ->color(fn (string $state): string => self::statusColor($state))
                        ->helperText(fn (User $record): ?string => $record->isBlocked() ? 'Since '.$record->blocked_at->format('d M Y H:i') : null),
                    TextEntry::make('username')
                        ->label('Page')
                        ->formatStateUsing(fn (User $record): string => $record->siteUrl())
                        ->url(fn (User $record): string => $record->siteUrl(), shouldOpenInNewTab: true)
                        ->placeholder('No username yet'),
                    TextEntry::make('telegram_channel')
                        ->label('Channel')
                        ->url(fn (User $record): ?string => $record->telegramUrl(), shouldOpenInNewTab: true)
                        ->placeholder('Not connected'),
                    TextEntry::make('locale')
                        ->label('Site language')
                        ->formatStateUsing(fn (?string $state): string => User::LOCALES[$state] ?? (string) $state),
                    TextEntry::make('published_posts_count')->label('Published posts')->numeric(),
                    TextEntry::make('posts_count')->label('All posts')->numeric(),
                    TextEntry::make('created_at')->label('Joined')->dateTime('d M Y'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar')
                    ->label('')
                    ->circular()
                    ->getStateUsing(fn (User $record): ?string => $record->avatarUrl()),
                TextColumn::make('name')
                    ->searchable(['name', 'email'])
                    ->sortable()
                    ->description(fn (User $record): string => $record->email),
                TextColumn::make('username')
                    ->label('Page')
                    ->searchable()
                    ->url(fn (User $record): ?string => $record->username ? $record->siteUrl() : null, shouldOpenInNewTab: true)
                    ->placeholder('—'),
                TextColumn::make('telegram_channel')
                    ->label('Channel')
                    ->url(fn (User $record): ?string => $record->telegramUrl(), shouldOpenInNewTab: true)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('published_posts_count')
                    ->label('Posts')
                    ->numeric()
                    ->sortable()
                    ->description(function (User $record): ?string {
                        $drafts = $record->posts_count - $record->published_posts_count;

                        return $drafts > 0 ? $drafts.' '.Str::plural('draft', $drafts) : null;
                    }),
                TextColumn::make('latest_post_at')
                    ->label('Last post')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->placeholder('Never'),
                TextColumn::make('status')
                    ->state(fn (User $record): string => self::statusOf($record))
                    ->badge()
                    ->color(fn (string $state): string => self::statusColor($state)),
                TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('blocked')
                    ->label('Status')
                    ->placeholder('Everyone')
                    ->trueLabel('Blocked')
                    ->falseLabel('Active')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('blocked_at'),
                        false: fn (Builder $query) => $query->whereNull('blocked_at'),
                    ),
            ])
            ->recordUrl(fn (User $record): string => static::getUrl('view', ['record' => $record]))
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->label('Details and posts'),
                    EditAction::make(),
                    AuthorActions::setPassword(),
                    AuthorActions::block(),
                    AuthorActions::unblock(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            PostsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuthors::route('/'),
            'view' => ViewAuthor::route('/{record}'),
            'edit' => EditAuthor::route('/{record}/edit'),
        ];
    }

    public static function statusOf(User $user): string
    {
        return match (true) {
            $user->isBlocked() => 'Blocked',
            $user->isAdmin() => 'Admin',
            default => 'Active',
        };
    }

    private static function statusColor(string $status): string
    {
        return match ($status) {
            'Blocked' => 'danger',
            'Admin' => 'primary',
            default => 'success',
        };
    }

    private static function frontendHost(): string
    {
        return (string) (parse_url((string) config('app.frontend_url'), PHP_URL_HOST) ?: 'localhost');
    }
}
