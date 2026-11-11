<?php

namespace App\Services\Crm;

use App\Models\PricePlan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The values staff type on a deal (FR-CRM-4): seats, the monthly value in rupees and the expected close
 * date. Each is optional and nothing is filled in for them; a missing value is never stored as zero.
 */
final class DealValues
{
    /**
     * The values checked, with any further rules of the step.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, list<mixed>>  $rules
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function validate(array $data, array $rules = []): array
    {
        return Validator::make($data, [
            ...$rules,
            'seats' => ['nullable', 'integer', 'between:1,2000'],
            'monthly_value' => ['nullable', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'expected_close_on' => ['nullable', 'date'],
        ], [
            'monthly_value.regex' => 'Enter an amount in rupees, such as 125000 or 125000.50.',
        ], [
            'pipeline_stage_id' => 'stage',
            'monthly_value' => 'monthly value',
            'expected_close_on' => 'expected close date',
        ])->validate();
    }

    /**
     * The deal's columns for checked values.
     *
     * @param  array<string, mixed>  $validated
     * @return array{seats: int|null, monthly_value_minor: int|null, expected_close_on: string|null}
     */
    public static function columns(array $validated): array
    {
        return [
            'seats' => filled($validated['seats'] ?? null) ? (int) $validated['seats'] : null,
            'monthly_value_minor' => filled($validated['monthly_value'] ?? null) ? PricePlan::toMinor($validated['monthly_value']) : null,
            'expected_close_on' => filled($validated['expected_close_on'] ?? null) ? CarbonImmutable::parse($validated['expected_close_on'])->toDateString() : null,
        ];
    }
}
