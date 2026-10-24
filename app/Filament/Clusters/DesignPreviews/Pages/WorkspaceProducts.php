<?php

namespace App\Filament\Clusters\DesignPreviews\Pages;

use App\Enums\Access\StaffRole;
use App\Filament\Clusters\DesignPreviews\Concerns\IsDesignPreview;
use App\Filament\Clusters\DesignPreviews\DesignPreviewsCluster;
use App\Services\Design\DesignPreviewContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Workspace product (category) management: the seven owner-defined categories (OD-07) and the
 * per-category rules of DATABASE_ERD §4.1. Values shown are the proposed defaults, not approved ones.
 *
 * @property-read Schema $form
 */
class WorkspaceProducts extends Page implements HasTable
{
    use InteractsWithTable;
    use IsDesignPreview;

    protected static ?string $cluster = DesignPreviewsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'Workspace products';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Workspace products';

    /**
     * Roles whose permitted scope includes approving booking requests (OD-52). A category's approver
     * role can only narrow approval to one of them (DATABASE_ERD §4.1).
     *
     * @var list<StaffRole>
     */
    private const APPROVER_ROLES = [StaffRole::SuperAdmin, StaffRole::OperationsManager, StaffRole::PropertyManager];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'name' => 'Meeting rooms',
            'slug' => 'meeting-rooms',
            'is_active' => true,
            'is_publicly_listed' => false,
            'inventory_requirement' => 'physical_space',
            'allowed_inventory_modes' => ['exclusive'],
            'space_types' => ['meeting_room', 'conference_training_room'],
            'fulfilment' => 'time_slot',
            'allowed_durations' => ['hourly'],
            'acquisition_workflow' => 'request_then_approve',
            'requires_staff_approval' => true,
            'approver_role' => StaffRole::OperationsManager->value,
            'required_fields' => ['attendee_count'],
            'segments' => [],
        ]);
    }

    public function table(Table $table): Table
    {
        $workflows = ['enquiry_quote' => 'Enquiry and quote', 'request_then_approve' => 'Request, then approve'];

        return $table
            ->heading('Categories')
            ->records(fn (): array => collect(app(DesignPreviewContent::class)->categories())
                ->mapWithKeys(fn (array $category): array => [$category['slug'] => [
                    'name' => $category['name'],
                    'inventory' => $category['slug'] === 'virtual-office' ? 'No physical space' : 'Physical space',
                    'fulfilment' => $category['fulfilment'],
                    'workflow' => $workflows[$category['workflow']],
                    'offerings' => 0,
                    'listed' => 'Not listed',
                ]])
                ->all())
            ->columns([
                TextColumn::make('name')->label('Category')->weight(FontWeight::SemiBold),
                TextColumn::make('inventory'),
                TextColumn::make('fulfilment')->label('How it is sold'),
                TextColumn::make('workflow')->label('Workflow (proposed)')->badge()->color('gray'),
                TextColumn::make('offerings')->label('Active offerings')->numeric(),
                TextColumn::make('listed')->label('Website')->badge()->color('warning'),
            ])
            ->paginated(false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identity')->columns(2)->schema([
                    TextInput::make('name')->required(),
                    TextInput::make('slug')->label('Page address')->prefix('/workspaces/')->required(),
                    Textarea::make('description')->columnSpanFull()->placeholder('Draft copy is written in Phase 6'),
                    Toggle::make('is_active')->label('Active'),
                    Toggle::make('is_publicly_listed')->label('Listed on the website')
                        ->helperText('The website shows a category only while at least one published property offers it (BR-PRODCFG-4).'),
                ]),
                Section::make('Inventory')->columns(2)->schema([
                    Radio::make('inventory_requirement')->label('Needs physical space?')->options(['physical_space' => 'Yes: uses spaces', 'none' => 'No (for example, a virtual office)']),
                    CheckboxList::make('allowed_inventory_modes')->label('Allowed inventory modes')->options([
                        'exclusive' => 'Exclusive: one customer at a time',
                        'per_seat' => 'Individual seats',
                        'pooled' => 'Pooled capacity',
                        'hybrid' => 'Mixed zone',
                    ]),
                    CheckboxList::make('space_types')->label('Space types it can be sold on')->columnSpanFull()->columns(3)->options([
                        'private_cabin' => 'Private cabin / office',
                        'desk_zone_dedicated' => 'Dedicated desk zone',
                        'desk_zone_flexible' => 'Flexible seating zone',
                        'meeting_room' => 'Meeting room',
                        'conference_training_room' => 'Conference / training room',
                        'office_suite' => 'Office suite / floor plate',
                    ]),
                ]),
                Section::make('Selling and workflow')->columns(2)->schema([
                    Select::make('fulfilment')->label('How it is sold')->options([
                        'term_agreement' => 'Monthly or term agreement',
                        'time_slot' => 'Time slots (hourly, half day, day)',
                        'day_pass' => 'Day pass',
                        'service_subscription' => 'Service subscription',
                    ]),
                    CheckboxList::make('allowed_durations')->label('Durations offered')->options([
                        'hourly' => 'Hourly', 'half_day' => 'Half day', 'daily' => 'Daily', 'monthly' => 'Monthly', 'term_months' => 'Term in months',
                    ])->columns(3),
                    Radio::make('acquisition_workflow')->label('How customers get it')->columnSpanFull()->options([
                        'enquiry_quote' => 'Enquiry, then a quote',
                        'request_then_approve' => 'Booking request, confirmed by staff',
                        'instant_booking' => 'Instant online booking',
                    ])->descriptions([
                        'instant_booking' => 'Available only after the online-booking and payment decisions D-07 and D-08 (BR-PRODCFG-5).',
                    ])->disableOptionWhen(fn (string $value): bool => $value === 'instant_booking'),
                    Toggle::make('requires_staff_approval')->label('Staff approval required'),
                    Select::make('approver_role')->label('Approved by')
                        ->options(collect(self::APPROVER_ROLES)->mapWithKeys(fn (StaffRole $role): array => [$role->value => $role->label()])->all())
                        ->helperText('Only roles that may approve booking requests (OD-52). This narrows who approves for the category and never grants approval; nobody approves a request they created or submitted (OD-72).'),
                ]),
                Section::make('Money and policies')->columns(2)
                    ->description('Values are decided at the Phase 5 gate (pricing values PR-1 to PR-11, D-08).')
                    ->schema([
                        Select::make('payment_rule')->options(['none' => 'No payment', 'deposit' => 'Deposit', 'full_prepayment' => 'Full prepayment', 'invoice' => 'Invoice'])->placeholder('Not decided')->disabled(),
                        Select::make('cancellation_policy')->options([])->placeholder('Not decided')->disabled(),
                    ]),
                Section::make('Booking rules')->columns(4)
                    ->description('Proposed defaults, not approved values (BR-BK-7).')
                    ->schema([
                        TextInput::make('min_seats_per_order')->label('Min seats per order')->numeric(),
                        TextInput::make('max_seats_per_order')->label('Max seats per order')->numeric(),
                        TextInput::make('min_notice_minutes')->label('Minimum notice')->numeric()->suffix('min')->placeholder('30'),
                        TextInput::make('max_advance_days')->label('Book up to')->numeric()->suffix('days ahead')->placeholder('60'),
                    ]),
                Section::make('What customers are asked')->columns(2)->schema([
                    CheckboxList::make('required_fields')->label('Required information')->options([
                        'company_name' => 'Company name', 'gstin' => 'GSTIN', 'team_size' => 'Team size', 'move_in_date' => 'Move-in date',
                        'attendee_count' => 'Number of attendees', 'business_documents' => 'Business documents', 'billing_address' => 'Billing address',
                    ]),
                    CheckboxList::make('segments')->label('Relevant customer segments')
                        ->helperText('Used for discovery and reporting only; segments carry no priority (OD-04).')
                        ->options(collect(app(DesignPreviewContent::class)->segments())->mapWithKeys(fn (string $segment): array => [$segment => $segment])->all()),
                ]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->previewNotice('The seven categories are confirmed (OD-07). Their rules show the proposed defaults; nothing here is approved or saved.'),
            EmbeddedTable::make(),
            Section::make('Edit category: Meeting rooms')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->schema([
                    Form::make([EmbeddedSchema::make('form')])
                        ->livewireSubmitHandler('save')
                        ->footer([Actions::make([Action::make('save')->label('Save category')->submit('save')])]),
                ]),
        ]);
    }
}
