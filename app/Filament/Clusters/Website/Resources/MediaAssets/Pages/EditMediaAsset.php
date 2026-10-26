<?php

namespace App\Filament\Clusters\Website\Resources\MediaAssets\Pages;

use App\Actions\Media\DeleteMediaAsset;
use App\Actions\Media\UpdateMediaAsset;
use App\Filament\Clusters\Website\Resources\MediaAssets\MediaAssetResource;
use App\Models\MediaAsset;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * @property MediaAsset $record
 */
class EditMediaAsset extends EditRecord
{
    protected static string $resource = MediaAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('delete')
                ->label('Delete')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->visible(fn (): bool => Gate::allows('delete', $this->record))
                ->requiresConfirmation()
                ->modalDescription('The image and its renditions are deleted. An image that a page or page version uses cannot be deleted.')
                ->action(function (): void {
                    /** @var User $user */
                    $user = auth()->user();

                    try {
                        app(DeleteMediaAsset::class)->handle($this->record, $user);
                    } catch (ValidationException $exception) {
                        Notification::make()->danger()->title('Not deleted')->body(collect($exception->errors())->flatten()->first())->send();

                        return;
                    }

                    Notification::make()->success()->title('Image deleted')->send();
                    $this->redirect(static::getResource()::getUrl('index'));
                }),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return Arr::only($data, ['title', 'alt_text', 'is_decorative', 'caption', 'licence_type', 'licence_holder', 'licence_reference', 'licence_expires_at', 'credit_line', 'usage_restrictions']);
    }

    /**
     * @param  MediaAsset  $record
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            return app(UpdateMediaAsset::class)->handle($record, $user, $data);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(Arr::mapWithKeys($exception->errors(), fn (array $messages, string $key): array => ["data.{$key}" => $messages]));
        }
    }
}
