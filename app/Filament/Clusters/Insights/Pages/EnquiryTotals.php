<?php

namespace App\Filament\Clusters\Insights\Pages;

use App\Enums\Access\Capability;
use App\Filament\Clusters\Insights\InsightsCluster;
use App\Models\Property;
use App\Models\User;
use App\Services\Reports\EnquiryReport;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Enquiry and tour totals (FR-AN-2's enquiry and tour part, OD-132): how many enquiries arrived in a
 * period, by day or week, status, source, location and campaign; how long the first recorded contact
 * took; how many led to a tour; the current workload; and, for staff who see tours, the tours requested
 * and taking place. Counts only, never a visitor's details, within the user's property scope
 * (`leads.reports.view`). Every definition is stated on the page. No target or threshold is applied to
 * response times: none is decided (D-09).
 */
class EnquiryTotals extends Page
{
    protected static ?string $cluster = InsightsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $navigationLabel = 'Enquiries and tours';

    protected static ?string $title = 'Enquiries and tours';

    protected static ?string $slug = 'enquiries-and-tours';

    protected static ?int $navigationSort = 1;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $filters = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Capability::ViewLeadReports) ?? false;
    }

    public function mount(): void
    {
        $today = CarbonImmutable::now((string) config('backoffice.timezone'));
        $this->form->fill(['from' => $today->subDays(29)->toDateString(), 'to' => $today->toDateString(), 'property' => null]);
    }

    public function getSubheading(): ?string
    {
        return 'Totals from SmartGro\'s own records, in India time. Visitor numbers are not available: tracking is off (OD-100).';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'sm' => 3])->schema([
                    DatePicker::make('from')->label('From')->required()->live(),
                    DatePicker::make('to')->label('To (included)')->required()->live(),
                    Select::make('property')
                        ->label('Location')
                        ->placeholder('All locations you work on')
                        ->options(fn (): array => Property::query()->visibleTo($this->user(), Capability::ViewLeadReports)->orderBy('code')->get()
                            ->mapWithKeys(fn (Property $property): array => [$property->getKey() => 'SmartGro '.$property->name])->all())
                        ->live(),
                ]),
            ])
            ->statePath('filters');
    }

    public function content(Schema $schema): Schema
    {
        $problem = $this->problem();

        return $schema->components([
            Form::make([EmbeddedSchema::make('form')]),
            ...($problem !== null ? [Callout::make('Choose another period')->description($problem)->warning()] : $this->report()),
        ]);
    }

    /**
     * @return list<mixed>
     */
    private function report(): array
    {
        $propertyId = filled($this->filters['property'] ?? null) ? (int) $this->filters['property'] : null;
        $report = app(EnquiryReport::class)->build($this->user(), CarbonImmutable::parse($this->filters['from']), CarbonImmutable::parse($this->filters['to']), $propertyId);
        $contact = $report['first_contact'];
        $table = fn (string $heading, array $rows, string $caption, string $empty = 'None in this period.'): View => View::make('filament.reports.breakdown')
            ->viewData(['heading' => $heading, 'rows' => $rows, 'caption' => $caption, 'empty' => $empty]);
        $figure = fn (string $label, string $value, string $help): Section => Section::make($label)->compact()->schema([Text::make($value)->weight('bold')->size('lg'), Text::make($help)->color('gray')]);

        $sections = [
            Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])->schema([
                $figure('Enquiries received', number_format($report['received']), 'Created in the period, whatever their status now.'),
                $figure('Led to a tour', isset($report['led_to_tour']) ? number_format($report['led_to_tour']) : '—', isset($report['led_to_tour']) ? 'Of those, with at least one tour, whatever its status.' : 'Shown to staff who see tours.'),
                $figure('Median time to first contact', $this->duration($contact['median_minutes']), $contact['contacted'].' contacted; 90% within '.$this->duration($contact['p90_minutes']).'. No target is set (D-09).'),
                $figure('Not contacted yet', number_format($contact['not_contacted']), 'No call, email, WhatsApp or meeting recorded. A status change alone is not a contact.'),
            ]),
            Section::make('Enquiries received over time')->description($report['over_time_by'] === 'week' ? 'Per week, Monday to Sunday.' : 'Per day.')->schema([
                $table($report['over_time_by'] === 'week' ? 'Week' : 'Day', $report['over_time'], 'Enquiries received over time'),
            ]),
            Grid::make(['default' => 1, 'lg' => 2])->schema([
                Section::make('By status now')->schema([$table('Status', $report['by_status'], 'Enquiries by status')]),
                Section::make('By source')->schema([$table('Source', $report['by_source'], 'Enquiries by source')]),
                Section::make('By location')->schema([$table('Location', $report['by_location'], 'Enquiries by location')]),
                Section::make('By campaign')->description('The campaign source in the address the visitor came from (utm_source), as recorded with the enquiry.')
                    ->schema([$table('Campaign source', $report['by_campaign'], 'Enquiries by campaign')]),
            ]),
            Section::make('Workload now')->description('As of now, not for the period.')->schema([
                $table('Item', [
                    ['label' => 'Open enquiries without an owner', 'total' => $report['workload']['unassigned_open']],
                    ['label' => 'Follow-ups overdue', 'total' => $report['workload']['follow_ups_overdue']],
                    ['label' => 'Follow-ups due later today', 'total' => $report['workload']['follow_ups_due_today']],
                ], 'Workload now'),
            ]),
        ];

        if ($report['tours'] !== null) {
            $tours = $report['tours'];
            $sections[] = Grid::make(['default' => 1, 'lg' => 2])->schema([
                Section::make('Tours requested or arranged: '.number_format($tours['requested']))->description('Created in the period.')->schema([
                    $table('Status now', $tours['requested_by_status'], 'Tours requested by status'),
                    $table('How', $tours['requested_by_channel'], 'Tours requested by channel'),
                    $table('Location', $tours['requested_by_location'], 'Tours requested by location'),
                ]),
                Section::make('Tours taking place: '.number_format($tours['taking_place']))->description('Starting in the period.')->schema([
                    $table('Status', $tours['taking_place_by_status'], 'Tours taking place by status'),
                ]),
            ]);
        }

        $sections[] = Section::make('Not available yet')->collapsible()->collapsed()->icon(Heroicon::OutlinedInformationCircle)->schema([
            Text::make('Website visits: tracking stays off until the consent approach is agreed (OD-100).'),
            Text::make('Deals: the sales pipeline is not built yet (FR-CRM-4).'),
            Text::make('Occupancy and availability by location: they wait for each property\'s real inventory (the inventory entry gate).'),
            Text::make('Response targets: none is decided (D-09), so times are shown as observed.'),
        ]);

        return $sections;
    }

    /**
     * Why the period cannot be shown, or null.
     */
    private function problem(): ?string
    {
        $from = $this->filters['from'] ?? null;
        $to = $this->filters['to'] ?? null;

        return match (true) {
            blank($from) || blank($to) => 'Choose the first and the last day.',
            CarbonImmutable::parse($from)->greaterThan(CarbonImmutable::parse($to)) => 'The first day must not be after the last day.',
            CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) >= EnquiryReport::MAX_DAYS => 'Choose at most '.EnquiryReport::MAX_DAYS.' days.',
            default => null,
        };
    }

    private function duration(?int $minutes): string
    {
        return match (true) {
            $minutes === null => '—',
            $minutes < 60 => $minutes.' min',
            $minutes < 1440 => intdiv($minutes, 60).' h '.($minutes % 60).' min',
            default => intdiv($minutes, 1440).' d '.intdiv($minutes % 1440, 60).' h',
        };
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
