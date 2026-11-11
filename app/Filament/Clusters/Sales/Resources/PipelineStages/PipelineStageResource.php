<?php

namespace App\Filament\Clusters\Sales\Resources\PipelineStages;

use App\Enums\Crm\PipelineOutcome;
use App\Filament\Clusters\Sales\Resources\PipelineStages\Pages\CreatePipelineStage;
use App\Filament\Clusters\Sales\Resources\PipelineStages\Pages\EditPipelineStage;
use App\Filament\Clusters\Sales\Resources\PipelineStages\Pages\ListPipelineStages;
use App\Filament\Clusters\Sales\SalesCluster;
use App\Models\Deal;
use App\Models\PipelineStage;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The stages deals move through (FR-CRM-4), for the Super Admin only (OD-36). Nothing is preset (D-09):
 * the names, order, outcomes and probabilities are the owner's to decide. Until an open stage exists no
 * deal can be opened; until a won or lost stage exists none can be won or lost. A stage's outcome is fixed
 * once a deal has used it, and a stage is switched off rather than deleted, so past deals keep it. Hidden
 * until the deal tables exist.
 */
class PipelineStageResource extends Resource
{
    protected static ?string $model = PipelineStage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $cluster = SalesCluster::class;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Pipeline stages';

    protected static ?string $modelLabel = 'pipeline stage';

    protected static ?string $slug = 'pipeline-stages';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isGloballySearchable = false;

    public static function canAccess(): bool
    {
        return Deal::isAvailable() && parent::canAccess();
    }

    /**
     * Filament loads a stage before it checks access, so a stage's own address is refused here first while
     * the deal tables are missing, rather than ending in a query error.
     */
    public static function resolveRecordRouteBinding(int|string $key, ?Closure $modifyQuery = null): ?Model
    {
        abort_unless(Deal::isAvailable(), 403);

        return parent::resolveRecordRouteBinding($key, $modifyQuery);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Stage')
                ->description('Deals move between the open stages, in this order, and close by being won or lost in one of the closing stages.')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->required()->maxLength(60)->notRegex('/[<>]/')->unique(ignoreRecord: true)
                        ->validationMessages(['not_regex' => 'Use plain text: < and > are not allowed.']),
                    TextInput::make('sort_order')->label('Position in the list')->required()->integer()->minValue(0)->maxValue(65535)->default(0),
                    Radio::make('outcome')
                        ->label('What the stage means')
                        ->options(PipelineOutcome::options())
                        ->descriptions([
                            PipelineOutcome::Open->value => 'A deal in progress. Deals move between open stages.',
                            PipelineOutcome::Won->value => 'Winning a deal here allocates its proposed spaces, if they are still free.',
                            PipelineOutcome::Lost->value => 'Losing a deal here needs a reason. Nothing is allocated.',
                        ])
                        ->required()
                        ->disabled(fn (?PipelineStage $record): bool => $record?->deals()->exists() ?? false)
                        ->helperText(fn (?PipelineStage $record): ?string => $record?->deals()->exists() ? 'Deals already use this stage, so what it means stays as it is.' : null)
                        ->columnSpanFull(),
                    TextInput::make('probability_pct')->label('Probability (optional)')->integer()->minValue(0)->maxValue(100)->suffix('%')
                        ->helperText('Leave empty until a probability is decided.'),
                    Toggle::make('is_active')->label('Offered to staff')->default(true)->inline(false)
                        ->helperText('Switch off a stage you no longer want offered. Deals already in it keep it.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('deals'))
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('sort_order')->orderBy('name'))
            ->columns([
                TextColumn::make('sort_order')->label('Position')->numeric(),
                TextColumn::make('name')->weight('semibold'),
                TextColumn::make('outcome')->label('Means')->badge()->formatStateUsing(fn (PipelineOutcome $state): string => $state->label())->color(fn (PipelineOutcome $state): string => $state->color()),
                TextColumn::make('probability_pct')->label('Probability')->formatStateUsing(fn (int $state): string => $state.'%')->placeholder('Not set'),
                IconColumn::make('is_active')->label('Offered')->boolean(),
                TextColumn::make('deals_count')->label('Deals')->numeric(),
            ])
            ->emptyStateHeading('No pipeline stages yet')
            ->emptyStateDescription('Add the stages deals move through. Until an open stage exists no deal can be opened; until a won or lost stage exists none can be won or lost.')
            ->emptyStateIcon('heroicon-o-queue-list')
            ->recordActions([EditAction::make()->label('Open')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPipelineStages::route('/'),
            'create' => CreatePipelineStage::route('/create'),
            'edit' => EditPipelineStage::route('/{record}/edit'),
        ];
    }
}
