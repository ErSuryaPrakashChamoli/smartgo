<?php

namespace App\Filament\Clusters\Properties\Resources\PricePlans\Pages;

use App\Actions\Pricing\SavePricePlan;
use App\Filament\Clusters\Properties\Resources\PricePlans\PricePlanResource;
use App\Models\PricePlan;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Changes a draft plan; an active plan is never edited (SavePricePlan).
 *
 * @property PricePlan $record
 */
class EditPricePlan extends EditRecord
{
    protected static string $resource = PricePlanResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $plan = $this->record;

        return [
            'property_id' => $plan->offering->property_id,
            'offering_id' => $plan->offering_id,
            'space_id' => $plan->space_id,
            'unit' => $plan->unit->value,
            'bundle_size' => $plan->bundle_size,
            'amount' => PricePlan::toRupees($plan->amount_minor),
            'min_term_months' => $plan->min_term_months,
            'deposit_type' => $plan->deposit_type?->value,
            'deposit_months' => $plan->deposit_months,
            'deposit_amount' => PricePlan::toRupees($plan->deposit_minor),
            'setup_fee' => PricePlan::toRupees($plan->setup_fee_minor),
            'valid_from' => $plan->valid_from->toDateString(),
            'valid_to' => $plan->valid_to?->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            return app(SavePricePlan::class)->handle($user, $this->record, $data);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(Arr::mapWithKeys($exception->errors(), fn (array $messages, string $key): array => ["data.{$key}" => $messages]));
        }
    }

    protected function getRedirectUrl(): string
    {
        return PricePlanResource::getUrl('view', ['record' => $this->record]);
    }
}
