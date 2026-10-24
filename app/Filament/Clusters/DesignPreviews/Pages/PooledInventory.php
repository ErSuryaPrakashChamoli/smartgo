<?php

namespace App\Filament\Clusters\DesignPreviews\Pages;

use App\Filament\Clusters\DesignPreviews\Concerns\IsDesignPreview;
use App\Filament\Clusters\DesignPreviews\DesignPreviewsCluster;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Pooled-capacity inventory (`pooled` zones, DATABASE_ERD §3.2; BR-AV-3, BR-BK-2). Free seats on a
 * date = capacity − allocated seats − blocking booking quantities, including unexpired holds.
 *
 * @property-read Schema $form
 */
class PooledInventory extends Page implements HasTable
{
    use InteractsWithTable;
    use IsDesignPreview;

    protected static ?string $cluster = DesignPreviewsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Pooled capacity';

    protected static ?int $navigationSort = 6;

    protected static ?string $title = 'Sample flexible seating zone';

    protected ?string $subheading = 'Pooled capacity · Sample property (fictional)';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['date' => now()->toDateString(), 'capacity_seats' => 40, 'limited_below' => 5]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    DatePicker::make('date')->label('Show date')->required(),
                    TextInput::make('capacity_seats')->label('Capacity')->numeric()->minValue(1)->suffix('seats')->required()
                        ->helperText('Cannot go below the seats committed on any future date.'),
                    TextInput::make('limited_below')->label('Show "Limited availability" below')->numeric()->suffix('free seats')
                        ->helperText('Sample threshold; the real value is set per zone.'),
                ]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->previewNotice('A fictional zone: no real seats, members or bookings exist. Nothing is saved.'),
            Form::make([EmbeddedSchema::make('form')])
                ->livewireSubmitHandler('save')
                ->footer([Actions::make([Action::make('save')->label('Save zone')->submit('save')])]),
            Section::make('Usage on the selected date')
                ->icon(Heroicon::OutlinedChartBar)
                ->schema([
                    View::make('filament.design-previews.capacity-bar')->viewData([
                        'capacity' => 40,
                        'segments' => [
                            ['label' => 'Allocated to members', 'seats' => 22, 'classes' => 'bg-primary-600'],
                            ['label' => 'Confirmed day passes', 'seats' => 7, 'classes' => 'bg-info-500'],
                            ['label' => 'Held (expires in 10 min)', 'seats' => 2, 'classes' => 'bg-warning-400'],
                        ],
                        'caption' => 'Customers see "Available now": 9 seats are free, above the sample threshold of 5.',
                    ]),
                ]),
            EmbeddedTable::make(),
            Callout::make('Example: a request that is refused')
                ->description('A booking for 12 seats on this date is refused: only 9 are free. The check runs after locking the zone, so two requests can never both take the last seats (BR-BK-2, BR-BK-5).')
                ->danger(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Commitments on the selected date')
            ->records(fn (): array => [
                1 => ['type' => 'Allocation', 'who' => 'Sample company A', 'seats' => 12, 'status' => 'Active', 'tone' => 'primary', 'detail' => 'Monthly agreement'],
                2 => ['type' => 'Allocation', 'who' => 'Sample company B', 'seats' => 10, 'status' => 'Active', 'tone' => 'primary', 'detail' => 'Notice given'],
                3 => ['type' => 'Booking', 'who' => 'Sample customer C', 'seats' => 4, 'status' => 'Confirmed', 'tone' => 'info', 'detail' => 'Day pass'],
                4 => ['type' => 'Booking', 'who' => 'Sample customer D', 'seats' => 3, 'status' => 'Confirmed', 'tone' => 'info', 'detail' => 'Day pass'],
                5 => ['type' => 'Booking', 'who' => 'Sample customer E', 'seats' => 2, 'status' => 'Held', 'tone' => 'warning', 'detail' => 'Hold expires in 10 minutes'],
            ])
            ->columns([
                TextColumn::make('type')->weight(FontWeight::SemiBold),
                TextColumn::make('who')->label('Customer'),
                TextColumn::make('seats')->numeric(),
                TextColumn::make('status')->badge()->color(fn (array $record): string => $record['tone']),
                TextColumn::make('detail'),
            ])
            ->paginated(false);
    }
}
