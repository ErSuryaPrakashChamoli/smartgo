<?php

namespace App\Filament\Clusters\Website\Concerns;

use Illuminate\Validation\ValidationException;

/**
 * Shows server-side payload errors on the form fields they concern. Payload paths count list items
 * (`blocks.2.data.items.0.question`); the form keys them by UUID, so each index is translated through
 * the form's current state.
 *
 * @property array<string, mixed>|null $data
 */
trait MapsPayloadErrorsToForm
{
    protected function payloadErrorsForForm(ValidationException $exception): ValidationException
    {
        $mapped = [];

        foreach ($exception->errors() as $key => $messages) {
            $state = $this->data;
            $path = [];

            foreach (explode('.', $key) as $segment) {
                if (is_array($state) && ctype_digit($segment) && ! array_key_exists($segment, $state)) {
                    $segment = (string) (array_keys($state)[(int) $segment] ?? $segment);
                }

                $path[] = $segment;
                $state = is_array($state) ? ($state[$segment] ?? null) : null;
            }

            $mapped['data.'.implode('.', $path)] = $messages;
        }

        return ValidationException::withMessages($mapped);
    }
}
