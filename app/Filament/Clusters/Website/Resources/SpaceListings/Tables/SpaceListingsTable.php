<?php

namespace App\Filament\Clusters\Website\Resources\SpaceListings\Tables;

use App\Actions\Content\CreateDraft;
use App\Actions\Content\SubmitRevision;
use App\Actions\Content\UnpublishContent;
use App\Enums\Access\Capability;
use App\Enums\Content\RevisionState;
use App\Filament\Clusters\Website\Tables\RevisionedContentTable;
use App\Models\Property;
use App\Models\SpaceListing;
use App\Models\SpaceType;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The list of spaces on the website, each with its location and space type, and an On/Off switch (OD-177).
 *
 * Off takes the space off the website at once, as "Take off the website" does (OD-98). On sends the space's last
 * published content for approval as a new version: it shows again once someone else approves and publishes it, so
 * the switch stays off until then. A space that was never published is finished and submitted in its editor. The
 * switch is a convenience: the actions check permission and state again.
 */
class SpaceListingsTable
{
    public const string SWITCHED_OFF_REASON = 'Switched off in Website → Spaces on the website.';

    public static function configure(Table $table): Table
    {
        return RevisionedContentTable::configure(
            $table
                ->emptyStateHeading('No spaces yet')
                ->emptyStateDescription('Add a space at a location. It appears under its space type in the website\'s Spaces menu once someone else has approved it and it is published.'),
            'name',
            'Space',
            'New space',
            description: fn (SpaceListing $record): string => self::whereItIs($record),
            extraColumns: [
                IconColumn::make('is_sample')->label('Example')->boolean(),
                ToggleColumn::make('switched_on')
                    ->label('On / Off')
                    ->getStateUsing(fn (SpaceListing $record): bool => $record->status === 'published')
                    ->disabled(fn (SpaceListing $record): bool => self::switchRefusal($record) !== null)
                    ->tooltip(fn (SpaceListing $record): ?string => self::switchRefusal($record) ?? ($record->status === 'published' ? 'Switch off: hidden at once' : 'Switch on: sent for approval'))
                    ->updateStateUsing(fn (SpaceListing $record, bool $state): bool => $state ? self::switchOn($record) : self::switchOff($record)),
            ],
        );
    }

    /**
     * "SmartGro A-70 · Dedicated desk zone", from the live row or, before the first publication, the draft.
     */
    private static function whereItIs(SpaceListing $record): string
    {
        $payload = $record->status === 'draft' ? ($record->workingRevision?->payload ?? []) : $record->only(['property_id', 'space_type_id']);
        $property = once(fn () => Property::query()->pluck('name', 'id'))->get($payload['property_id'] ?? 0);
        $type = once(fn () => SpaceType::query()->pluck('name', 'id'))->get($payload['space_type_id'] ?? 0);

        return collect([$property === null ? null : 'SmartGro '.$property, $type])->filter()->join(' · ') ?: 'No location yet';
    }

    /**
     * Why this user cannot use the switch on this space now, or null when they can.
     */
    private static function switchRefusal(SpaceListing $record): ?string
    {
        /** @var User $user */
        $user = auth()->user();

        return match (true) {
            $record->status === 'draft' => 'Not published yet: open it to finish and submit it.',
            $record->status === 'published' => Gate::forUser($user)->allows('unpublish', $record->publishedRevision)
                ? null
                : (Gate::forUser($user)->inspect('unpublish', $record->publishedRevision)->message() ?? 'You cannot take content off the website.'),
            ! $user->can(Capability::EditContent) => 'You cannot write new versions of website content.',
            default => null,
        };
    }

    private static function switchOff(SpaceListing $record): bool
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            app(UnpublishContent::class)->handle($record, $user, self::SWITCHED_OFF_REASON, false);
        } catch (AuthorizationException|ValidationException $exception) {
            Notification::make()->danger()->title('Not switched off')->body($exception->getMessage())->send();

            return true;
        }

        Notification::make()->success()->title('Switched off')->body('"'.$record->name.'" is no longer on the website.')->send();

        return false;
    }

    private static function switchOn(SpaceListing $record): bool
    {
        /** @var User $user */
        $user = auth()->user();
        $inProgress = $record->revisions()->whereIn('state', RevisionState::open())->latest('number')->first();

        if ($inProgress !== null) {
            Notification::make()->warning()->title('Already in progress')
                ->body('Version '.$inProgress->number.' is '.strtolower($inProgress->state->label()).'. The space shows once it is approved and published.')
                ->send();

            return false;
        }

        try {
            // The row still holds the content it last had on the website; the new version starts from it.
            $draft = app(CreateDraft::class)->handle($record, $user);
            app(SubmitRevision::class)->handle($draft, $user);
        } catch (AuthorizationException|ValidationException $exception) {
            Notification::make()->danger()->title('Not sent for approval')->body($exception->getMessage())->send();

            return false;
        }

        Notification::make()->success()->title('Sent for approval')
            ->body('"'.$record->name.'" shows on the website again once someone else approves and publishes it.')
            ->send();

        return false;
    }
}
