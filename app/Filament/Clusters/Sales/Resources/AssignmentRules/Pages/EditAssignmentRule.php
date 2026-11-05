<?php

namespace App\Filament\Clusters\Sales\Resources\AssignmentRules\Pages;

use App\Filament\Clusters\Sales\Resources\AssignmentRules\AssignmentRuleResource;
use App\Models\AssignmentRule;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Changes a rule that is switched off. An active rule opens on its own page instead, where it can be
 * switched off first (SaveAssignmentRule refuses to change it anyway).
 *
 * @property AssignmentRule $record
 */
class EditAssignmentRule extends EditRecord
{
    use SavesAssignmentRules;

    protected static string $resource = AssignmentRuleResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->record->is_active) {
            Notification::make()->warning()->title('Switch the rule off to change it')->send();
            $this->redirect(AssignmentRuleResource::getUrl('view', ['record' => $this->record]));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [
            'name' => $this->record->name,
            'priority' => $this->record->priority,
            'strategy' => $this->record->strategy->value,
            'criteria' => $this->record->criteria,
            'assignees' => $this->record->assignees()->get()->map(fn ($user): array => ['user_id' => $user->getKey()])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->saveRule($this->record, $data);
    }

    protected function getRedirectUrl(): string
    {
        return AssignmentRuleResource::getUrl('view', ['record' => $this->record]);
    }
}
