<?php

namespace App\Filament\Clusters\Sales\Resources\AssignmentRules\Pages;

use App\Actions\Crm\SetAssignmentRuleActive;
use App\Enums\Crm\AssignmentOutcome;
use App\Enums\Crm\AssignmentStrategy;
use App\Filament\Clusters\Sales\Resources\AssignmentRules\AssignmentRuleResource;
use App\Filament\Clusters\Sales\Resources\Leads\LeadResource;
use App\Models\AssignmentRule;
use App\Models\LeadAssignment;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * One assignment rule: what it matches, its people and whose turn is next, whether it is on, and what it
 * decided for recent enquiries. Switching it on or off runs SetAssignmentRuleActive, which checks again
 * that it can assign.
 *
 * @property AssignmentRule $record
 */
class ViewAssignmentRule extends ViewRecord
{
    protected static string $resource = AssignmentRuleResource::class;

    public function infolist(Schema $schema): Schema
    {
        $timezone = config('backoffice.timezone');

        return $schema->columns(2)->components([
            Section::make('Rule')
                ->columns(2)
                ->schema([
                    TextEntry::make('priority'),
                    TextEntry::make('is_active')->label('Status')->badge()->formatStateUsing(fn (bool $state): string => $state ? 'On' : 'Off')->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                    TextEntry::make('strategy')->label('How it picks the owner')->formatStateUsing(fn (AssignmentStrategy $state): string => $state->label().': '.$state->description())->columnSpanFull(),
                    TextEntry::make('criteria')->label('Which enquiries')->state(fn (AssignmentRule $record): array => AssignmentRuleResource::criteriaLines($record))->listWithLineBreaks()->columnSpanFull(),
                ]),
            Section::make('People')
                ->schema([
                    TextEntry::make('people')->hiddenLabel()->state(fn (AssignmentRule $record): array => $record->assignees()->get()
                        ->map(fn (User $user): string => $user->name.($user->is_active ? '' : ' (deactivated: passed over)'))->all())
                        ->listWithLineBreaks()->placeholder('Nobody yet'),
                    TextEntry::make('next_in_turn')
                        ->label('Last turn')
                        ->state(fn (AssignmentRule $record): string => $record->lastAssignedUser?->name ?? 'Nobody yet: the first eligible person in the list is next')
                        ->visible(fn (AssignmentRule $record): bool => $record->strategy === AssignmentStrategy::RoundRobin),
                ]),
            Section::make('Recent decisions')
                ->description('The last 20 new enquiries this rule matched.')
                ->collapsible()
                ->schema([
                    RepeatableEntry::make('recent_decisions')
                        ->hiddenLabel()
                        ->state(fn (AssignmentRule $record): Collection => $record->decisions()->with(['lead', 'assignedUser'])->latest('id')->limit(20)->get())
                        ->schema([
                            TextEntry::make('lead.reference')->hiddenLabel()->weight('semibold')->url(fn (LeadAssignment $record): string => LeadResource::getUrl('view', ['record' => $record->lead])),
                            TextEntry::make('outcome')->hiddenLabel()->formatStateUsing(fn (AssignmentOutcome $state): string => $state->label()),
                            TextEntry::make('assignedUser.name')->hiddenLabel()->placeholder('Unassigned'),
                            TextEntry::make('created_at')->hiddenLabel()->color('gray')->dateTime('j M Y, H:i', $timezone),
                        ])
                        ->columns(4)
                        ->placeholder('None yet'),
                ])
                ->columnSpanFull(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->visible(fn (): bool => ! $this->record->is_active),
            Action::make('switchOn')
                ->label('Switch on')
                ->icon(Heroicon::OutlinedPlay)
                ->visible(fn (): bool => ! $this->record->is_active)
                ->requiresConfirmation()
                ->modalDescription('New enquiries that match will be given an owner by this rule. Enquiries already received are not changed.')
                ->action(fn () => $this->setActive(true)),
            Action::make('switchOff')
                ->label('Switch off')
                ->icon(Heroicon::OutlinedPause)
                ->color('gray')
                ->visible(fn (): bool => $this->record->is_active)
                ->requiresConfirmation()
                ->modalDescription('New enquiries will no longer be assigned by this rule. Enquiries already assigned keep their owner.')
                ->action(fn () => $this->setActive(false)),
        ];
    }

    private function setActive(bool $active): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            app(SetAssignmentRuleActive::class)->handle($user, $this->record, $active);
        } catch (AuthorizationException $exception) {
            Notification::make()->danger()->title('Not allowed')->body($exception->getMessage())->send();

            throw new Halt;
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title('Not switched on')->body(collect($exception->errors())->flatten()->first())->send();

            throw new Halt;
        }

        Notification::make()->success()->title($active ? 'Rule switched on' : 'Rule switched off')->send();
        $this->record->refresh();
    }
}
