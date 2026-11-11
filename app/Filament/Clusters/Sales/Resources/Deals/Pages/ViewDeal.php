<?php

namespace App\Filament\Clusters\Sales\Resources\Deals\Pages;

use App\Actions\Crm\AssignDeal;
use App\Actions\Crm\AssignLead;
use App\Actions\Crm\LoseDeal;
use App\Actions\Crm\MoveDeal;
use App\Actions\Crm\UpdateDeal;
use App\Actions\Crm\WinDeal;
use App\Enums\Crm\PipelineOutcome;
use App\Filament\Clusters\Sales\Resources\Deals\DealResource;
use App\Models\Deal;
use App\Models\PipelineStage;
use App\Models\PricePlan;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * One deal with its actions. Each action runs a server-side step that locks the deal and checks
 * permission, scope and the state the user saw again, so hiding a button is only a convenience. "Mark
 * won" allocates the proposed spaces through the inventory, all or none (BR-CRM-7).
 *
 * @property Deal $record
 */
class ViewDeal extends ViewRecord
{
    protected static string $resource = DealResource::class;

    public function getSubheading(): ?string
    {
        return $this->record->isOpen()
            ? 'Proposed spaces are not reserved: each is allocated only when the deal is won, if it is still free then.'
            : null;
    }

    protected function getHeaderActions(): array
    {
        $open = fn (): bool => $this->record->isOpen() && Gate::allows('update', $this->record);

        return [
            Action::make('changeDetails')
                ->label('Change details')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('gray')
                ->visible($open)
                ->fillForm(fn (): array => [
                    'seats' => $this->record->seats,
                    'monthly_value' => PricePlan::toRupees($this->record->monthly_value_minor),
                    'expected_close_on' => $this->record->expected_close_on?->toDateString(),
                ])
                ->schema(self::valueFields())
                ->action(fn (array $data) => $this->runStep(fn (User $user) => app(UpdateDeal::class)->handle($user, $this->record, $data), 'Deal updated')),
            Action::make('move')
                ->label('Move')
                ->icon(Heroicon::OutlinedArrowRight)
                ->color('gray')
                ->visible(fn (): bool => $open() && PipelineStage::query()->offered(PipelineOutcome::Open)->whereKeyNot($this->record->pipeline_stage_id)->exists())
                ->fillForm(fn (): array => ['expected_stage_id' => $this->record->pipeline_stage_id])
                ->schema([
                    Select::make('pipeline_stage_id')
                        ->label('Move to')
                        ->options(fn (): array => PipelineStage::query()->offered(PipelineOutcome::Open)->whereKeyNot($this->record->pipeline_stage_id)->get()
                            ->mapWithKeys(fn (PipelineStage $stage): array => [$stage->getKey() => $stage->displayName()])->all())
                        ->required(),
                    Hidden::make('expected_stage_id'),
                ])
                ->action(fn (array $data) => $this->runStep(fn (User $user) => app(MoveDeal::class)->handle($user, $this->record, (int) $data['pipeline_stage_id'], (int) $data['expected_stage_id']), 'Deal moved')),
            Action::make('assign')
                ->label('Change owner')
                ->icon(Heroicon::OutlinedUserPlus)
                ->color('gray')
                ->visible(fn (): bool => $this->record->isOpen() && Gate::allows('assign', $this->record))
                ->fillForm(fn (): array => ['owner' => $this->record->owner_user_id, 'expected_owner' => $this->record->owner_user_id])
                ->schema([
                    Select::make('owner')
                        ->label('Owner')
                        ->options(fn (AssignLead $assignLead): array => $assignLead->candidates($this->record->lead))
                        ->required()
                        ->helperText('Only active staff who may work on this deal\'s enquiry are listed.'),
                    Hidden::make('expected_owner'),
                ])
                ->action(fn (array $data) => $this->runStep(fn (User $user) => app(AssignDeal::class)->handle(
                    $user,
                    $this->record,
                    User::query()->findOrFail($data['owner']),
                    filled($data['expected_owner'] ?? null) ? (int) $data['expected_owner'] : null,
                ), 'Owner updated')),
            Action::make('win')
                ->label('Mark won')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->visible($open)
                ->modalDescription(fn (): string => $this->record->proposedSpaces()->exists()
                    ? 'Each proposed space is allocated under this agreement, checked for availability now. If any is no longer free, nothing is allocated and the deal stays open.'
                    : 'No spaces are proposed, so nothing is allocated.')
                ->schema([
                    Select::make('pipeline_stage_id')
                        ->label('Won stage')
                        ->options(fn (): array => PipelineStage::query()->offered(PipelineOutcome::Won)->get()->mapWithKeys(fn (PipelineStage $stage): array => [$stage->getKey() => $stage->displayName()])->all())
                        ->required()
                        ->helperText(fn (): ?string => PipelineStage::query()->offered(PipelineOutcome::Won)->exists() ? null : 'No won stage is set up yet. The Super Admin adds one in Sales → Pipeline stages.'),
                    TextInput::make('agreement_reference')
                        ->label('Agreement reference')
                        ->maxLength(60)
                        ->helperText('Every allocation of this deal carries it, for example AG-2027-014.')
                        ->required(fn (): bool => $this->record->proposedSpaces()->exists())
                        ->visible(fn (): bool => $this->record->proposedSpaces()->exists()),
                    DatePicker::make('starts_on')
                        ->label('Allocated from')
                        ->native(false)
                        ->displayFormat('j M Y')
                        ->required(fn (): bool => $this->record->proposedSpaces()->exists())
                        ->visible(fn (): bool => $this->record->proposedSpaces()->exists()),
                    DatePicker::make('ends_on')
                        ->label('Allocated until (empty for open-ended)')
                        ->native(false)
                        ->displayFormat('j M Y')
                        ->visible(fn (): bool => $this->record->proposedSpaces()->exists()),
                ])
                ->action(fn (array $data) => $this->runStep(fn (User $user) => app(WinDeal::class)->handle($user, $this->record, $data), 'Deal won')),
            Action::make('lose')
                ->label('Mark lost')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('gray')
                ->visible($open)
                ->schema([
                    Select::make('pipeline_stage_id')
                        ->label('Lost stage')
                        ->options(fn (): array => PipelineStage::query()->offered(PipelineOutcome::Lost)->get()->mapWithKeys(fn (PipelineStage $stage): array => [$stage->getKey() => $stage->displayName()])->all())
                        ->required()
                        ->helperText(fn (): ?string => PipelineStage::query()->offered(PipelineOutcome::Lost)->exists() ? null : 'No lost stage is set up yet. The Super Admin adds one in Sales → Pipeline stages.'),
                    Textarea::make('reason')->label('Why it was lost')->required()->maxLength(300)->rows(3),
                ])
                ->action(fn (array $data) => $this->runStep(fn (User $user) => app(LoseDeal::class)->handle($user, $this->record, $data), 'Deal marked lost')),
        ];
    }

    /**
     * The values staff type on a deal, each optional (also used when opening one).
     *
     * @return list<TextInput|DatePicker>
     */
    public static function valueFields(): array
    {
        return [
            TextInput::make('seats')->label('Seats (optional)')->integer()->minValue(1)->maxValue(2000),
            TextInput::make('monthly_value')->label('Monthly value (optional)')->prefix('₹')->maxLength(14)
                ->regex('/^\d{1,10}(\.\d{1,2})?$/')->validationMessages(['regex' => 'Enter an amount in rupees, such as 125000 or 125000.50.']),
            DatePicker::make('expected_close_on')->label('Expected close (optional)')->native(false)->displayFormat('j M Y'),
        ];
    }

    /**
     * Runs a step for the signed-in user; refusals and validation errors become notifications.
     *
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
            Notification::make()->danger()->title('Not saved')->body(collect($exception->errors())->flatten()->first())->persistent()->send();

            throw new Halt;
        }

        Notification::make()->success()->title($success)->send();
        $this->record->refresh();
        $this->dispatch('deal-changed');
    }
}
