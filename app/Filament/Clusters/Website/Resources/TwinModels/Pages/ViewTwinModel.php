<?php

namespace App\Filament\Clusters\Website\Resources\TwinModels\Pages;

use App\Actions\Twin\DeleteTwinModel;
use App\Filament\Clusters\Website\Resources\TwinModels\TwinModelResource;
use App\Models\TwinModel;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * One 3D model: its checks, the anchors found in it and a preview that loads only when asked for.
 *
 * @property TwinModel $record
 */
class ViewTwinModel extends ViewRecord
{
    protected static string $resource = TwinModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('delete')
                ->label('Delete')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->visible(fn (): bool => Gate::allows('delete', $this->record))
                ->requiresConfirmation()
                ->modalDescription('The model, its file and its anchors are deleted. A corrected file can be uploaded as a new model.')
                ->action(function (): void {
                    /** @var User $user */
                    $user = auth()->user();

                    app(DeleteTwinModel::class)->handle($this->record, $user);

                    Notification::make()->success()->title('3D model deleted')->send();
                    $this->redirect(static::getResource()::getUrl('index'));
                }),
        ];
    }
}
