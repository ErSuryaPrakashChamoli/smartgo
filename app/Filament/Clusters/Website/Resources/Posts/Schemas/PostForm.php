<?php

namespace App\Filament\Clusters\Website\Resources\Posts\Schemas;

use App\Models\Post;
use App\Models\PostTopic;
use App\Services\Content\BlockLibrary;
use App\Services\Content\ContentRules;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Select;
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
                        Select::make('topic_ids')
                            ->label('Topics')
                            ->multiple()
                            ->options(fn (): array => PostTopic::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->maxItems(Post::MAX_TOPICS)
                            ->helperText('Up to '.Post::MAX_TOPICS.'. Once this version is published, they are shown with the article, and the article is listed on each topic\'s page. Topics are kept under Website → Insight topics.')
                            ->visible(fn (): bool => PostTopic::isAvailable())
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
