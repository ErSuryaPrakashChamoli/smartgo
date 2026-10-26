<?php

namespace App\Filament\Clusters\Website\Resources\Posts\Schemas;

use App\Models\Post;
use App\Services\Content\BlockLibrary;
use App\Services\Content\ContentRules;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The article editor. The rules on Post are the authority: a draft may be saved incomplete, and fields
 * marked required must be filled before it can be submitted.
 */
class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Article')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Title')
                            ->required()
                            ->maxLength(120),
                        TextInput::make('slug')
                            ->label('Address')
                            ->prefix(url('/insights').'/')
                            ->required()
                            ->maxLength(150)
                            ->regex(ContentRules::SLUG_PATTERN)
                            ->validationMessages(['regex' => 'Use lowercase letters, numbers and hyphens (for example flexible-workspace-guide).'])
                            ->helperText('If a published article gets a new address, the old address redirects to it once the change is published.'),
                        Textarea::make('excerpt')
                            ->label('Summary')
                            ->markAsRequired()
                            ->maxLength(300)
                            ->rows(2)
                            ->helperText('Shown under the title and in the list of articles. Plain text.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Sections')
                    ->description('Sections appear in the article in this order.')
                    ->schema([
                        Builder::make('blocks')
                            ->hiddenLabel()
                            ->blocks(app(BlockLibrary::class)->editorBlocks(Post::BLOCK_TYPES))
                            ->maxItems(BlockLibrary::MAX_BLOCKS)
                            ->addActionLabel('Add section')
                            ->blockNumbers()
                            ->collapsible(),
                    ]),
            ]);
    }
}
