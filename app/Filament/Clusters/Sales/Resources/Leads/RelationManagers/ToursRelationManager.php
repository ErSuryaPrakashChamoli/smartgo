<?php

namespace App\Filament\Clusters\Sales\Resources\Leads\RelationManagers;

use App\Actions\Booking\ScheduleTour;
use App\Enums\Access\Capability;
use App\Enums\Booking\TourStatus;
use App\Filament\Clusters\Properties\Concerns\RunsInventorySteps;
use App\Filament\Clusters\Sales\Resources\Tours\TourResource;
use App\Models\Lead;
use App\Models\Property;
use App\Models\TourRequest;
use App\Models\User;
use App\Services\Booking\TourSlots;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * The tours of an enquiry (BR-TR-3), and arranging one with the visitor (UJ-10): a free time at a
 * location whose tours the user manages, confirmed at once and checked again under the property's lock.
 * Only tours the user may see are listed. Hidden on a database without the tour tables.
 *
 * @property Lead $ownerRecord
 */
class ToursRelationManager extends RelationManager
{
    use RunsInventorySteps;

    protected static string $relationship = 'tourRequests';

    protected static ?string $title = 'Tours';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return TourRequest::isAvailable() && $user !== null && $user->can(Capability::ViewTours) && Gate::allows('view', $ownerRecord);
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->visibleTo($user)->with('property'))
            ->columns([
                TextColumn::make('reference')->weight('semibold')->url(fn (TourRequest $record): string => TourResource::getUrl('view', ['record' => $record])),
                TextColumn::make('scheduled_start_at')->label('When (India time)')->dateTime('D j M Y, H:i', (string) config('backoffice.timezone')),
                TextColumn::make('property.name')->label('Location')->formatStateUsing(fn (string $state): string => 'SmartGro '.$state),
                TextColumn::make('status')->badge()->formatStateUsing(fn (TourStatus $state): string => $state->label())->color(fn (TourStatus $state): string => $state->color()),
            ])
            ->emptyStateHeading('No tours')
            ->emptyStateDescription('Arrange a visit once a time is agreed with the visitor.')
            ->headerActions([
                Action::make('arrangeTour')
                    ->label('Arrange a tour')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->visible(fn (): bool => $this->locations() !== [])
                    ->fillForm(fn (): array => ['property_id' => array_key_exists((int) $this->ownerRecord->property_id, $this->locations()) ? $this->ownerRecord->property_id : null, 'visitors' => 1])
                    ->schema([
                        Select::make('property_id')
                            ->label('Location')
                            ->options(fn (): array => $this->locations())
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('starts_at', null)),
                        Select::make('starts_at')
                            ->label('Time (India time)')
                            ->options(fn (Get $get, TourSlots $slots): array => ($property = Property::query()->find($get('property_id'))) !== null ? $slots->options($property) : [])
                            ->required()
                            ->helperText('Free times in the next '.config('tours.window_days').' days, from the location\'s tour hours. The time is checked again when you save.'),
                        TextInput::make('visitors')->label('People')->integer()->minValue(1)->maxValue((int) config('tours.max_visitors'))->required(),
                        Textarea::make('notes')->label('Notes (optional)')->maxLength(1000)->rows(2),
                    ])
                    ->action(fn (array $data) => $this->runInventoryStep(fn (User $actor): TourRequest => app(ScheduleTour::class)->handle($actor, $this->ownerRecord, $data), 'Tour arranged and confirmed')),
            ]);
    }

    /**
     * Locations with tour hours whose tours the user manages.
     *
     * @return array<int, string>
     */
    private function locations(): array
    {
        /** @var User $user */
        $user = auth()->user();

        return Property::query()->visibleTo($user, Capability::ManageTours)->whereHas('tourRules')->orderBy('code')->get()
            ->filter(fn (Property $property): bool => $user->hasCapabilityForProperty(Capability::ManageTours, $property))
            ->mapWithKeys(fn (Property $property): array => [$property->getKey() => 'SmartGro '.$property->name])->all();
    }
}
