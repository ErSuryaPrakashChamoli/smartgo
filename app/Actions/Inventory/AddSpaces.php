<?php

namespace App\Actions\Inventory;

use App\Actions\Properties\SaveProperty;
use App\Models\Property;
use App\Models\Space;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Adds a run of similar spaces to one floor in one step, such as cabins A70-C01 to A70-C12, so a
 * location with several floors and dozens of spaces can be entered in one session (FR-INV-1). Each
 * space goes through SaveSpace with the same rules and the same reason; all are added or none.
 */
class AddSpaces
{
    public const int MAX_SPACES = 100;

    public function __construct(private SaveSpace $saveSpace) {}

    /**
     * @param  array<string, mixed>  $data  count, code_prefix, first_number, name_prefix, and SaveSpace's fields except code and name
     * @return Collection<int, Space>
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, Property $property, array $data): Collection
    {
        $validated = Validator::make($data, [
            'count' => ['required', 'integer', 'between:1,'.self::MAX_SPACES],
            'code_prefix' => ['required', 'string', 'max:24', 'regex:/^[A-Z0-9]+(?:-[A-Z0-9]+)*-?$/'],
            'first_number' => ['required', 'integer', 'between:0,9999'],
            'name_prefix' => ['nullable', 'string', 'max:70', 'not_regex:/[<>]/'],
        ], [
            'code_prefix.regex' => 'Use capital letters, numbers and hyphens, for example A70-C.',
            'name_prefix.not_regex' => 'Use plain text: < and > are not allowed.',
        ], ['code_prefix' => 'code prefix', 'first_number' => 'first number', 'name_prefix' => 'name prefix'])->validate();

        $numbers = range((int) $validated['first_number'], (int) $validated['first_number'] + (int) $validated['count'] - 1);
        $width = max(2, strlen((string) end($numbers)));
        $codes = collect($numbers)->mapWithKeys(fn (int $number): array => [$number => $validated['code_prefix'].str_pad((string) $number, $width, '0', STR_PAD_LEFT)]);

        $invalid = $codes->reject(fn (string $code): bool => strlen($code) <= 30 && preg_match(SaveProperty::CODE_PATTERN, $code) === 1);
        $taken = Space::withTrashed()->whereIn('code', $codes->all())->pluck('code');

        if ($invalid->isNotEmpty() || $taken->isNotEmpty()) {
            throw ValidationException::withMessages(['code_prefix' => $taken->isNotEmpty()
                ? 'These codes are already used, now or before, and codes are permanent: '.$taken->implode(', ').'.'
                : 'These codes would not be valid: '.$invalid->implode(', ').'.']);
        }

        return DB::transaction(fn (): Collection => $codes->map(fn (string $code, int $number): Space => $this->saveSpace->handle($user, $property, null, [
            ...collect($data)->except(['count', 'code_prefix', 'first_number', 'name_prefix'])->all(),
            'code' => $code,
            'name' => filled($validated['name_prefix'] ?? null) ? $validated['name_prefix'].' '.$number : null,
        ]))->values());
    }
}
