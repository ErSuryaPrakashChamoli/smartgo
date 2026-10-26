<?php

namespace App\Filament\Clusters\Website\Resources\Pages\Schemas;

use App\Models\MediaAsset;
use App\Services\Content\BlockLibrary;
use App\Services\Content\PagePayload;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * The page editor form. The server-side rules in PagePayload and the blocks are the authority: a draft
 * may be saved incomplete, and fields marked required must be filled before it can be submitted.
 */
class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Page')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Page title')
                            ->required()
                            ->maxLength(120),
                        TextInput::make('slug')
                            ->label('Page address')
                            ->prefix(url('/').'/')
                            ->required()
                            ->maxLength(150)
                            ->regex(PagePayload::SLUG_PATTERN)
                            ->validationMessages(['regex' => 'Use lowercase letters, numbers and hyphens, with / between parts (for example legal/privacy).'])
                            ->helperText('If a published page gets a new address, the old address redirects to it automatically once the change is published (BR-CMS-6).'),
                        Select::make('template')
                            ->label('Layout')
                            ->options(['standard' => 'Standard page', 'legal' => 'Legal page'])
                            ->default('standard')
                            ->selectablePlaceholder(false)
                            ->required()
                            ->live()
                            ->helperText('Legal pages hold text sections only and show the date they were last updated.'),
                        TextInput::make('legal_review_reference')
                            ->label('Legal review reference')
                            ->maxLength(120)
                            ->visible(fn (Get $get): bool => $get('template') === 'legal')
                            ->markAsRequired()
                            ->helperText('The reference of the review of this exact text by counsel, e.g. a ticket or email reference. Each new version needs its own (D-15).'),
                    ]),
                Section::make('Sections')
                    ->description('Sections appear on the page in this order. Images arrive with the media library.')
                    ->schema([
                        Builder::make('blocks')
                            ->hiddenLabel()
                            ->blocks(app(BlockLibrary::class)->editorBlocks())
                            ->maxItems(BlockLibrary::MAX_BLOCKS)
                            ->addActionLabel('Add section')
                            ->blockNumbers()
                            ->collapsible(),
                    ]),
                Section::make('Search engines')
                    ->description('Published together with the page. Leave a field empty to use the page title or the introduction.')
                    ->statePath('seo')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Title in search results')
                            ->maxLength(60)
                            ->helperText('Up to 60 characters. Plain text.'),
                        Toggle::make('indexable')
                            ->label('Allow search engines to index this page')
                            ->default(true)
                            ->inline(false),
                        Textarea::make('description')
                            ->label('Description in search results')
                            ->maxLength(160)
                            ->rows(2)
                            ->helperText('Up to 160 characters. Plain text.')
                            ->columnSpanFull(),
                        Select::make('image_asset_id')
                            ->label('Image when the page is shared')
                            ->options(fn (): array => MediaAsset::query()->latest()->limit(500)->pluck('title', 'id')->all())
                            ->searchable()
                            ->helperText('From the media library. Shown by social networks and messaging apps.'),
                    ]),
            ]);
    }
}
