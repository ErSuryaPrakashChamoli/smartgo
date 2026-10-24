<?php

namespace App\Filament\Clusters\DesignPreviews\Pages;

use App\Filament\Clusters\DesignPreviews\Concerns\IsDesignPreview;
use App\Filament\Clusters\DesignPreviews\DesignPreviewsCluster;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Mixed (hybrid) zones (OD-15; DATABASE_ERD §3.2; BR-AV-3d, BR-BK-2c): some seats are assigned to
 * individuals, the rest form a shared pool. At every instant, individually committed seats plus
 * pooled usage must not exceed the active seats.
 */
class MixedZones extends Page implements HasTable
{
    use InteractsWithTable;
    use IsDesignPreview;

    protected static ?string $cluster = DesignPreviewsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquaresPlus;

    protected static ?string $navigationLabel = 'Mixed zones';

    protected static ?int $navigationSort = 7;

    protected static ?string $title = 'Sample mixed zone';

    protected ?string $subheading = 'Assigned seats and a shared pool · Sample property (fictional)';

    /**
     * @var array<string, array{label: string, icon: string, classes: string}>
     */
    private const STATES = [
        'assigned' => ['label' => 'Assigned to a person', 'icon' => 'lock-closed', 'classes' => 'bg-primary-600 text-white ring-primary-600'],
        'pool' => ['label' => 'Shared pool seat', 'icon' => 'users', 'classes' => 'bg-warning-50 text-warning-700 ring-warning-600/30'],
    ];

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->previewNotice('A fictional zone: no real seats exist. Assigning saves nothing.'),
            Grid::make(['default' => 2, 'md' => 4])->schema([
                $this->stat('Active seats', '20'),
                $this->stat('Assigned to people', '8'),
                $this->stat('Pool in use today', '9'),
                $this->stat('Still free', '3'),
            ]),
            Callout::make('The rule for mixed zones')
                ->description('Assigned seats + seats used from the pool must never exceed the active seats, at any moment: 8 + 9 = 17 of 20 today. A seat cannot be assigned if that would leave the pool short, and pool bookings never take an assigned seat (BR-AV-3d).')
                ->icon(Heroicon::OutlinedScale)
                ->color('gray'),
            Section::make('Seat map: today')
                ->icon(Heroicon::OutlinedSquares2x2)
                ->schema([
                    View::make('filament.design-previews.seat-grid')->viewData([
                        'seats' => collect(range(1, 20))->map(fn (int $number): array => [
                            'label' => sprintf('H-%02d', $number),
                            'state' => $number <= 8 ? 'assigned' : 'pool',
                        ])->all(),
                        'states' => self::STATES,
                    ]),
                    Text::make('Pool seats are not tied to a person; 9 of the 12 are in use today, so 3 remain for either kind of use.')->color('gray'),
                ]),
            Callout::make('Example: an assignment that is refused')
                ->description('Assigning H-09 to Sample company C from the sample date is refused: on one day in that period, 9 assigned seats + 12 pool seats in use would exceed the 20 active seats. The check covers the whole requested period and runs after locking the zone (BR-BK-2c).')
                ->danger(),
            EmbeddedTable::make(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('assignSeat')
                ->label('Assign seat')
                ->icon(Heroicon::OutlinedUserPlus)
                ->schema([
                    Select::make('seat')->options(collect(range(9, 20))->mapWithKeys(fn (int $number): array => [sprintf('H-%02d', $number) => sprintf('H-%02d', $number)])->all())->required(),
                    TextInput::make('company')->label('Customer')->required(),
                    DatePicker::make('starts_on')->label('From')->required(),
                    DatePicker::make('ends_on')->label('Until'),
                ])
                ->action(fn () => $this->save()),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Commitments today')
            ->records(fn (): array => [
                1 => ['kind' => 'Assigned seats', 'who' => 'Sample company A', 'seats' => 'H-01 to H-05', 'count' => 5],
                2 => ['kind' => 'Assigned seats', 'who' => 'Sample company B', 'seats' => 'H-06 to H-08', 'count' => 3],
                3 => ['kind' => 'Pool allocation', 'who' => 'Sample company D', 'seats' => 'Any pool seat', 'count' => 6],
                4 => ['kind' => 'Pool booking', 'who' => 'Sample customer E', 'seats' => 'Any pool seat', 'count' => 3],
            ])
            ->columns([
                TextColumn::make('kind')->weight(FontWeight::SemiBold)->badge()->color(fn (string $state): string => $state === 'Assigned seats' ? 'primary' : 'warning'),
                TextColumn::make('who')->label('Customer'),
                TextColumn::make('seats')->label('Which seats'),
                TextColumn::make('count')->label('Seat count')->numeric(),
            ])
            ->paginated(false);
    }

    private function stat(string $label, string $value): Section
    {
        return Section::make()->compact()->schema([
            Text::make($label)->color('gray'),
            Text::make($value)->size(TextSize::Large)->weight(FontWeight::Bold),
        ]);
    }
}
