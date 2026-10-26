<?php

namespace App\Filament\Clusters\Website\Resources\Pages\Pages;

use App\Actions\Content\CreateDraft;
use App\Filament\Clusters\Website\Concerns\MapsPayloadErrorsToForm;
use App\Filament\Clusters\Website\Resources\Pages\PageResource;
use App\Models\Page;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Adds a page: an unpublished page and its first draft. Nothing is public until a version is
 * approved and published.
 */
class CreatePage extends CreateRecord
{
    use MapsPayloadErrorsToForm;

    protected static string $resource = PageResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();
        $page = Page::query()->forceCreate(['status' => 'draft']);

        try {
            app(CreateDraft::class)->handle($page, $user, $data);
        } catch (ValidationException $exception) {
            throw $this->payloadErrorsForForm($exception);
        }

        return $page;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Draft created';
    }
}
