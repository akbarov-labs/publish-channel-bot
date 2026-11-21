<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Models\Post;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Content')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $state, callable $set) => $set('slug', Str::slug($state))),
                        TextInput::make('slug')
                            ->required()
                            // Unique within this author's space only.
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('user_id', auth()->id()))
                            ->helperText('Used in the public URL. Changing it breaks existing links.'),
                        RichEditor::make('html')
                            ->label('Body')
                            ->columnSpanFull(),
                        Textarea::make('excerpt')
                            ->rows(2)
                            ->maxLength(280)
                            ->helperText('Shown in listings and in the Telegram announcement.')
                            ->columnSpanFull(),
                        // Saved by the page (syncTopics), since topics are a relation.
                        TagsInput::make('topics')
                            ->placeholder('travel')
                            ->helperText('Readers can open every post on a topic. Hashtags in the body (#travel) are added on their own.')
                            ->afterStateHydrated(fn (TagsInput $component, ?Post $record) => $component->state($record?->tags->pluck('name')->all() ?? []))
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Media')
                    ->description('A gallery shows photos as a carousel; an audio post plays its tracks; files are listed for download. Albums posted in the channel land here in order.')
                    ->schema([
                        Repeater::make('media')
                            ->relationship()
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('url')->required()->url()->columnSpan(2),
                                Select::make('type')
                                    ->options(['photo' => 'Photo', 'video' => 'Video', 'audio' => 'Audio', 'document' => 'File'])
                                    ->default('photo')
                                    ->required(),
                            ])
                            ->columns(3)
                            ->orderColumn('position')
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['url'] ?? null),
                    ])
                    ->visible(fn (callable $get) => in_array($get('post_type'), [Post::TYPE_GALLERY, Post::TYPE_AUDIO], true))
                    ->collapsed(),

                Section::make('Publishing')
                    ->schema([
                        Select::make('post_type')
                            ->label('Type')
                            ->options(fn (?Post $record): array => [
                                Post::TYPE_ARTICLE => 'Article',
                                Post::TYPE_GALLERY => 'Gallery (carousel)',
                                Post::TYPE_VIDEO => 'Video',
                                Post::TYPE_AUDIO => 'Audio',
                                // Polls come from the channel; they are not made here.
                                ...($record?->post_type === Post::TYPE_POLL ? [Post::TYPE_POLL => 'Poll'] : []),
                            ])
                            ->default(Post::TYPE_ARTICLE)
                            ->live()
                            ->required(),
                        Select::make('status')
                            ->options(['draft' => 'Draft', 'published' => 'Published'])
                            ->default('draft')
                            ->required()
                            ->helperText('Publishing announces the post in the connected Telegram channel.'),
                        DateTimePicker::make('published_at')
                            ->label('Publish at')
                            ->seconds(false)
                            ->helperText('Leave empty to publish now. A future time schedules the post: it appears on your site and in the channel at that time.'),
                        Toggle::make('pinned_at')
                            ->label('Pin to the top of my page')
                            ->helperText('One post can be pinned at a time; pinning this one unpins the other.')
                            ->formatStateUsing(fn ($state): bool => filled($state))
                            ->dehydrateStateUsing(fn ($state, $record) => $state ? ($record?->pinned_at ?? now()) : null),
                        Select::make('series_id')
                            ->label('Series')
                            ->options(fn () => auth()->user()->series()->orderBy('title')->pluck('title', 'id'))
                            ->placeholder('Not in a series')
                            ->helperText('Make series under Series in the menu.'),
                        TextInput::make('series_position')
                            ->label('Part number')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(9999)
                            ->helperText('Leave empty to order parts by date.')
                            ->visible(fn (callable $get) => filled($get('series_id'))),
                        FileUpload::make('cover_image')
                            ->image()
                            ->directory('posts')
                            ->disk('public'),
                    ])
                    ->columns(2),

                Section::make('Telegram')
                    ->description('Filled in automatically by the sync bot.')
                    ->schema([
                        TextInput::make('source')
                            ->disabled()
                            ->default(Post::SOURCE_WEB)
                            ->helperText('Posts marked "telegram" are never sent back to the channel.'),
                        TextInput::make('external_id')->disabled(),
                        TextInput::make('telegraph_url')->url()->disabled(),
                        DateTimePicker::make('telegram_notified_at')
                            ->label('Announced at')
                            ->disabled(),
                    ])
                    ->columns(2)
                    ->collapsed()
                    ->hiddenOn('create'),
            ]);
    }
}
