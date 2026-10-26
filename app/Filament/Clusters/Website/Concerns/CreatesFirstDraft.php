<?php

namespace App\Filament\Clusters\Website\Concerns;

use App\Actions\Content\CreateDraft;
use App\Models\Contracts\Revisionable;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Adds revisioned content: an unpublished record and its first draft, in the create page's
 * transaction. Nothing is public until a version is approved and published.
 */
trait CreatesFirstDraft
{
    use MapsPayloadErrorsToForm;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();
        /** @var Model&Revisionable $content */
        $content = static::getModel()::query()->forceCreate(['status' => 'draft']);

        try {
            app(CreateDraft::class)->handle($content, $user, $data);
        } catch (ValidationException $exception) {
            throw $this->payloadErrorsForForm($exception);
        }

        return $content;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Draft created';
    }
}
