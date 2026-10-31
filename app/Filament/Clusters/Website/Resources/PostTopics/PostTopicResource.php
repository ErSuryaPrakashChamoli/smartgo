<?php

namespace App\Filament\Clusters\Website\Resources\PostTopics;

use App\Filament\Clusters\Website\Resources\PostTopics\Pages\CreatePostTopic;
use App\Filament\Clusters\Website\Resources\PostTopics\Pages\EditPostTopic;
use App\Filament\Clusters\Website\Resources\PostTopics\Pages\ListPostTopics;
use App\Filament\Clusters\Website\WebsiteCluster;
use App\Models\PostTopic;
use App\Services\Content\ContentRules;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * The list of insight topics (IA /insights/topic/{slug}). Nothing is preset. Articles take up to three
 * topics in their versions; a topic's page lists its published articles and exists only while it has at
 * least one. A topic that a published article shows keeps its name and address; one that any article uses
 * cannot be deleted (PostTopicPolicy).
 */
class PostTopicResource extends Resource
{
    protected static ?string $model = PostTopic::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $cluster = WebsiteCluster::class;

    protected static ?string $navigationParentItem = 'Insights';

    protected static ?string $navigationLabel = 'Topics';

    protected static ?string $modelLabel = 'insight topic';

    protected static ?string $slug = 'insight-topics';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isGloballySearchable = false;

    /**
     * Hidden on a database not migrated for topics (OD-120; the owner's `smart_go` has them since OD-150).
     */
    public static function canAccess(): bool
    {
        return PostTopic::isAvailable() && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Topic')
                ->description('Shown with the articles that have it, once those are published, and on its own page listing them.')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(60)
                        ->notRegex('/[<>]/')
                        ->unique(ignoreRecord: true)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set, ?string $state): mixed => blank($get('slug')) ? $set('slug', Str::slug((string) $state)) : null)
                        ->validationMessages(['not_regex' => 'Use plain text: < and > are not allowed.']),
                    TextInput::make('slug')
                        ->label('Address')
                        ->prefix(url('/insights/topic').'/')
                        ->required()
                        ->maxLength(60)
                        ->regex(ContentRules::SLUG_PATTERN)
                        ->unique(ignoreRecord: true)
                        ->validationMessages(['regex' => 'Use lowercase letters, numbers and hyphens (for example flexible-work).'])
                        ->helperText('Fixed once a published article shows the topic.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withUseCounts())
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->weight('semibold')->searchable(),
                TextColumn::make('slug')->label('Address')->formatStateUsing(fn (string $state): string => '/insights/topic/'.$state),
                TextColumn::make('published_articles')
                    ->label('Published articles')
                    ->state(fn (PostTopic $record): int => $record->publishedPostCount()),
            ])
            ->emptyStateHeading('No topics yet')
            ->emptyStateDescription('Add a topic, then choose it on an article. Its page appears on the website once a published article has it.')
            ->recordActions([EditAction::make()->label('Open'), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPostTopics::route('/'),
            'create' => CreatePostTopic::route('/create'),
            'edit' => EditPostTopic::route('/{record}/edit'),
        ];
    }
}
