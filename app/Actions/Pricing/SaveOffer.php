<?php

namespace App\Actions\Pricing;

use App\Enums\Access\Capability;
use App\Enums\Pricing\DiscountType;
use App\Enums\Pricing\OfferStatus;
use App\Models\Offer;
use App\Models\PricePlan;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Drafts or changes an offer (FR-INV-5, PRICING_CONFIGURATION_PROPOSAL §7): a percentage, a fixed amount
 * or a free period; the dates it applies between (entered in the back office's time zone); the offerings
 * it applies to; an optional code; an optional limit on redemptions; and its terms, which are mandatory
 * (BR-PR-5). Staff with `offers.manage` draft offers (SECURITY_MODEL §3.1 "Offers (create)"); only a
 * draft changes, and every editor is recorded so none of them can approve it.
 */
class SaveOffer
{
    public function __construct(private RecordPricingActivity $recordPricingActivity) {}

    /**
     * @param  array<string, mixed>  $data  name, code, discount_type, percent, amount, free_period_quantity, free_period_unit, starts_at, ends_at, max_redemptions, terms, offering_ids
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, ?Offer $offer, array $data): Offer
    {
        if (! $user->can(Capability::ManageOffers)) {
            throw new AuthorizationException('You cannot draft offers.');
        }

        $values = $this->validate($offer, $data);

        return DB::transaction(function () use ($user, $offer, $values): Offer {
            $offer = $offer === null ? new Offer : Offer::query()->lockForUpdate()->findOrFail($offer->getKey());

            if ($offer->exists && $offer->status !== OfferStatus::Draft) {
                throw ValidationException::withMessages(['offer' => 'Only a draft offer can be changed.']);
            }

            $isNew = ! $offer->exists;
            $offeringIds = $values['offering_ids'];
            unset($values['offering_ids']);

            $offer->forceFill([
                ...$values,
                'status' => OfferStatus::Draft,
                'created_by' => $offer->created_by ?? $user->getKey(),
                'editor_ids' => array_values(array_unique([...($offer->editor_ids ?? []), $user->getKey()])),
            ])->save();
            $offer->offerings()->sync($offeringIds);

            $this->recordPricingActivity->handle($offer, $user, $isNew ? 'offer_created' : 'offer_changed', $isNew ? 'Offer drafted' : 'Offer changed', [
                'attributes' => [...$offer->only(['name', 'code', 'percent_bps', 'amount_minor', 'free_period_quantity', 'free_period_unit', 'max_redemptions']),
                    'discount_type' => $offer->discount_type->value, 'starts_at' => $offer->starts_at->toIso8601String(), 'ends_at' => $offer->ends_at->toIso8601String(), 'offering_ids' => $offeringIds],
            ]);

            return $offer;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validate(?Offer $offer, array $data): array
    {
        $type = DiscountType::tryFrom((string) ($data['discount_type'] ?? ''));
        $data['code'] = filled($data['code'] ?? null) ? strtoupper(trim((string) $data['code'])) : null;

        $valid = Validator::make($data, [
            'name' => ['required', 'string', 'max:120', 'not_regex:/[<>]/'],
            'code' => ['nullable', 'string', 'regex:/^[A-Z0-9][A-Z0-9-]{1,38}[A-Z0-9]$/', Rule::unique('offers', 'code')->ignore($offer?->getKey())],
            'discount_type' => ['required', Rule::enum(DiscountType::class)],
            'percent' => [Rule::requiredIf($type === DiscountType::Percent), Rule::prohibitedIf($type !== DiscountType::Percent), 'nullable', 'numeric', 'gt:0', 'max:100', 'decimal:0,2'],
            'amount' => [Rule::requiredIf($type === DiscountType::Fixed), Rule::prohibitedIf($type !== DiscountType::Fixed), 'nullable', function (string $attribute, mixed $value, \Closure $fail): void {
                if (filled($value) && ! (PricePlan::toMinor($value) > 0)) {
                    $fail('Enter an amount in rupees, such as 2000 or 2000.50.');
                }
            }],
            'free_period_quantity' => [Rule::requiredIf($type === DiscountType::FreePeriod), Rule::prohibitedIf($type !== DiscountType::FreePeriod), 'nullable', 'integer', 'between:1,366'],
            'free_period_unit' => [Rule::requiredIf($type === DiscountType::FreePeriod), Rule::prohibitedIf($type !== DiscountType::FreePeriod), 'nullable', Rule::in(['day', 'month'])],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'max_redemptions' => ['nullable', 'integer', 'between:1,1000000'],
            'terms' => ['required', 'string', 'max:5000', 'not_regex:/[<>]/'],
            'offering_ids' => ['required', 'array', 'min:1'],
            'offering_ids.*' => ['integer', Rule::exists('offerings', 'id')->where('is_active', true)],
        ], [
            'code.regex' => 'Use 3 to 40 capital letters, numbers and inner hyphens, for example WELCOME-2027.',
            'code.unique' => 'Another offer already uses this code.',
            'ends_at.after' => 'The offer must end after it starts.',
            'terms.required' => 'Every offer needs its terms (BR-PR-5).',
            'offering_ids.required' => 'Choose at least one workspace category at a property the offer applies to.',
            'offering_ids.*.exists' => 'Choose workspace categories that properties offer now.',
            '*.not_regex' => 'Use plain text: < and > are not allowed.',
        ], ['offering_ids' => 'eligible offerings', 'starts_at' => 'start', 'ends_at' => 'end', 'free_period_quantity' => 'free period'])->validate();

        $zone = config('backoffice.timezone');

        return [
            'name' => $valid['name'],
            'code' => $valid['code'] ?? null,
            'discount_type' => $type,
            'percent_bps' => $type === DiscountType::Percent ? (int) round((float) $valid['percent'] * 100) : null,
            'amount_minor' => $type === DiscountType::Fixed ? PricePlan::toMinor($valid['amount']) : null,
            'free_period_quantity' => $type === DiscountType::FreePeriod ? (int) $valid['free_period_quantity'] : null,
            'free_period_unit' => $type === DiscountType::FreePeriod ? $valid['free_period_unit'] : null,
            'starts_at' => Carbon::parse($valid['starts_at'], $zone)->utc(),
            'ends_at' => Carbon::parse($valid['ends_at'], $zone)->utc(),
            'max_redemptions' => filled($valid['max_redemptions'] ?? null) ? (int) $valid['max_redemptions'] : null,
            'terms' => $valid['terms'],
            'offering_ids' => array_values(array_unique(array_map('intval', $valid['offering_ids']))),
        ];
    }
}
