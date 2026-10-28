<?php

namespace App\Filament\Clusters\Sales\Resources\Leads\RelationManagers;

use App\Actions\Crm\RecordLeadActivity;
use App\Enums\Crm\LeadActivityType;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Internal notes, contacts made and follow-ups on an enquiry. Staff-only; entries are never edited or
 * deleted, and a follow-up is completed once. Adding or completing one re-checks permission and scope.
 */
class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'Notes and follow-ups';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('view', $ownerRecord);
    }

    public function table(Table $table): Table
    {
        $timezone = config('backoffice.timezone');

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'completer']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('type')->badge()->formatStateUsing(fn (LeadActivityType $state): string => $state->label()),
                TextColumn::make('body')->label('Note')->wrap()->limit(300),
                TextColumn::make('user.name')->label('By'),
                TextColumn::make('created_at')->label('When')->dateTime('j M Y, H:i', $timezone),
                TextColumn::make('due_at')
                    ->label('Follow-up')
                    ->state(fn (LeadActivity $record): ?string => match (true) {
                        $record->type !== LeadActivityType::FollowUp => null,
                        $record->completed_at !== null => 'Done '.$record->completed_at->copy()->timezone(config('backoffice.timezone'))->format('j M, H:i').' by '.$record->completer?->name,
                        default => 'Due '.$record->due_at?->copy()->timezone(config('backoffice.timezone'))->format('j M Y, H:i'),
                    })
                    ->placeholder('—'),
            ])
            ->headerActions([
                Action::make('record')
                    ->label('Add note or follow-up')
                    ->icon(Heroicon::OutlinedPlus)
                    ->visible(fn (): bool => Gate::allows('update', $this->getOwnerRecord()))
                    ->schema([
                        Select::make('type')->options(LeadActivityType::options())->default(LeadActivityType::Note->value)->required()->live(),
                        Textarea::make('body')->label('Note')->required()->maxLength(RecordLeadActivity::MAX_BODY_LENGTH)->rows(4),
                        DateTimePicker::make('due_at')
                            ->label('Due')
                            ->timezone(config('backoffice.timezone'))
                            ->seconds(false)
                            ->required(fn ($get): bool => $get('type') === LeadActivityType::FollowUp->value)
                            ->visible(fn ($get): bool => $get('type') === LeadActivityType::FollowUp->value),
                    ])
                    ->action(fn (array $data) => $this->runStep(fn (User $user) => app(RecordLeadActivity::class)->handle(
                        $this->leadRecord(),
                        $user,
                        LeadActivityType::from((string) $data['type']),
                        (string) $data['body'],
                        filled($data['due_at'] ?? null) ? CarbonImmutable::parse($data['due_at']) : null,
                    ), 'Saved')),
            ])
            ->recordActions([
                Action::make('complete')
                    ->label('Mark done')
                    ->icon(Heroicon::OutlinedCheck)
                    ->visible(fn (LeadActivity $record): bool => $record->isOpenFollowUp() && Gate::allows('update', $this->getOwnerRecord()))
                    ->requiresConfirmation()
                    ->action(fn (LeadActivity $record) => $this->runStep(fn (User $user) => app(RecordLeadActivity::class)->complete($record, $user), 'Follow-up done')),
            ])
            ->emptyStateHeading('No notes yet');
    }

    private function leadRecord(): Lead
    {
        /** @var Lead $lead */
        $lead = $this->getOwnerRecord();

        return $lead;
    }

    /**
     * @param  Closure(User): mixed  $step
     */
    private function runStep(Closure $step, string $success): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $step($user);
        } catch (AuthorizationException $exception) {
            Notification::make()->danger()->title('Not allowed')->body($exception->getMessage())->send();

            throw new Halt;
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title('Not saved')->body(collect($exception->errors())->flatten()->first())->send();

            throw new Halt;
        }

        Notification::make()->success()->title($success)->send();
    }
}
