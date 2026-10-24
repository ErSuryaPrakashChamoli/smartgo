<?php

namespace App\Filament\Clusters\DesignPreviews\Pages;

use App\Filament\Clusters\DesignPreviews\Concerns\IsDesignPreview;
use App\Filament\Clusters\DesignPreviews\DesignPreviewsCluster;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Enquiry (lead) management (DATABASE_ERD §6 leads; BR-CRM-1; OD-36). Every row is sample data:
 * none of these people or companies exist, and no message is ever sent from this preview.
 */
class EnquiryManagement extends Page implements HasTable
{
    use InteractsWithTable;
    use IsDesignPreview;

    protected static ?string $cluster = DesignPreviewsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $navigationLabel = 'Enquiries';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Enquiries';

    /**
     * @var array<string, array{label: string, color: string}>
     */
    private const STATUSES = [
        'new' => ['label' => 'New', 'color' => 'warning'],
        'contacted' => ['label' => 'Contacted', 'color' => 'info'],
        'qualified' => ['label' => 'Qualified', 'color' => 'primary'],
        'tour_scheduled' => ['label' => 'Tour scheduled', 'color' => 'primary'],
        'proposal_sent' => ['label' => 'Proposal sent', 'color' => 'primary'],
        'won' => ['label' => 'Won', 'color' => 'success'],
        'lost' => ['label' => 'Lost', 'color' => 'gray'],
        'disqualified' => ['label' => 'Disqualified', 'color' => 'gray'],
    ];

    private const OWNERS = ['Sample sales executive 1', 'Sample sales executive 2'];

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->previewNotice('Shows how enquiries will be handled. No enquiry has been received yet.'),
            Callout::make('Sample data: these are not real customers')
                ->description('Every enquiry, person and company below is invented to show the screen. Assigning, logging follow-ups and changing status save nothing, and no email, SMS or WhatsApp message is ever sent from this preview.')
                ->warning(),
            EmbeddedTable::make(),
            Callout::make('Who can do what (approved matrix)')
                ->description('Sales/CRM Executives and the Super Admin manage all enquiries and assign owners by hand (OD-36, OD-51). Property Managers see enquiries for their properties; Operations and Marketing see totals only; Finance sees deal values. Exports need the Super Admin and are audited.')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color('gray'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Enquiries (sample)')
            ->records(function (array $filters): array {
                $status = $filters['status']['value'] ?? null;

                return collect($this->enquiries())
                    ->when(filled($status), fn ($enquiries) => $enquiries->where('status', $status))
                    ->all();
            })
            ->columns([
                TextColumn::make('reference')->weight(FontWeight::SemiBold)->description(fn (array $record): string => $record['received']),
                TextColumn::make('contact')->description(fn (array $record): string => $record['company']),
                TextColumn::make('interest')->description(fn (array $record): string => 'Source: '.$record['source']),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUSES[$state]['label'])
                    ->color(fn (string $state): string => self::STATUSES[$state]['color']),
                TextColumn::make('owner')->placeholder('Unassigned'),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(self::STATUSES)->map(fn (array $status): string => $status['label'])->all()),
            ])
            ->recordActions([
                Action::make('view')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->modalHeading(fn (array $record): string => $record['reference'].' (sample)')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->schema(fn (array $record): array => [
                        Grid::make(2)->schema([
                            TextEntry::make('contact')->state($record['contact']),
                            TextEntry::make('company')->state($record['company']),
                            TextEntry::make('email')->state($record['email']),
                            TextEntry::make('interest')->state($record['interest']),
                            TextEntry::make('message')->state('Sample message: looking for a workspace for a small team.')->columnSpanFull(),
                        ]),
                        Section::make('Activity (sample)')->compact()->schema([
                            View::make('filament.design-previews.simple-table')->viewData([
                                'caption' => 'Activity history',
                                'columns' => ['When', 'What', 'By'],
                                'rows' => [
                                    [$record['received'], 'Enquiry received from the website', 'System'],
                                    ['Sample time', 'Call: no answer; follow-up planned', 'Sample sales executive 1'],
                                ],
                            ]),
                        ]),
                    ]),
                ActionGroup::make([
                    Action::make('assign')
                        ->label('Assign owner')
                        ->icon(Heroicon::OutlinedUserPlus)
                        ->schema([Select::make('owner')->options(array_combine(self::OWNERS, self::OWNERS))->required()])
                        ->action(fn () => $this->notifyNothingSaved()),
                    Action::make('logFollowUp')
                        ->label('Log follow-up')
                        ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                        ->schema([
                            Select::make('type')->options(['call' => 'Call', 'email' => 'Email', 'whatsapp' => 'WhatsApp', 'meeting' => 'Meeting'])->required(),
                            Textarea::make('summary')->required()->rows(3),
                            DatePicker::make('next_step_on')->label('Next follow-up'),
                        ])
                        ->action(fn () => $this->notifyNothingSaved()),
                    Action::make('changeStatus')
                        ->label('Change status')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->schema([
                            Select::make('status')->options(collect(self::STATUSES)->map(fn (array $status): string => $status['label'])->all())->required(),
                            Textarea::make('reason')->label('Reason (required for lost or disqualified)')->rows(2),
                        ])
                        ->action(fn () => $this->notifyNothingSaved()),
                ]),
            ])
            ->paginated(false);
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function enquiries(): array
    {
        return [
            1 => ['reference' => 'ENQ-SAMPLE-001', 'received' => '12 min ago', 'contact' => 'Sample enquirer A', 'company' => 'Sample company A', 'email' => 'enquirer.a@example.test', 'interest' => 'Private cabins · Noida', 'source' => 'Website form', 'status' => 'new', 'owner' => null],
            2 => ['reference' => 'ENQ-SAMPLE-002', 'received' => '2 h ago', 'contact' => 'Sample enquirer B', 'company' => 'Sample company B', 'email' => 'enquirer.b@example.test', 'interest' => 'Meeting rooms · Noida', 'source' => 'Tour form', 'status' => 'contacted', 'owner' => 'Sample sales executive 1'],
            3 => ['reference' => 'ENQ-SAMPLE-003', 'received' => 'Yesterday', 'contact' => 'Sample enquirer C', 'company' => 'Sample company C', 'email' => 'enquirer.c@example.test', 'interest' => 'Managed offices', 'source' => 'Enterprise form', 'status' => 'qualified', 'owner' => 'Sample sales executive 2'],
            4 => ['reference' => 'ENQ-SAMPLE-004', 'received' => '2 days ago', 'contact' => 'Sample enquirer D', 'company' => 'Independent', 'email' => 'enquirer.d@example.test', 'interest' => 'Hot desks · Noida', 'source' => 'Phone', 'status' => 'tour_scheduled', 'owner' => 'Sample sales executive 1'],
            5 => ['reference' => 'ENQ-SAMPLE-005', 'received' => '3 days ago', 'contact' => 'Sample enquirer E', 'company' => 'Sample company E', 'email' => 'enquirer.e@example.test', 'interest' => 'Virtual office', 'source' => 'Website form', 'status' => 'proposal_sent', 'owner' => 'Sample sales executive 2'],
            6 => ['reference' => 'ENQ-SAMPLE-006', 'received' => 'Last week', 'contact' => 'Sample enquirer F', 'company' => 'Sample company F', 'email' => 'enquirer.f@example.test', 'interest' => 'Gurugram (city interest)', 'source' => 'City waitlist', 'status' => 'lost', 'owner' => 'Sample sales executive 1'],
        ];
    }
}
