<?php

namespace App\Filament\Clusters\DesignPreviews\Pages;

use App\Filament\Clusters\DesignPreviews\Concerns\IsDesignPreview;
use App\Filament\Clusters\DesignPreviews\DesignPreviewsCluster;
use App\Services\Design\DesignPreviewContent;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * Property create and edit wizard (DATABASE_ERD §3: properties, buildings). Pre-filled with A-70's
 * confirmed facts only (OD-08, OD-13, OD-14); fields SmartGro has not supplied stay empty.
 *
 * @property-read Schema $form
 */
class PropertyEditor extends Page
{
    use IsDesignPreview;

    protected static ?string $cluster = DesignPreviewsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?string $navigationLabel = 'Property: create and edit';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Edit property: A-70, Sector 2';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'city' => 'noida',
            'name' => 'A-70',
            'micro_market' => 'Sector 2',
            'operating_model' => 'owned_operated',
            'buildings' => [
                ['code' => null, 'address_line_1' => 'A-70', 'address_line_2' => null, 'locality' => 'Sector 2', 'postal_code' => null, 'is_primary' => true],
            ],
            'operating_hours' => [],
            'is_publicly_listed' => false,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $content = app(DesignPreviewContent::class);

        return $schema
            ->components([
                Wizard::make([
                    Step::make('Identity')
                        ->description('Name, city and how SmartGro runs it')
                        ->icon(Heroicon::OutlinedIdentification)
                        ->schema([
                            Grid::make(2)->schema([
                                Select::make('city')
                                    ->options(collect($content->cities())->pluck('name', 'slug')->all())
                                    ->required()
                                    ->helperText('Cities and their public visibility are managed in Cities.'),
                                Select::make('micro_market')
                                    ->label('Area')
                                    ->options(['Sector 2' => 'Sector 2', 'Sector 3' => 'Sector 3', 'Sector 62' => 'Sector 62'])
                                    ->required(),
                                TextInput::make('name')
                                    ->label('Property name')
                                    ->required()
                                    ->maxLength(120),
                                TextInput::make('slug')
                                    ->label('Page address')
                                    ->prefix('/locations/noida/')
                                    ->placeholder('a-70')
                                    ->helperText('Set from the name; changing it later creates a redirect.'),
                            ]),
                            Radio::make('operating_model')
                                ->label('Operating model')
                                ->options(['owned_operated' => 'Owned and operated', 'leased_operated' => 'Leased and operated'])
                                ->descriptions([
                                    'owned_operated' => 'SmartGro owns and runs the property.',
                                    'leased_operated' => 'SmartGro leases the property and runs it; lease dates are tracked privately.',
                                ])
                                ->required(),
                            Grid::make(2)->schema([
                                Select::make('operational_status')
                                    ->label('Status')
                                    ->options(['planned' => 'Planned', 'pre_opening' => 'Pre-opening', 'open' => 'Open', 'temporarily_closed' => 'Temporarily closed', 'closed' => 'Closed'])
                                    ->placeholder('To be confirmed')
                                    ->live(),
                                DatePicker::make('opens_on')
                                    ->label('Opens on')
                                    ->visible(fn (Get $get): bool => in_array($get('operational_status'), ['planned', 'pre_opening'], true)),
                            ]),
                        ]),
                    Step::make('Buildings')
                        ->description('Addresses; one is the primary building')
                        ->icon(Heroicon::OutlinedMapPin)
                        ->schema([
                            Repeater::make('buildings')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('address_line_1')->label('Address line 1')->required(),
                                        TextInput::make('address_line_2')->label('Address line 2'),
                                        TextInput::make('locality')->required(),
                                        TextInput::make('postal_code')->label('PIN code')->placeholder('To be supplied by SmartGro'),
                                        TextInput::make('code')->label('Internal building code')->placeholder('Assigned when saved'),
                                        Toggle::make('is_primary')->label('Primary building')->inline(false),
                                    ]),
                                ])
                                ->itemLabel(fn (array $state): string => $state['address_line_1'] ?? 'New building')
                                ->addActionLabel('Add another building')
                                ->minItems(1),
                        ]),
                    Step::make('Opening hours')
                        ->description('Used for tours and bookings')
                        ->icon(Heroicon::OutlinedClock)
                        ->schema([
                            Text::make('Opening hours have not been supplied for A-70 yet. Tours and bookings are offered only inside these hours.')->color('gray'),
                            Repeater::make('operating_hours')
                                ->hiddenLabel()
                                ->schema([
                                    Grid::make(3)->schema([
                                        Select::make('day')->options(['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'])->required(),
                                        TimePicker::make('opens')->seconds(false)->required(),
                                        TimePicker::make('closes')->seconds(false)->required(),
                                    ]),
                                ])
                                ->defaultItems(0)
                                ->addActionLabel('Add opening hours'),
                        ]),
                    Step::make('Website')
                        ->description('Public listing')
                        ->icon(Heroicon::OutlinedGlobeAlt)
                        ->schema([
                            Toggle::make('is_publicly_listed')
                                ->label('Show this property on the website')
                                ->helperText('Stays off until the property page has photos and approved copy.'),
                            TextInput::make('page_title')->label('Page title')->placeholder('Draft copy is written in Phase 6'),
                            Textarea::make('summary')->label('Short summary')->rows(3),
                        ]),
                    Step::make('Review')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->schema([
                            Text::make('Saving creates or updates the property. Floors and spaces are added next, under Buildings and floors and the inventory screens. Every change is recorded in the audit log.'),
                        ]),
                ])
                    ->skippable()
                    ->persistStepInQueryString()
                    ->submitAction(new HtmlString(Blade::render('<x-filament::button type="submit">Save property</x-filament::button>'))),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->previewNotice('Pre-filled with A-70\'s confirmed facts only: Sector 2, Noida; owned and operated. Fields SmartGro has not supplied stay empty. Saving does nothing.'),
            Form::make([EmbeddedSchema::make('form')])->livewireSubmitHandler('save'),
        ]);
    }
}
