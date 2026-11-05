<?php

namespace App\Filament\Clusters\Sales\Resources\AssignmentRules\Pages;

use App\Filament\Clusters\Sales\Resources\AssignmentRules\AssignmentRuleResource;
use App\Models\AssignmentRule;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * A new rule is saved switched off; it is switched on from its page once it is complete.
 */
class CreateAssignmentRule extends CreateRecord
{
    use SavesAssignmentRules;

    protected static string $resource = AssignmentRuleResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return $this->saveRule(new AssignmentRule, $data);
    }

    protected function getRedirectUrl(): string
    {
        return AssignmentRuleResource::getUrl('view', ['record' => $this->record]);
    }
}
