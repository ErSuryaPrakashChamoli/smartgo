<?php

namespace App\Filament\Clusters\Website\Resources\Pages\Schemas;

use App\Services\Content\BlockLibrary;
use App\Services\Content\PagePayload;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
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
            ]);
    }
}
