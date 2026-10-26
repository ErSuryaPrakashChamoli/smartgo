<?php

namespace App\Filament\Clusters\Website\Resources\Pages\RelationManagers;

use App\Actions\Content\RestoreRevision;
use App\Enums\Content\RevisionState;
use App\Http\Controllers\Backoffice\RevisionPreviewController;
use App\Models\Revision;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Spatie\Activitylog\Models\Activity;

/**
 * Every version of the page with who wrote, edited, approved or rejected, scheduled and published it.
 * Read-only: restoring an earlier version copies it into a new draft and never changes the old one.
 */
class RevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    protected static ?string $title = 'Version history';

    public function isReadOnly(): bool
    {
        return true;
    }

    /**
     * Re-renders the history after a workflow step in the editor above it.
     */
    #[On('page-workflow-changed')]
    public function refreshHistory(): void {}

    public function table(Table $table): Table
    {
        $timezone = config('backoffice.timezone');

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['author', 'reviewer', 'publisher', 'scheduler']))
            ->defaultSort('number', 'desc')
            ->columns([
                TextColumn::make('number')->label('Version')->prefix('v')->sortable(),
                TextColumn::make('state')
                    ->badge()
                    ->formatStateUsing(fn (RevisionState $state): string => $state->label())
                    ->color(fn (RevisionState $state): string => $state->color()),
                TextColumn::make('author.name')
                    ->label('Written by')
                    ->description(fn (Revision $record): ?string => $record->editor_ids === [] ? null : 'Also edited by '.User::query()->whereKey($record->editor_ids)->orderBy('name')->pluck('name')->join(', ')),
                TextColumn::make('reviewer.name')
                    ->label('Approved or rejected by')
                    ->description(fn (Revision $record): ?string => $record->review_note === null ? null : Str::limit('Note: '.$record->review_note, 120))
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('publisher.name')
                    ->label('Published by')
                    ->description(fn (Revision $record): ?string => $record->published_at?->copy()->timezone($timezone)->format('j M Y, H:i'))
                    ->placeholder('—'),
                TextColumn::make('scheduled_for')
                    ->label('Scheduled for')
                    ->dateTime('j M Y, H:i', $timezone)
                    ->description(fn (Revision $record): ?string => $record->scheduler === null ? null : 'by '.$record->scheduler->name)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->label('Last change')->since()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('activity')
                    ->label('Activity')
                    ->icon(Heroicon::OutlinedListBullet)
                    ->iconButton()
                    ->color('gray')
                    ->modalHeading(fn (Revision $record): string => "Version {$record->number}: activity")
                    ->modalContent(fn (Revision $record): View => view('filament.content.revision-activity', [
                        'entries' => Activity::query()->inLog('content')->whereMorphedTo('subject', $record)->with('causer')->oldest('id')->get(),
                        'timezone' => $timezone,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Action::make('preview')
                    ->label('Preview')
                    ->icon(Heroicon::OutlinedEye)
                    ->iconButton()
                    ->color('gray')
                    ->visible(fn (Revision $record): bool => Gate::allows('view', $record))
                    ->url(fn (Revision $record): string => RevisionPreviewController::url($record), shouldOpenInNewTab: true),
                Action::make('restore')
                    ->label('Restore')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->visible(fn (Revision $record): bool => $record->state === RevisionState::Superseded)
                    ->disabled(fn (Revision $record): bool => Gate::denies('restore', $record))
                    ->tooltip(fn (Revision $record): ?string => Gate::inspect('restore', $record)->message())
                    ->requiresConfirmation()
                    ->modalDescription(fn (Revision $record): string => "Version {$record->number} is copied into a new draft. It goes live only after someone approves it and it is published. Version {$record->number} itself does not change.")
                    ->action(function (Revision $record): void {
                        /** @var User $user */
                        $user = auth()->user();

                        try {
                            app(RestoreRevision::class)->handle($record, $user);
                        } catch (AuthorizationException|ValidationException $exception) {
                            Notification::make()->danger()->title('Could not restore')->body($exception->getMessage())->send();

                            return;
                        }

                        Notification::make()->success()->title("Version {$record->number} restored as a new draft")->send();
                        $this->dispatch('revisions-changed');
                    }),
            ])
            ->paginated([10, 25, 50]);
    }
}
