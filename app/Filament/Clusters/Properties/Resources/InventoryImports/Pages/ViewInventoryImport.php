<?php

namespace App\Filament\Clusters\Properties\Resources\InventoryImports\Pages;

use App\Actions\Inventory\ChangeInventoryImportState;
use App\Actions\Inventory\CommitInventoryImport;
use App\Enums\Access\Capability;
use App\Enums\Inventory\ImportStatus;
use App\Filament\Clusters\Properties\Concerns\RunsInventorySteps;
use App\Filament\Clusters\Properties\Resources\InventoryImports\InventoryImportResource;
use App\Models\InventoryImport;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * One import: its findings, its preview, and the steps open to the user. Every step is checked again on
 * the server; the commit re-runs every check under lock.
 *
 * @property InventoryImport $record
 */
class ViewInventoryImport extends ViewRecord
{
    use RunsInventorySteps;

    protected static string $resource = InventoryImportResource::class;

    public function getTitle(): string
    {
        return 'Inventory import '.$this->record->getKey();
    }

    protected function getHeaderActions(): array
    {
        $isUploader = fn (): bool => $this->record->uploaded_by === auth()->id();
        $is = fn (ImportStatus ...$statuses): bool => in_array($this->record->status, $statuses, true);

        return [
            Action::make('revalidate')->label('Check again')->icon(Heroicon::OutlinedArrowPath)->color('gray')
                ->visible(fn (): bool => $isUploader() && $is(ImportStatus::Valid, ImportStatus::Invalid, ImportStatus::AwaitingApproval, ImportStatus::Rejected, ImportStatus::Failed))
                ->action(fn () => $this->step(fn (User $user) => app(ChangeInventoryImportState::class)->handle($user, $this->record, 'revalidate'), 'Checked again')),
            Action::make('submit')->label('Submit for approval')->icon(Heroicon::OutlinedPaperAirplane)
                ->visible(fn (): bool => $isUploader() && $is(ImportStatus::Valid))
                ->modalDescription(fn (): string => $this->record->warningCount() > 0 ? 'This workbook has '.$this->record->warningCount().' warnings. Read them in Findings first.' : 'Someone else who may approve imports then approves and imports it.')
                ->schema(fn (): array => $this->record->warningCount() > 0 ? [Checkbox::make('acknowledged')->label('I have read every warning and the import is still correct')->accepted()] : [])
                ->action(fn (array $data) => $this->step(fn (User $user) => app(ChangeInventoryImportState::class)->handle($user, $this->record, 'submit', (bool) ($data['acknowledged'] ?? false)), 'Submitted for approval')),
            Action::make('approve')->label('Approve and import')->icon(Heroicon::OutlinedCheckCircle)->color('success')
                ->visible(fn (): bool => ! $isUploader() && $is(ImportStatus::AwaitingApproval) && (auth()->user()?->can(Capability::ApproveInventoryImport) ?? false))
                ->requiresConfirmation()->modalDescription('Every check runs again; if SmartGro changed since the preview, nothing is imported. Otherwise everything is written at once.')
                ->action(fn () => $this->step(fn (User $user) => app(CommitInventoryImport::class)->handle($user, $this->record), 'Imported')),
            Action::make('reject')->label('Reject')->color('danger')
                ->visible(fn (): bool => ! $isUploader() && $is(ImportStatus::AwaitingApproval) && (auth()->user()?->can(Capability::ApproveInventoryImport) ?? false))
                ->schema([Textarea::make('reason')->label('Reason')->required()->maxLength(500)->rows(2)])
                ->action(fn (array $data) => $this->step(fn (User $user) => app(ChangeInventoryImportState::class)->handle($user, $this->record, 'reject', reason: (string) $data['reason']), 'Rejected')),
        ];
    }

    private function step(\Closure $step, string $success): void
    {
        $this->runInventoryStep($step, $success);
        $this->record->refresh();
    }
}
