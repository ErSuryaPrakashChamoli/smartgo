<?php

namespace App\Filament\Clusters\Properties\Resources\Spaces\Pages;

use App\Actions\Inventory\ChangeSpaceStatus;
use App\Actions\Inventory\RemoveSpace;
use App\Enums\Inventory\SpaceStatus;
use App\Filament\Clusters\Properties\Concerns\RunsInventorySteps;
use App\Filament\Clusters\Properties\Resources\Properties\PropertyResource;
use App\Filament\Clusters\Properties\Resources\Spaces\SpaceResource;
use App\Models\Space;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * One space with its seats and allocations. Changing its status needs a reason and is refused while
 * allocations need the space (FR-INV-2, BR-INV-4); a space never allocated may be removed.
 *
 * @property Space $record
 */
class ViewSpace extends ViewRecord
{
    use RunsInventorySteps;

    protected static string $resource = SpaceResource::class;

    public function getTitle(): string
    {
        return 'Space '.$this->record->displayName();
    }

    public function getSubheading(): ?string
    {
        return 'SmartGro '.$this->record->property->name.', '.$this->record->floor->displayName().'. An allocation records occupancy under an agreement; it is not a booking.';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Edit'),
            Action::make('changeStatus')
                ->label('Change status')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn (): bool => Gate::allows('update', $this->record))
                ->fillForm(fn (): array => ['expected_status' => $this->record->operational_status->value])
                ->schema([
                    Select::make('status')->label('New status')->required()
                        ->options(fn (): array => collect(SpaceStatus::options())->except($this->record->operational_status->value)->all()),
                    Textarea::make('reason')->label('Reason')->required()->maxLength(300)->rows(2)
                        ->helperText('Recorded with the change. A space with allocations now or later cannot be taken out of use.'),
                    Hidden::make('expected_status'),
                ])
                ->action(function (array $data): void {
                    $this->runInventoryStep(fn (User $user): Space => app(ChangeSpaceStatus::class)->handle($user, $this->record, SpaceStatus::from((string) $data['status']), SpaceStatus::from((string) $data['expected_status']), (string) $data['reason']), 'Status changed');
                    $this->record->refresh();
                }),
            Action::make('remove')
                ->label('Remove')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->visible(fn (): bool => Gate::allows('delete', $this->record) && ! $this->record->allocations()->exists())
                ->modalDescription('Only a space recorded by mistake and never allocated can be removed. Its code stays used.')
                ->schema([Textarea::make('reason')->label('Reason')->required()->maxLength(300)->rows(2)])
                ->action(function (array $data): void {
                    $this->runInventoryStep(fn (User $user) => app(RemoveSpace::class)->handle($user, $this->record, (string) $data['reason']), 'Space removed');
                    $this->redirect(PropertyResource::getUrl('edit', ['record' => $this->record->property_id]));
                }),
        ];
    }
}
