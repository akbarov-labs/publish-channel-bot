<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

/**
 * The author's own settings: how they appear on the reader site, and the
 * credentials that tie their Telegram channel to their space.
 */
class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->inlineLabel(false)
            ->components([
                Section::make('Public profile')
                    ->description('Shown at the top of your space on the reader site.')
                    ->schema([
                        $this->getNameFormComponent()->helperText('Your name as readers see it.'),
                        TextInput::make('username')
                            ->required()
                            ->minLength(3)
                            ->maxLength(32)
                            ->regex(User::USERNAME_PATTERN)
                            ->rule(Rule::notIn(User::RESERVED_USERNAMES))
                            ->unique(ignoreRecord: true)
                            ->dehydrateStateUsing(fn (?string $state): string => strtolower(trim((string) $state)))
                            ->prefix('https://')
                            ->suffix('.'.$this->frontendHost())
                            ->helperText('Your subdomain. Lowercase letters, digits and hyphens. Changing it breaks existing links.'),
                        Textarea::make('bio')
                            ->rows(3)
                            ->maxLength(300)
                            ->helperText('One or two sentences about what you write.'),
                        FileUpload::make('avatar')
                            ->image()
                            ->avatar()
                            ->imageEditor()
                            ->directory('avatars')
                            ->disk('public'),
                        TextInput::make('telegram_channel')
                            ->label('Telegram channel')
                            ->placeholder('@yourchannel')
                            ->maxLength(255)
                            ->helperText('Linked from your space so readers can follow the channel.'),
                        Select::make('locale')
                            ->label('Site language')
                            ->options(User::LOCALES)
                            ->in(array_keys(User::LOCALES))
                            ->default('uz')
                            ->required()
                            ->selectablePlaceholder(false)
                            ->helperText('The language of buttons, labels and dates on your space. Your posts stay in the language you write them in.'),
                    ]),

                Section::make('Site design')
                    ->description('How your space looks to readers. You can change it any time; posts stay as they are.')
                    ->schema([
                        Radio::make('template')
                            ->hiddenLabel()
                            ->options(array_map(fn (array $template) => $template[0], User::TEMPLATES))
                            ->descriptions(self::templateDescriptions())
                            // Premium designs need Premium. One already picked
                            // stays selectable, so a profile whose Premium ended
                            // can still be saved; readers see Classic meanwhile.
                            ->disableOptionWhen(fn (string $value): bool => $this->lacksPremiumFor($value))
                            ->in(fn (): array => array_values(array_filter(
                                array_keys(User::TEMPLATES),
                                fn (string $key): bool => ! $this->lacksPremiumFor($key),
                            )))
                            ->default('classic')
                            ->required()
                            ->helperText(fn (): ?string => $this->getUser()->isPremium()
                                ? null
                                : 'Midnight and Gazette come with Premium: send /premium to the bot.'),
                    ]),

                Section::make('Account')
                    ->schema([
                        $this->getEmailFormComponent(),
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                        $this->getCurrentPasswordFormComponent(),
                    ]),

                Section::make('Telegram bot')
                    ->description('Filled in by the bot when you send it /connect. You only need to touch these for a blog the bot cannot reach on its own.')
                    ->schema([
                        TextInput::make('webhook_url')
                            ->label('Webhook URL')
                            ->url()
                            ->maxLength(255),
                        TextInput::make('webhook_secret')
                            ->label('Webhook secret')
                            ->password()
                            ->revealable()
                            ->maxLength(255),
                    ])
                    ->collapsible(),
            ]);
    }

    /** A Premium design, without Premium, that is not already their pick. */
    private function lacksPremiumFor(string $template): bool
    {
        $user = $this->getUser();

        return in_array($template, User::PREMIUM_TEMPLATES, true)
            && ! $user->isPremium()
            && $user->template !== $template;
    }

    /** @return array<string, string> */
    private static function templateDescriptions(): array
    {
        $descriptions = [];
        foreach (User::TEMPLATES as $key => [, $description]) {
            $descriptions[$key] = in_array($key, User::PREMIUM_TEMPLATES, true) ? "{$description} Premium." : $description;
        }

        return $descriptions;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewSite')
                ->label('View my site')
                ->color('gray')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (): string => $this->getUser()->siteUrl(), shouldOpenInNewTab: true)
                ->visible(fn (): bool => filled($this->getUser()->username)),
            Action::make('generateBotToken')
                ->label('Generate bot token')
                ->icon('heroicon-o-key')
                ->requiresConfirmation()
                ->modalDescription('A new token replaces the old one. A bot still using the old token stops syncing until you /connect again.')
                ->action(function (): void {
                    $token = $this->getUser()->issueBotToken();

                    Notification::make()
                        ->title('Your bot token')
                        ->body("Copy it now; it is not shown again.\n\n{$token}")
                        ->success()
                        ->persistent()
                        ->send();
                }),
        ];
    }

    private function frontendHost(): string
    {
        return parse_url((string) config('app.frontend_url'), PHP_URL_HOST) ?: 'localhost';
    }
}
