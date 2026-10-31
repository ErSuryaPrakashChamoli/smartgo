<?php

namespace App\Filament\Clusters\Properties\Resources\PricePlans\Pages;

use App\Actions\Pricing\ChangePricePlanState;
use App\Enums\Access\Capability;
use App\Enums\Pricing\PricePlanStatus;
use App\Filament\Clusters\Properties\Concerns\RunsInventorySteps;
use App\Filament\Clusters\Properties\Resources\PricePlans\PricePlanResource;
use App\Models\PricePlan;
use App\Models\User;
use App\Services\Inventory\BusinessDay;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * One price plan with its history and the steps open to the user: submit, activate (someone other than
 * its authors), return, set its last day, or retire it. Each step is checked again on the server under
 * the offering's lock, so a hidden button is only a convenience.
 *
 * @property PricePlan $record
 */
class ViewPricePlan extends ViewRecord
{
    use RunsInventorySteps;

    protected static string $resource = PricePlanResource::class;

    public function getTitle(): string
    {
        return 'Price plan '.$this->record->getKey();
    }

    public function getSubheading(): ?string
    {
        return $this->record->scopeName().'. Not shown on the website.';
    }

    protected function getHeaderActions(): array
    {
        $can = fn (Capability $capability): bool => auth()->user()?->can($capability) ?? false;
        $isAuthor = fn (): bool => in_array(auth()->id(), $this->record->authorIds(), true);
        $is = fn (PricePlanStatus $status): bool => $this->record->status === $status;
        $reason = Textarea::make('reason')->label('Reason')->required()->maxLength(500)->rows(2);

        return [
            EditAction::make()->label('Edit draft'),
            Action::make('submit')->label('Submit for activation')->icon(Heroicon::OutlinedPaperAirplane)
                ->visible(fn (): bool => $is(PricePlanStatus::Draft) && $can(Capability::ManagePrices))
                ->requiresConfirmation()->modalDescription('Someone else who may approve prices then activates it.')
                ->action(fn () => $this->step('submit', null, 'Submitted for activation')),
            Action::make('activate')->label('Activate')->icon(Heroicon::OutlinedCheckCircle)->color('success')
                ->visible(fn (): bool => $is(PricePlanStatus::PendingApproval) && $can(Capability::ApprovePrices) && ! $isAuthor())
                ->requiresConfirmation()->modalDescription('The price applies from its start date. Only one plan can apply to this category, space and unit on any day.')
                ->action(fn () => $this->step('activate', null, 'Price plan activated')),
            Action::make('return')->label(fn (): string => $isAuthor() ? 'Withdraw' : 'Return to Finance')->color('gray')
                ->visible(fn (): bool => $is(PricePlanStatus::PendingApproval) && ($isAuthor() ? $can(Capability::ManagePrices) : $can(Capability::ApprovePrices)))
                ->schema([$reason])
                ->action(fn (array $data) => $this->step('return', $data['reason'], 'Returned as a draft')),
            Action::make('end')->label('Set last day')->color('gray')
                ->visible(fn (): bool => $is(PricePlanStatus::Active) && $can(Capability::ManagePrices))
                ->schema([DatePicker::make('ends_on')->label('Last day it applies')->required()->minDate(BusinessDay::today()), $reason])
                ->action(fn (array $data) => $this->step('end', $data['reason'], 'Last day set', (string) $data['ends_on'])),
            Action::make('retire')->label('Retire')->color('danger')
                ->visible(fn (): bool => $is(PricePlanStatus::Active) && $can(Capability::ManagePrices))
                ->modalDescription('The plan stops applying at once. It stays in the history.')
                ->schema([$reason])
                ->action(fn (array $data) => $this->step('retire', $data['reason'], 'Price plan retired')),
        ];
    }

    private function step(string $change, ?string $reason, string $success, ?string $endsOn = null): void
    {
        $this->runInventoryStep(fn (User $user): PricePlan => app(ChangePricePlanState::class)->handle($user, $this->record, $change, $reason, $endsOn), $success);
        $this->record->refresh();
    }
}
