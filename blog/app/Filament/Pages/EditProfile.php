<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\CustomDomain;
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
                            ->descriptions(array_map(fn (array $template) => $template[1], User::TEMPLATES))
                            ->in(array_keys(User::TEMPLATES))
                            ->default('classic')
                            ->required(),
                    ]),

                Section::make('Your own domain')
                    ->key('domain')
                    ->description('With Premium, your space can live at an address of your own, like blog.yourname.uz.')
                    ->visible(fn (): bool => CustomDomain::enabled())
                    ->schema([
                        TextInput::make('custom_domain')
                            ->label('Domain')
                            ->placeholder('blog.yourname.uz')
                            ->maxLength(253)
                            // Without Premium it stays as it is: a lapsed
                            // author's domain is kept for when it comes back.
                            ->disabled(fn (): bool => ! $this->getUser()->isPremium())
                            ->dehydrateStateUsing(fn (?string $state): ?string => CustomDomain::normalize($state))
                            ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                $host = CustomDomain::normalize(is_string($value) ? $value : null);
                                if ($host === null) {
                                    return;
                                }
                                if ($problem = CustomDomain::problem($host)) {
                                    $fail($problem);

                                    return;
                                }
                                if (User::where('custom_domain', $host)->whereKeyNot($this->getUser()->getKey())->exists()) {
                                    $fail('Another author already uses this domain.');
                                }
                            })
                            ->helperText(fn (): string => $this->domainHelp()),
                    ])
                    ->footer([
                        Action::make('checkDomain')
                            ->label('Check DNS')
                            ->icon('heroicon-o-arrow-path')
                            ->color('gray')
                            ->visible(fn (): bool => $this->getUser()->isPremium() && filled($this->getUser()->custom_domain))
                            ->action(fn () => $this->checkDomain()),
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

    /** Where the saved domain stands, and the DNS record it needs. */
    private function domainHelp(): string
    {
        $user = $this->getUser();
        $target = CustomDomain::target();
        $record = "Add a CNAME record for it pointing to {$target}";
        $record .= CustomDomain::ip() !== null
            ? ', or, for a whole domain like yourname.uz, an A record pointing to '.CustomDomain::ip().'.'
            : '.';

        return match (true) {
            ! $user->isPremium() && filled($user->custom_domain) => "Premium has ended, so readers of {$user->custom_domain} are sent to your subdomain. It comes back with Premium: send /premium to the bot.",
            ! $user->isPremium() => 'Comes with Premium: send /premium to the bot.',
            blank($user->custom_domain) => "Type your domain and save. Then: {$record}",
            $user->custom_domain_verified_at !== null => "✓ Live: readers find your space at https://{$user->custom_domain}, and your subdomain sends them there.",
            default => "{$record} Then press Check DNS. Changes can take up to an hour to reach everyone.",
        };
    }

    /** Checks the saved domain, so the address on screen is the one checked. */
    private function checkDomain(): void
    {
        $user = $this->getUser();
        $domain = (string) $user->custom_domain;

        if (! CustomDomain::pointsHere($domain)) {
            Notification::make()
                ->title("{$domain} does not point here yet")
                ->body('Check the DNS record, or wait a little: changes can take up to an hour to arrive.')
                ->warning()
                ->send();

            return;
        }

        $user->forceFill(['custom_domain_verified_at' => now()])->save();

        Notification::make()
            ->title("{$domain} is live")
            ->body('The first visit takes a few seconds while its certificate is issued.')
            ->success()
            ->send();
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
