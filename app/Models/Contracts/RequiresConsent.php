<?php

namespace App\Models\Contracts;

/**
 * Content published only with the person's or customer's consent on file (BR-CMS-7), whose consent can
 * be withdrawn (OD-101). While it is withdrawn, no version may carry the old consent forward.
 */
interface RequiresConsent
{
    public function consentWithdrawn(): bool;

    /**
     * The payload with its consent cleared, so a new draft never inherits consent after a withdrawal.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function withoutConsent(array $payload): array;
}
