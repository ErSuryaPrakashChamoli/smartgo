<?php

namespace App\Filament\Clusters\Sales\Resources\Leads\RelationManagers;

use App\Actions\Crm\OpenDeal;
use App\Enums\Crm\PipelineOutcome;
use App\Filament\Clusters\Properties\Concerns\RunsInventorySteps;
use App\Filament\Clusters\Sales\Resources\Deals\DealResource;
use App\Filament\Clusters\Sales\Resources\Deals\Pages\ViewDeal;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\PipelineStage;
use App\Models\PricePlan;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;

/**
 * The deals opened from an enquiry (FR-CRM-4), and opening one in one of the Super Admin's open stages.
 * An enquiry has at most one open deal at a time, and a closed enquiry gets none (OpenDeal checks both
 * again under the enquiry's lock). The relationship is built here from the deal side, so the enquiry model
 * is unchanged. Hidden on a database without the deal tables.
 *
 * @property Lead $ownerRecord
 */
class DealsRelationManager extends RelationManager
{
    use RunsInventorySteps;

    protected static string $relationship = 'deals';

    protected static ?string $title = 'Deals';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Deal::isAvailable() && Gate::allows('view', $ownerRecord);
    }

    public function getRelationship(): Relation|Builder
    {
        return $this->getOwnerRecord()->hasMany(Deal::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['stage', 'owner'])->withCount('proposedSpaces'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label('Deal')->formatStateUsing(fn (int $state): string => 'Deal '.$state)->weight('semibold')
                    ->url(fn (Deal $record): string => DealResource::getUrl('view', ['record' => $record])),
                TextColumn::make('stage.name')->label('Stage')->badge()->color(fn (Deal $record): string => $record->outcome()->color())
                    ->description(fn (Deal $record): ?string => $record->isOpen() ? null : $record->outcome()->label()),
                TextColumn::make('owner.name')->label('Owner')->placeholder('Nobody')->visibleFrom('md'),
                TextColumn::make('monthly_value_minor')->label('Monthly value')->formatStateUsing(fn (int $state): string => PricePlan::format($state))->placeholder('—')->visibleFrom('md'),
                TextColumn::make('proposed_spaces_count')->label('Spaces proposed')->numeric()->visibleFrom('md'),
            ])
            ->emptyStateHeading('No deals')
            ->emptyStateDescription(fn (): string => PipelineStage::query()->offered(PipelineOutcome::Open)->exists()
                ? 'Open a deal when the visitor is ready to discuss specific spaces.'
                : 'No open pipeline stages are set up yet, so no deal can be opened. The Super Admin adds them in Sales → Pipeline stages.')
            ->headerActions([
                Action::make('openDeal')
                    ->label('Open a deal')
                    ->icon(Heroicon::OutlinedBriefcase)
                    ->visible(fn (): bool => Gate::allows('update', $this->ownerRecord)
                        && ! $this->ownerRecord->status->isClosed()
                        && PipelineStage::query()->offered(PipelineOutcome::Open)->exists()
                        && ! Deal::query()->where('lead_id', $this->ownerRecord->getKey())->whereNull('won_at')->whereNull('lost_at')->exists())
                    ->schema([
                        Select::make('pipeline_stage_id')
                            ->label('Stage')
                            ->options(fn (): array => PipelineStage::query()->offered(PipelineOutcome::Open)->get()->mapWithKeys(fn (PipelineStage $stage): array => [$stage->getKey() => $stage->displayName()])->all())
                            ->required(),
                        ...ViewDeal::valueFields(),
                    ])
                    ->action(function (array $data): void {
                        $deal = $this->runInventoryStep(fn (User $user): Deal => app(OpenDeal::class)->handle($user, $this->ownerRecord, $data), 'Deal opened');
                        $this->redirect(DealResource::getUrl('view', ['record' => $deal]));
                    }),
            ]);
    }
}
