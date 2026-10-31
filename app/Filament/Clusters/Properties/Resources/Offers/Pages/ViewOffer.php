<?php

namespace App\Filament\Clusters\Properties\Resources\Offers\Pages;

use App\Actions\Pricing\ChangeOfferState;
use App\Enums\Access\Capability;
use App\Enums\Pricing\OfferStatus;
use App\Filament\Clusters\Properties\Concerns\RunsInventorySteps;
use App\Filament\Clusters\Properties\Resources\Offers\OfferResource;
use App\Models\Offer;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * One offer with its history and the steps open to the user; each is checked again on the server.
 *
 * @property Offer $record
 */
class ViewOffer extends ViewRecord
{
    use RunsInventorySteps;

    protected static string $resource = OfferResource::class;

    public function getSubheading(): ?string
    {
        return 'Not shown on the website.';
    }

    protected function getHeaderActions(): array
    {
        $can = fn (Capability $capability): bool => auth()->user()?->can($capability) ?? false;
        $isAuthor = fn (): bool => in_array(auth()->id(), $this->record->authorIds(), true);
        $reason = Textarea::make('reason')->label('Reason')->required()->maxLength(500)->rows(2);

        return [
            EditAction::make()->label('Edit draft'),
            Action::make('submit')->label('Submit for approval')->icon(Heroicon::OutlinedPaperAirplane)
                ->visible(fn (): bool => $this->record->status === OfferStatus::Draft && $can(Capability::ManageOffers))
                ->requiresConfirmation()
                ->action(fn () => $this->step('submit', null, 'Submitted for approval')),
            Action::make('approve')->label('Approve')->icon(Heroicon::OutlinedCheckCircle)->color('success')
                ->visible(fn (): bool => $this->record->status === OfferStatus::PendingApproval && $can(Capability::ApprovePrices) && ! $isAuthor())
                ->requiresConfirmation()->modalDescription('The offer applies from its start until its end.')
                ->action(fn () => $this->step('approve', null, 'Offer approved')),
            Action::make('return')->label(fn (): string => $isAuthor() ? 'Withdraw' : 'Return to its author')->color('gray')
                ->visible(fn (): bool => $this->record->status === OfferStatus::PendingApproval && ($isAuthor() ? $can(Capability::ManageOffers) : $can(Capability::ApprovePrices)))
                ->schema([$reason])
                ->action(fn (array $data) => $this->step('return', $data['reason'], 'Returned as a draft')),
            Action::make('end')->label('End now')->color('danger')
                ->visible(fn (): bool => $this->record->stateAt(Carbon::now()) === OfferStatus::Active && ($can(Capability::ManageOffers) || $can(Capability::ApprovePrices)))
                ->schema([$reason])
                ->action(fn (array $data) => $this->step('end', $data['reason'], 'Offer ended')),
        ];
    }

    private function step(string $change, ?string $reason, string $success): void
    {
        $this->runInventoryStep(fn (User $user): Offer => app(ChangeOfferState::class)->handle($user, $this->record, $change, $reason), $success);
        $this->record->refresh();
    }
}
