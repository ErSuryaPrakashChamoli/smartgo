<?php

namespace App\Console\Commands\Inventory;

use App\Enums\Content\RevisionState;
use App\Models\Property;
use App\Models\Space;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Step 3 of the floor-rule transition plan (PHASE_5_ACCEPTANCE_PACK §15.2): lists every property that is
 * on the website or has a profile version on its way there, with what BR-PROP-3's inventory part would
 * still refuse. Reads only; succeeds only when nothing would be refused, so it can gate switching
 * INVENTORY_ENFORCE_FLOOR_RULE on in an environment. Switching it on never takes a property off the
 * website either way.
 */
#[Signature('inventory:floor-rule-readiness')]
#[Description('Check, without changing anything, whether the floor rule (BR-PROP-3) can be switched on')]
class FloorRuleReadinessCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->components->twoColumnDetail('Floor rule switch (INVENTORY_ENFORCE_FLOOR_RULE)', config('inventory.enforce_floor_rule_on_publication') ? 'ON' : 'OFF');

        if (! Space::isAvailable()) {
            $this->components->error('This database has no floor and space tables, so the rule cannot be checked here. Keep the switch off.');

            return self::FAILURE;
        }

        $rows = Property::query()
            ->with('profile')
            ->whereHas('profile', fn ($profile) => $profile->where('status', 'published')
                ->orWhereHas('revisions', fn ($revisions) => $revisions->whereIn('state', [RevisionState::Submitted, RevisionState::Approved])))
            ->orderBy('code')
            ->get()
            ->map(fn (Property $property): array => [
                $property->code,
                $property->name,
                $property->profile?->isPublished() ? 'yes' : 'no',
                $property->profile?->revisions()->whereIn('state', [RevisionState::Submitted, RevisionState::Approved])->count() ?? 0,
                implode(' ', $property->inventoryPublicationGaps()) ?: 'ready',
            ]);

        $this->table(['Code', 'Property', 'On the website', 'Versions waiting', 'What the rule would refuse'], $rows->all());

        $notReady = $rows->reject(fn (array $row): bool => $row[4] === 'ready')->count();

        if ($notReady > 0) {
            $this->components->warn("{$notReady} of {$rows->count()} property(ies) are not ready. Their pages stay on the website if the rule is switched on, but their next version would be refused.");

            return self::FAILURE;
        }

        $this->components->info("All {$rows->count()} property(ies) are ready.");

        return self::SUCCESS;
    }
}
