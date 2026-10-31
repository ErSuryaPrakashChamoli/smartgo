<?php

namespace App\Actions\Pricing;

use App\Enums\Access\Capability;
use App\Enums\Pricing\DepositType;
use App\Enums\Pricing\PricePlanStatus;
use App\Enums\Pricing\PriceUnit;
use App\Models\Offering;
use App\Models\PricePlan;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Enters or changes a draft price plan (FR-INV-4, BR-PR-1, BR-PR-2): Finance (or the Super Admin) types
 * every value; nothing is defaulted. A plan prices one workspace category at one property, or one space
 * sold as it (PRICING_CONFIGURATION_PROPOSAL §3), in one of the units the category may use (§2,
 * config/pricing.php). Amounts are entered in rupees and stored in paise, tax-exclusive, in INR. Only a
 * draft can change: an active plan stays as it was approved, and a new price is a new plan. Everyone who
 * edits a plan is recorded, so none of them can activate it.
 */
class SavePricePlan
{
    /**
     * The audited fields of a plan.
     *
     * @var list<string>
     */
    private const array AUDITED = ['offering_id', 'space_id', 'unit', 'bundle_size', 'amount_minor', 'min_term_months', 'deposit_type', 'deposit_months', 'deposit_minor', 'setup_fee_minor', 'valid_from', 'valid_to'];

    public function __construct(private RecordPricingActivity $recordPricingActivity) {}

    /**
     * @param  array<string, mixed>  $data  offering_id, space_id, unit, bundle_size, amount, min_term_months, deposit_type, deposit_months, deposit_amount, setup_fee, valid_from, valid_to (amounts in rupees)
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, ?PricePlan $plan, array $data): PricePlan
    {
        if (! $user->can(Capability::ManagePrices)) {
            throw new AuthorizationException('Only Finance and the Super Admin enter prices.');
        }

        $values = $this->validate($data);

        return DB::transaction(function () use ($user, $plan, $values): PricePlan {
            $plan = $plan === null ? new PricePlan : PricePlan::query()->lockForUpdate()->findOrFail($plan->getKey());

            if ($plan->exists && $plan->status !== PricePlanStatus::Draft) {
                throw ValidationException::withMessages(['plan' => 'Only a draft can be changed. An active plan stays as it was approved: enter the new price as a new plan.']);
            }

            $before = $plan->exists ? $this->audited($plan) : null;

            $plan->forceFill([
                ...$values,
                'currency' => config('pricing.currency'),
                'tax_treatment' => config('pricing.tax_treatment'),
                'status' => PricePlanStatus::Draft,
                'created_by' => $plan->created_by ?? $user->getKey(),
                'editor_ids' => array_values(array_unique([...($plan->editor_ids ?? []), $user->getKey()])),
            ])->save();

            $after = $this->audited($plan);

            if ($before !== $after) {
                $this->recordPricingActivity->handle($plan, $user, $before === null ? 'price_plan_created' : 'price_plan_changed', $before === null ? 'Price plan entered' : 'Price plan changed', [
                    'old' => $before,
                    'attributes' => $after,
                ]);
            }

            return $plan;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validate(array $data): array
    {
        $offering = Offering::query()->with('product')->find($data['offering_id'] ?? null);
        $units = $offering === null ? [] : config('pricing.units_by_category.'.$offering->product->key, []);
        $money = ['nullable', function (string $attribute, mixed $value, \Closure $fail): void {
            if (filled($value) && PricePlan::toMinor($value) === null) {
                $fail('Enter an amount in rupees, such as 12500 or 12500.50.');
            }
        }];

        $validator = Validator::make($data, [
            'offering_id' => ['required', 'integer', Rule::exists('offerings', 'id')->where('is_active', true)],
            'space_id' => ['nullable', 'integer', Rule::exists('offering_space', 'space_id')->where('offering_id', $data['offering_id'] ?? null)],
            'unit' => ['required', Rule::in($units)],
            'bundle_size' => [Rule::requiredIf(($data['unit'] ?? null) === PriceUnit::BundleDays->value), Rule::prohibitedIf(($data['unit'] ?? null) !== PriceUnit::BundleDays->value), 'nullable', 'integer', 'between:1,366'],
            'amount' => ['required', ...array_slice($money, 1)],
            'min_term_months' => ['nullable', 'integer', 'between:1,120'],
            'deposit_type' => ['nullable', Rule::enum(DepositType::class)],
            'deposit_months' => [Rule::requiredIf(($data['deposit_type'] ?? null) === DepositType::Months->value), Rule::prohibitedIf(($data['deposit_type'] ?? null) !== DepositType::Months->value), 'nullable', 'numeric', 'gt:0', 'max:99.99', 'decimal:0,2'],
            'deposit_amount' => [Rule::requiredIf(($data['deposit_type'] ?? null) === DepositType::Fixed->value), Rule::prohibitedIf(($data['deposit_type'] ?? null) !== DepositType::Fixed->value), ...$money],
            'setup_fee' => $money,
            'valid_from' => ['required', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ], [
            'offering_id.exists' => 'Choose a workspace category a property offers now.',
            'space_id.exists' => 'Choose a space that is sold as this category at this property.',
            'unit.in' => $offering === null ? 'Choose the workspace category first.' : ($units === [] ? $offering->product->name.' are priced by quotation, not by a price plan.' : 'Choose a unit '.$offering->product->name.' can be priced in.'),
            'bundle_size.required' => 'Enter how many days the bundle holds.',
            'bundle_size.prohibited' => 'Only a bundle of days has a number of days.',
            'deposit_months.required' => 'Enter how many months of the fee the deposit is.',
            'deposit_months.prohibited' => 'Months apply only to a deposit in months of the fee.',
            'deposit_amount.required' => 'Enter the deposit amount.',
            'deposit_amount.prohibited' => 'An amount applies only to a fixed deposit.',
            'valid_to.after_or_equal' => 'The plan cannot end before it starts.',
        ], ['offering_id' => 'workspace category', 'space_id' => 'space', 'valid_from' => 'start date', 'valid_to' => 'end date', 'min_term_months' => 'minimum term']);

        $validator->after(function ($validator) use ($data): void {
            if (filled($data['amount'] ?? null) && PricePlan::toMinor($data['amount']) === 0) {
                $validator->errors()->add('amount', 'Enter the price; it cannot be zero.');
            }
        });

        $valid = $validator->validate();

        return [
            'offering_id' => (int) $valid['offering_id'],
            'space_id' => filled($valid['space_id'] ?? null) ? (int) $valid['space_id'] : null,
            'unit' => PriceUnit::from($valid['unit']),
            'bundle_size' => filled($valid['bundle_size'] ?? null) ? (int) $valid['bundle_size'] : null,
            'amount_minor' => PricePlan::toMinor($valid['amount']),
            'min_term_months' => filled($valid['min_term_months'] ?? null) ? (int) $valid['min_term_months'] : null,
            'deposit_type' => filled($valid['deposit_type'] ?? null) ? DepositType::from($valid['deposit_type']) : null,
            'deposit_months' => filled($valid['deposit_months'] ?? null) ? (string) $valid['deposit_months'] : null,
            'deposit_minor' => filled($valid['deposit_amount'] ?? null) ? PricePlan::toMinor($valid['deposit_amount']) : null,
            'setup_fee_minor' => filled($valid['setup_fee'] ?? null) ? PricePlan::toMinor($valid['setup_fee']) : null,
            'valid_from' => $valid['valid_from'],
            'valid_to' => $valid['valid_to'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function audited(PricePlan $plan): array
    {
        return [
            ...$plan->only(self::AUDITED),
            'unit' => $plan->unit?->value,
            'deposit_type' => $plan->deposit_type?->value,
            'valid_from' => $plan->valid_from?->toDateString(),
            'valid_to' => $plan->valid_to?->toDateString(),
        ];
    }
}
