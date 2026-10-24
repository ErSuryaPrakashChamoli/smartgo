<?php

namespace App\Filament\Clusters\DesignPreviews\Pages;

use App\Filament\Clusters\DesignPreviews\Concerns\IsDesignPreview;
use App\Filament\Clusters\DesignPreviews\DesignPreviewsCluster;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Tour management (DATABASE_ERD §5 tour_requests; BR-TR-1 to BR-TR-4). Requested tours are kept
 * apart from confirmed ones; times are sample values and no visitor is ever contacted from here.
 */
class TourManagement extends Page implements HasTable
{
    use InteractsWithTable;
    use IsDesignPreview;

    protected static ?string $cluster = DesignPreviewsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Tours';

    protected static ?int $navigationSort = 12;

    protected static ?string $title = 'Tours';

    /**
     * @var array<string, array{label: string, color: string}>
     */
    private const STATUSES = [
        'requested' => ['label' => 'Requested: not yet confirmed', 'color' => 'warning'],
        'confirmed' => ['label' => 'Confirmed', 'color' => 'success'],
        'rescheduled' => ['label' => 'New time proposed', 'color' => 'info'],
        'completed' => ['label' => 'Completed', 'color' => 'gray'],
        'no_show' => ['label' => 'No-show', 'color' => 'gray'],
        'cancelled' => ['label' => 'Cancelled', 'color' => 'gray'],
    ];

    private const HOSTS = ['Sample host 1', 'Sample host 2'];

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->previewNotice('Sample visitors and sample times on the fictional Sample property. Real tour times come from each property\'s tour hours (BR-TR-1), which are not configured yet. Nothing here sends an email, SMS or WhatsApp message.'),
            EmbeddedTable::make(),
            Callout::make('Confirming a tour')
                ->description('Confirmation claims the time under the same lock-and-recheck rule as bookings, so two visitors cannot take the last place (BR-TR-2). In production the visitor is then told on the channel they agreed to (BR-TR-3).')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color('gray'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Tour requests and tours (sample)')
            ->records(fn (): array => $this->tours())
            ->columns([
                TextColumn::make('reference')->weight(FontWeight::SemiBold),
                TextColumn::make('visitor')->description(fn (array $record): string => $record['party'].' · '.$record['property']),
                TextColumn::make('requested')->label('Visitor asked for'),
                TextColumn::make('confirmed')->label('Confirmed time')->placeholder('Not confirmed'),
                TextColumn::make('host')->placeholder('Unassigned'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUSES[$state]['label'])
                    ->color(fn (string $state): string => self::STATUSES[$state]['color']),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('confirm')
                        ->label('Confirm a time')
                        ->icon(Heroicon::OutlinedCheck)
                        ->visible(fn (array $record): bool => in_array($record['status'], ['requested', 'rescheduled'], true))
                        ->schema([
                            DateTimePicker::make('confirmed_at')->label('Tour time')->seconds(false)->required(),
                            Select::make('host')->options(array_combine(self::HOSTS, self::HOSTS))->required(),
                        ])
                        ->modalDescription('In production this sends the visitor a confirmation. This preview sends nothing.')
                        ->action(fn () => $this->notifyNothingSaved()),
                    Action::make('propose')
                        ->label('Propose another time')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->visible(fn (array $record): bool => in_array($record['status'], ['requested', 'confirmed'], true))
                        ->schema([
                            DateTimePicker::make('proposed_at')->label('Proposed time')->seconds(false)->required(),
                            Textarea::make('note')->label('Note to the visitor')->rows(2),
                        ])
                        ->action(fn () => $this->notifyNothingSaved()),
                    Action::make('complete')
                        ->label('Mark as completed')
                        ->visible(fn (array $record): bool => $record['status'] === 'confirmed')
                        ->action(fn () => $this->notifyNothingSaved()),
                    Action::make('noShow')
                        ->label('Mark as no-show')
                        ->visible(fn (array $record): bool => $record['status'] === 'confirmed')
                        ->action(fn () => $this->notifyNothingSaved()),
                    Action::make('cancel')
                        ->label('Cancel tour')
                        ->color('danger')
                        ->visible(fn (array $record): bool => in_array($record['status'], ['requested', 'confirmed', 'rescheduled'], true))
                        ->schema([Textarea::make('reason')->required()->rows(2)])
                        ->action(fn () => $this->notifyNothingSaved()),
                ]),
            ])
            ->paginated(false);
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function tours(): array
    {
        return [
            1 => ['reference' => 'TOUR-SAMPLE-201', 'visitor' => 'Sample visitor A', 'party' => '2 people', 'property' => 'Sample property', 'requested' => 'Sample date · Morning', 'confirmed' => null, 'host' => null, 'status' => 'requested'],
            2 => ['reference' => 'TOUR-SAMPLE-202', 'visitor' => 'Sample visitor B', 'party' => '1 person', 'property' => 'Sample property', 'requested' => 'Sample date · Afternoon', 'confirmed' => 'Sample date · 15:00', 'host' => 'Sample host 1', 'status' => 'confirmed'],
            3 => ['reference' => 'TOUR-SAMPLE-203', 'visitor' => 'Sample visitor C', 'party' => '4 people', 'property' => 'Sample property', 'requested' => 'Sample date · Evening', 'confirmed' => null, 'host' => 'Sample host 2', 'status' => 'rescheduled'],
            4 => ['reference' => 'TOUR-SAMPLE-199', 'visitor' => 'Sample visitor D', 'party' => '3 people', 'property' => 'Sample property', 'requested' => 'Sample date · Morning', 'confirmed' => 'Sample date · 11:00', 'host' => 'Sample host 1', 'status' => 'completed'],
            5 => ['reference' => 'TOUR-SAMPLE-198', 'visitor' => 'Sample visitor E', 'party' => '1 person', 'property' => 'Sample property', 'requested' => 'Sample date · Afternoon', 'confirmed' => 'Sample date · 14:30', 'host' => 'Sample host 2', 'status' => 'no_show'],
            6 => ['reference' => 'TOUR-SAMPLE-197', 'visitor' => 'Sample visitor F', 'party' => '2 people', 'property' => 'Sample property', 'requested' => 'Sample date · Morning', 'confirmed' => null, 'host' => null, 'status' => 'cancelled'],
        ];
    }
}
