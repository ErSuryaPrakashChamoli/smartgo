<?php

namespace App\Filament\Clusters\DesignPreviews\Pages;

use App\Filament\Clusters\DesignPreviews\Concerns\IsDesignPreview;
use App\Filament\Clusters\DesignPreviews\DesignPreviewsCluster;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedTable;
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
 * Booking-request review (BR-BK-5, BR-BK-10). Requests are listed apart from confirmed bookings.
 * Approval never bypasses availability: in production it locks the space, re-checks it and only
 * then confirms; a conflict refuses the approval. All rows are sample data on the fictional property.
 */
class BookingRequests extends Page implements HasTable
{
    use InteractsWithTable;
    use IsDesignPreview;

    protected static ?string $cluster = DesignPreviewsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Booking requests';

    protected static ?int $navigationSort = 11;

    protected static ?string $title = 'Booking requests';

    /**
     * @var array<string, array{label: string, color: string}>
     */
    private const STATUSES = [
        'requested' => ['label' => 'Requested: not yet confirmed', 'color' => 'warning'],
        'rejected' => ['label' => 'Rejected', 'color' => 'gray'],
        'cancelled' => ['label' => 'Cancelled by customer', 'color' => 'gray'],
        'not_confirmed' => ['label' => 'Could not confirm: time taken', 'color' => 'danger'],
    ];

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->previewNotice('Sample requests on the fictional Sample property. Approving, rejecting or cancelling creates no booking, sends no message and takes no payment.'),
            EmbeddedTable::make(),
            Section::make('Confirmed bookings')
                ->description('Shown separately: a booking appears here only after approval has passed the availability check.')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->schema([
                    View::make('filament.design-previews.simple-table')->viewData([
                        'caption' => 'Confirmed bookings (sample)',
                        'columns' => ['Reference', 'Space', 'When', 'Customer', 'Status', 'Payment'],
                        'rows' => [
                            ['BKG-SAMPLE-0091', 'Sample training room', 'Sample date · 10:00–13:00', 'Sample customer G', 'Booking confirmed', 'As agreed (rule to be set, D-08)'],
                            ['BKG-SAMPLE-0094', 'Sample meeting room', 'Sample date · 15:00–16:00', 'Sample customer H', 'Booking confirmed', 'As agreed (rule to be set, D-08)'],
                        ],
                    ]),
                ]),
            Callout::make('Approval cannot override availability')
                ->description('In production, approving locks the space, re-reads its bookings and allocations, and confirms only if the time is still free (BR-BK-5, ADR-0004). If not, the request becomes "Could not confirm" and the customer is offered other times. There is no "force approve". Nobody approves a request or reschedule they created or submitted, the Super Admin included (OD-72).')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color('gray'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Requests to review (sample)')
            ->records(fn (): array => $this->requests())
            ->columns([
                TextColumn::make('reference')->weight(FontWeight::SemiBold)->description(fn (array $record): string => $record['customer']),
                TextColumn::make('space')->label('Request')->description(fn (array $record): string => $record['product'].' · '.$record['property']),
                TextColumn::make('when')->label('Requested time')->description(fn (array $record): string => $record['attendees'].' attendees'),
                TextColumn::make('check')->label('Availability now')->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'free' ? 'Free' : 'Conflict')
                    ->color(fn (string $state): string => $state === 'free' ? 'success' : 'danger')
                    ->description(fn (array $record): ?string => $record['check_detail']),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUSES[$state]['label'])
                    ->color(fn (string $state): string => self::STATUSES[$state]['color']),
            ])
            ->recordActions([
                Action::make('review')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->modalHeading(fn (array $record): string => 'Review '.$record['reference'])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->schema(fn (array $record): array => [
                        Grid::make(2)->schema([
                            TextEntry::make('property')->state($record['property']),
                            TextEntry::make('space')->state($record['space']),
                            TextEntry::make('product')->state($record['product']),
                            TextEntry::make('when')->label('Requested time')->state($record['when']),
                            TextEntry::make('attendees')->state((string) $record['attendees']),
                            TextEntry::make('price')->state('Price on request (no price approved yet)'),
                            TextEntry::make('inventory')->label('Inventory context')->state($record['check_detail'] ?? 'No conflicting booking or allocation at the moment.')->columnSpanFull(),
                        ]),
                    ]),
                Action::make('approve')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (array $record): bool => $record['status'] === 'requested')
                    ->requiresConfirmation()
                    ->modalHeading(fn (array $record): string => 'Approve '.$record['reference'].'?')
                    ->modalDescription('Approval re-checks availability under lock before confirming. If the time is no longer free, the request is not approved.')
                    ->action(fn (array $record) => $this->approve($record)),
                Action::make('reject')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->visible(fn (array $record): bool => $record['status'] === 'requested')
                    ->schema([
                        Select::make('reason')->options([
                            'unavailable' => 'Space not available',
                            'outside_hours' => 'Outside opening hours',
                            'capacity' => 'Too many attendees for the room',
                            'other' => 'Other',
                        ])->required(),
                        Textarea::make('note')->label('Note to the customer')->rows(3),
                    ])
                    ->action(fn () => $this->notifyNothingSaved()),
                Action::make('cancel')
                    ->label('Cancel request')
                    ->color('gray')
                    ->visible(fn (array $record): bool => $record['status'] === 'requested')
                    ->requiresConfirmation()
                    ->action(fn () => $this->notifyNothingSaved()),
            ])
            ->paginated(false);
    }

    /**
     * Mirrors the production rule: a conflict refuses the approval; otherwise the preview stores nothing.
     *
     * @param  array<string, mixed>  $record
     */
    private function approve(array $record): void
    {
        if ($record['check'] !== 'free') {
            Notification::make()
                ->danger()
                ->title('Not approved: the space is no longer free')
                ->body('Approval re-checks availability under lock and cannot override a conflict (BR-BK-5).')
                ->send();

            return;
        }

        $this->notifyNothingSaved();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function requests(): array
    {
        return [
            1 => ['reference' => 'REQ-SAMPLE-101', 'product' => 'Meeting rooms', 'property' => 'Sample property', 'space' => 'Sample meeting room', 'when' => 'Sample date · 11:00–12:00', 'attendees' => 4, 'customer' => 'Sample customer A', 'check' => 'free', 'check_detail' => null, 'status' => 'requested'],
            2 => ['reference' => 'REQ-SAMPLE-102', 'product' => 'Conference and training rooms', 'property' => 'Sample property', 'space' => 'Sample training room', 'when' => 'Sample date · 10:00–13:00', 'attendees' => 18, 'customer' => 'Sample customer B', 'check' => 'conflict', 'check_detail' => 'Overlaps confirmed booking BKG-SAMPLE-0091', 'status' => 'requested'],
            3 => ['reference' => 'REQ-SAMPLE-103', 'product' => 'Hot desks', 'property' => 'Sample property', 'space' => 'Sample flexible zone', 'when' => 'Sample date · full day', 'attendees' => 2, 'customer' => 'Sample customer C', 'check' => 'free', 'check_detail' => '9 of 40 seats free (pooled)', 'status' => 'requested'],
            4 => ['reference' => 'REQ-SAMPLE-098', 'product' => 'Meeting rooms', 'property' => 'Sample property', 'space' => 'Sample meeting room', 'when' => 'Sample date · 20:00–21:00', 'attendees' => 6, 'customer' => 'Sample customer D', 'check' => 'free', 'check_detail' => null, 'status' => 'rejected'],
            5 => ['reference' => 'REQ-SAMPLE-097', 'product' => 'Meeting rooms', 'property' => 'Sample property', 'space' => 'Sample meeting room', 'when' => 'Sample date · 09:00–10:00', 'attendees' => 3, 'customer' => 'Sample customer E', 'check' => 'free', 'check_detail' => null, 'status' => 'cancelled'],
            6 => ['reference' => 'REQ-SAMPLE-095', 'product' => 'Conference and training rooms', 'property' => 'Sample property', 'space' => 'Sample training room', 'when' => 'Sample date · 10:00–13:00', 'attendees' => 12, 'customer' => 'Sample customer F', 'check' => 'conflict', 'check_detail' => 'Taken by BKG-SAMPLE-0091 before review', 'status' => 'not_confirmed'],
        ];
    }
}
