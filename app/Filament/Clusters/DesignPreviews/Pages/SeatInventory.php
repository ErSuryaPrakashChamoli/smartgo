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
 * Individual-seat inventory (`per_seat` zones, DATABASE_ERD §3.2; BR-AV-3a, BR-BK-2a). Shown with a
 * fictional zone because no real inventory has been entered.
 */
class SeatInventory extends Page implements HasTable
{
    use InteractsWithTable;
    use IsDesignPreview;

    protected static ?string $cluster = DesignPreviewsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static ?string $navigationLabel = 'Individual seats';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Sample dedicated desk zone';

    protected ?string $subheading = 'Individual seats · Sample property (fictional)';

    /**
     * @var array<string, array{label: string, icon: string, classes: string}>
     */
    private const STATES = [
        'allocated' => ['label' => 'Allocated (agreement)', 'icon' => 'lock-closed', 'classes' => 'bg-primary-600 text-white ring-primary-600'],
        'booked' => ['label' => 'Booked today', 'icon' => 'calendar-days', 'classes' => 'bg-info-50 text-info-700 ring-info-600/30'],
        'free' => ['label' => 'Free today', 'icon' => 'check', 'classes' => 'bg-success-50 text-success-700 ring-success-600/30'],
        'inactive' => ['label' => 'Inactive', 'icon' => 'no-symbol', 'classes' => 'bg-gray-100 text-gray-500 ring-gray-300'],
    ];

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->previewNotice('A fictional zone: no real seats have been entered. Assigning or deactivating saves nothing.'),
            Grid::make(['default' => 2, 'md' => 5])->schema([
                $this->stat('Active seats', '15'),
                $this->stat('Allocated', '9'),
                $this->stat('Booked today', '2'),
                $this->stat('Free today', '4'),
                $this->stat('Inactive', '1'),
            ]),
            Section::make('Seat map: today')
                ->icon(Heroicon::OutlinedSquares2x2)
                ->schema([
                    View::make('filament.design-previews.seat-grid')->viewData([
                        'seats' => collect($this->seats())->map(fn (array $seat, string $label): array => ['label' => $label, 'state' => $seat['state']])->values()->all(),
                        'states' => self::STATES,
                    ]),
                ]),
            EmbeddedTable::make(),
            Callout::make('How seat availability works')
                ->description('Each seat holds at most one allocation or booking at a time. Every assignment locks the zone first, then checks the seat, so two staff members cannot give the same seat away (BR-BK-2a, ADR-0004). The website shows the zone as available while at least one active seat is free.')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color('gray'),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('assignSeat')
                ->label('Assign seat')
                ->icon(Heroicon::OutlinedUserPlus)
                ->schema([
                    Select::make('seat')->options(collect($this->seats())->filter(fn (array $seat): bool => $seat['state'] === 'free')->keys()->mapWithKeys(fn (string $label): array => [$label => $label])->all())->required(),
                    TextInput::make('company')->label('Customer')->required(),
                    DatePicker::make('starts_on')->label('From')->required(),
                    DatePicker::make('ends_on')->label('Until')->helperText('Leave empty for an open-ended agreement.'),
                ])
                ->action(fn () => $this->save()),
            Action::make('addSeats')->label('Add seats')->color('gray')->action(fn () => $this->save()),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Seats')
            ->records(fn (): array => collect($this->seats())->mapWithKeys(fn (array $seat, string $label): array => [$label => [
                'seat' => $label,
                'state' => self::STATES[$seat['state']]['label'],
                'tone' => $seat['state'],
                'holder' => $seat['holder'] ?? '—',
                'until' => $seat['until'] ?? '—',
            ]])->all())
            ->columns([
                TextColumn::make('seat')->weight(FontWeight::SemiBold),
                TextColumn::make('state')->label('Today')->badge()->color(fn (array $record): string => ['allocated' => 'primary', 'booked' => 'info', 'free' => 'success', 'inactive' => 'gray'][$record['tone']]),
                TextColumn::make('holder')->label('Held by'),
                TextColumn::make('until')->label('Until'),
            ])
            ->recordActions([
                Action::make('toggleActive')
                    ->label(fn (array $record): string => $record['tone'] === 'inactive' ? 'Reactivate' : 'Deactivate')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('A seat with a current or future allocation or booking cannot be deactivated.')
                    ->action(fn () => $this->save()),
            ])
            ->paginated(false);
    }

    /**
     * @return array<string, array{state: string, holder?: string, until?: string}>
     */
    private function seats(): array
    {
        $seats = [];

        foreach (range(1, 16) as $number) {
            $label = sprintf('D-%02d', $number);
            $seats[$label] = match (true) {
                $number <= 5 => ['state' => 'allocated', 'holder' => 'Sample company A', 'until' => 'Open-ended'],
                in_array($number, [7, 8, 9, 10], true) => ['state' => 'allocated', 'holder' => 'Sample company B', 'until' => 'Sample date'],
                in_array($number, [11, 14], true) => ['state' => 'booked', 'holder' => 'Sample day booking', 'until' => 'Today'],
                $number === 13 => ['state' => 'inactive'],
                default => ['state' => 'free'],
            };
        }

        return $seats;
    }

    private function stat(string $label, string $value): Section
    {
        return Section::make()->compact()->schema([
            Text::make($label)->color('gray'),
            Text::make($value)->size(TextSize::Large)->weight(FontWeight::Bold),
        ]);
    }
}
