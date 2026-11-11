<?php

namespace App\Filament\Clusters\Sales\Resources\Deals\Tables;

use App\Enums\Crm\PipelineOutcome;
use App\Models\Deal;
use App\Models\PipelineStage;
use App\Models\PricePlan;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The pipeline: deals by stage, in the Super Admin's order, open ones first by default. Values show as
 * staff typed them, or "—" when none was typed. On a phone only the deal and its stage are shown, so the
 * table never scrolls sideways with nothing in it to reach by keyboard (WCAG 2.1.1, axe
 * scrollable-region-focusable).
 */
class DealsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('proposedSpaces'))
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderBy(PipelineStage::query()->select('sort_order')->whereColumn('pipeline_stages.id', 'deals.pipeline_stage_id'))
                ->orderByDesc('deals.id'))
            ->columns([
                TextColumn::make('id')
                    ->label('Deal')
                    ->formatStateUsing(fn (int $state): string => 'Deal '.$state)
                    ->description(fn (Deal $record): string => $record->lead->reference.': '.$record->lead->submitted_name)
                    ->weight('semibold'),
                TextColumn::make('stage.name')
                    ->label('Stage')
                    ->badge()
                    ->color(fn (Deal $record): string => $record->outcome()->color())
                    ->description(fn (Deal $record): ?string => $record->isOpen() ? null : $record->outcome()->label()),
                TextColumn::make('owner.name')->label('Owner')->placeholder('Nobody')->visibleFrom('md'),
                TextColumn::make('seats')->numeric()->placeholder('—')->visibleFrom('md'),
                TextColumn::make('monthly_value_minor')->label('Monthly value')->formatStateUsing(fn (int $state): string => PricePlan::format($state))->placeholder('—')->visibleFrom('md'),
                TextColumn::make('expected_close_on')->label('Expected close')->date('j M Y')->placeholder('—')->visibleFrom('md'),
                TextColumn::make('proposed_spaces_count')->label('Spaces proposed')->numeric()->visibleFrom('md'),
            ])
            ->filters([
                SelectFilter::make('outcome')
                    ->label('Open or closed')
                    ->options(PipelineOutcome::options())
                    ->default(PipelineOutcome::Open->value)
                    ->query(fn (Builder $query, array $data): Builder => match (PipelineOutcome::tryFrom((string) ($data['value'] ?? ''))) {
                        PipelineOutcome::Open => $query->whereNull('deals.won_at')->whereNull('deals.lost_at'),
                        PipelineOutcome::Won => $query->whereNotNull('deals.won_at'),
                        PipelineOutcome::Lost => $query->whereNotNull('deals.lost_at'),
                        null => $query,
                    }),
                SelectFilter::make('pipeline_stage_id')
                    ->label('Stage')
                    ->options(fn (): array => PipelineStage::query()->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all()),
                Filter::make('mine')
                    ->label('My deals')
                    ->query(fn (Builder $query): Builder => $query->where('deals.owner_user_id', auth()->id())),
            ])
            ->recordActions([ViewAction::make()->label('Open')])
            ->emptyStateHeading('No deals')
            ->emptyStateDescription('Open a deal from an enquiry\'s page, under Deals.')
            ->emptyStateIcon('heroicon-o-briefcase');
    }
}
