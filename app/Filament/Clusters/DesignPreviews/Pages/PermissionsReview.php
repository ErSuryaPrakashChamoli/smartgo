<?php

namespace App\Filament\Clusters\DesignPreviews\Pages;

use App\Enums\Access\StaffRole;
use App\Filament\Clusters\DesignPreviews\Concerns\IsDesignPreview;
use App\Filament\Clusters\DesignPreviews\DesignPreviewsCluster;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\UnorderedList;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

/**
 * Permissions review: view, edit, approve, verify and publish are separate capabilities. Rows follow
 * SECURITY_MODEL §11: the approved matrix (§3.1) and the owner's decisions OD-51 to OD-75. Only
 * backoffice.access, users.manage and audit_log.view exist in code today; the rest are policy, not yet built.
 */
class PermissionsReview extends Page implements HasTable
{
    use InteractsWithTable;
    use IsDesignPreview;

    protected static ?string $cluster = DesignPreviewsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $navigationLabel = 'Permissions review';

    protected static ?int $navigationSort = 15;

    protected static ?string $title = 'Permissions review';

    /**
     * Role keys in column order; values are "Yes", a scope ("Own properties", "Totals", "Not own",
     * "After another's approval") or "—".
     *
     * @var list<string>
     */
    private const ROLES = ['super_admin', 'operations_manager', 'property_manager', 'sales_crm_executive', 'marketing_manager', 'finance'];

    /**
     * Basis of each row: in code, restated from the approved matrix, or decided by the owner but not built.
     *
     * @var array<string, string>
     */
    private const BASES = [
        'implemented' => 'Implemented',
        'matrix' => 'Approved matrix, not built',
        'decided' => 'Owner decision, not built',
    ];

    /**
     * @return list<array{area: string, capability: string, purpose: string, grants: list<string>, basis: string}>
     */
    public static function catalogue(): array
    {
        $row = fn (string $area, string $capability, string $purpose, array $grants, string $basis): array => compact('area', 'capability', 'purpose', 'grants', 'basis');
        $notOwn = 'Not own';
        $afterApproval = "After another's approval";

        return [
            $row('Staff and audit', 'backoffice.access', 'Enter the back office: every protected page, route and API', ['Yes', 'Yes', 'Yes', 'Yes', 'Yes', 'Yes'], 'implemented'),
            $row('Staff and audit', 'users.manage', 'Manage staff, roles and two-step login resets', ['Yes', '—', '—', '—', '—', '—'], 'implemented'),
            $row('Staff and audit', 'audit_log.view', 'Read the audit log', ['Yes', '—', '—', '—', '—', '—'], 'implemented'),
            $row('Pricing', 'pricing.view', 'See prices', ['Yes', 'Yes', 'Own properties', 'Yes', 'Yes', 'Yes'], 'matrix'),
            $row('Pricing', 'pricing.manage', 'Create and edit draft prices', ['Yes', '—', '—', '—', '—', 'Yes'], 'matrix'),
            $row('Pricing', 'pricing.approve', 'Activate prices', [$notOwn, '—', '—', '—', '—', $notOwn], 'matrix'),
            $row('Pricing', 'quotations.manage', 'Prepare enterprise quotations', ['Yes', '—', '—', 'Up to limit', '—', 'Approves'], 'matrix'),
            $row('Enquiries', 'leads.view', 'See enquiries', ['Yes', 'Totals', 'Own properties', 'Yes', 'Totals', 'Deal values'], 'matrix'),
            $row('Enquiries', 'leads.manage', 'Change status, log follow-ups', ['Yes', '—', '—', 'Yes', '—', '—'], 'matrix'),
            $row('Enquiries', 'leads.assign', 'Assign or reassign an owner by hand', ['Yes', '—', '—', 'Yes', '—', '—'], 'decided'),
            $row('Enquiries', 'leads.export', 'Export enquiries (audited)', ['Yes', '—', '—', '—', '—', '—'], 'matrix'),
            $row('Bookings', 'bookings.view', 'See booking requests and bookings', ['Yes', 'Yes', 'Own properties', 'Yes', '—', 'Yes'], 'matrix'),
            $row('Bookings', 'bookings.approve', 'Approve or reject requests, and reschedules that change inventory, price or terms; never one you created or submitted', [$notOwn, $notOwn, 'Own properties, not own', '—', '—', '—'], 'decided'),
            $row('Bookings', 'bookings.cancel', 'Cancel bookings', ['Yes', 'Yes', 'Own properties', 'Yes', '—', '—'], 'matrix'),
            $row('Tours', 'tours.view', 'See tours', ['Yes', 'Yes', 'Own properties', 'Yes', '—', '—'], 'matrix'),
            $row('Tours', 'tours.manage', 'Confirm, reschedule and cancel tours', ['Yes', '—', 'Own properties', 'Yes', '—', '—'], 'matrix'),
            $row('Contact and legal', 'settings.contact.manage', 'Draft changes to public contact details', ['Yes', '—', '—', '—', 'Yes', '—'], 'decided'),
            $row('Contact and legal', 'settings.contact.approve', 'Approve contact-detail changes', [$notOwn, '—', '—', '—', $notOwn, '—'], 'decided'),
            $row('Contact and legal', 'settings.contact.publish', 'Put approved contact details live', [$afterApproval, '—', '—', '—', $afterApproval, '—'], 'decided'),
            $row('Contact and legal', 'settings.legal.view', 'See legal entity and GST details', ['Yes', '—', '—', '—', '—', 'Yes'], 'decided'),
            $row('Contact and legal', 'settings.legal.manage', 'Edit legal entity and GST details', ['Yes', '—', '—', '—', '—', 'Yes'], 'decided'),
            $row('Contact and legal', 'settings.legal.verify', 'Verify legal details against the registration documents', [$notOwn, '—', '—', '—', '—', $notOwn], 'decided'),
            $row('Website content', 'content.view', 'Read drafts and previews', ['Yes', 'Profiles', 'Own properties', 'Profiles', 'Yes', '—'], 'implemented'),
            $row('Website content', 'content.edit', 'Edit drafts', ['Yes', '—', 'Own properties', '—', 'Yes', '—'], 'implemented'),
            $row('Website content', 'content.approve', 'Approve submitted content (one approval is enough)', [$notOwn, '—', '—', '—', $notOwn, '—'], 'implemented'),
            $row('Website content', 'content.publish', 'Publish or schedule an approved version', [$afterApproval, '—', '—', '—', $afterApproval, '—'], 'implemented'),
            $row('Website content', 'testimonials.publish', 'Publish testimonials and case studies (consent on file)', [$afterApproval, '—', '—', '—', $afterApproval, '—'], 'decided'),
            $row('Website content', 'media.manage', 'Upload media with origin and licence', ['Yes', '—', 'Own properties', '—', 'Yes', '—'], 'matrix'),
            $row('Redirects', 'redirects.manage', 'Draft redirect changes', ['Yes', '—', '—', '—', 'Yes', '—'], 'matrix'),
            $row('Redirects', 'redirects.approve', 'Approve redirect changes', [$notOwn, '—', '—', '—', $notOwn, '—'], 'decided'),
            $row('Redirects', 'redirects.publish', 'Put approved redirects live', [$afterApproval, '—', '—', '—', $afterApproval, '—'], 'decided'),
            $row('Brand', 'brand.manage', 'Draft changes to the logo, design-system settings and social links', ['Yes', '—', '—', '—', 'Yes', '—'], 'matrix'),
            $row('Brand', 'brand.approve', 'Approve brand changes', [$notOwn, '—', '—', '—', $notOwn, '—'], 'decided'),
            $row('Brand', 'brand.publish', 'Put approved brand changes live', [$afterApproval, '—', '—', '—', $afterApproval, '—'], 'decided'),
            $row('Tracking', 'settings.tracking.manage', 'Draft changes to tracking IDs, analytics identifiers and advertising pixels', ['Yes', '—', '—', '—', 'Yes', '—'], 'matrix'),
            $row('Tracking', 'settings.tracking.approve', 'Approve tracking changes', [$notOwn, '—', '—', '—', $notOwn, '—'], 'decided'),
            $row('Tracking', 'settings.tracking.publish', 'Make approved tracking changes active on the website', [$afterApproval, '—', '—', '—', $afterApproval, '—'], 'decided'),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->previewNotice('A review of the role design. It changes no permission: the rows marked Implemented exist in code today. The owner\'s other decisions (OD-51 to OD-75) are added by the modules that need them.'),
            EmbeddedTable::make(),
            Callout::make('Safeguards kept in every module')
                ->description('Viewing, editing, approving, verifying and publishing are separate. The author never approves; the approver never publishes that version; the author may publish (OD-65, OD-73). Nobody approves a booking request or reschedule they created or submitted (OD-72), and legal details are verified by someone who did not edit them (OD-62). Every change is audited. There is no Super Admin override: the Super Admin holds only the permissions listed for it.')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color('gray'),
            Callout::make('Owner decisions, not yet built')
                ->description('Decided by the owner on 2026-10-25 and recorded in the decision register. None of them is enforced by code yet.')
                ->info()
                ->footer([
                    UnorderedList::make([
                        Text::make('A permission assigned to a role applies to all its members; individual grants are optional and stay within the role\'s scope. Role membership alone grants nothing (OD-68).'),
                        Text::make('Contact details, redirects, brand settings and tracking IDs go live only after another person approves them (OD-69 to OD-71).'),
                        Text::make('A tag manager is used only if it enforces the same approval, publication and audit (OD-75).'),
                    ]),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        $labels = [
            StaffRole::SuperAdmin->value => 'Super Admin',
            StaffRole::OperationsManager->value => 'Operations',
            StaffRole::PropertyManager->value => 'Property',
            StaffRole::SalesCrmExecutive->value => 'Sales/CRM',
            StaffRole::MarketingManager->value => 'Marketing',
            StaffRole::Finance->value => 'Finance',
        ];

        return $table
            ->heading('Capabilities and the roles that may hold them')
            ->description('Columns are the six owner-defined roles (OD-33); each value is the most a member of that role may be granted (OD-68). "Own properties": only the properties assigned to that user. "Not own": only other people\'s work. "After another\'s approval": only a version someone else approved.')
            ->records(fn (): array => collect(self::catalogue())->mapWithKeys(fn (array $row, int $index): array => [$index + 1 => [
                'area' => $row['area'],
                'capability' => $row['capability'],
                'purpose' => $row['purpose'],
                'basis' => $row['basis'],
                ...array_combine(self::ROLES, $row['grants']),
            ]])->all())
            ->columns([
                TextColumn::make('capability')->fontFamily('mono')->size(TextSize::ExtraSmall)->wrap()
                    ->description(fn (array $record): string => $record['purpose'].' · '.self::BASES[$record['basis']]),
                ...collect(self::ROLES)->map(fn (string $role): TextColumn => TextColumn::make($role)
                    ->label($labels[$role])
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        '—' => 'gray',
                        'Yes' => 'success',
                        default => 'info',
                    }))->all(),
            ])
            ->groups([Group::make('area')->titlePrefixedWithLabel(false)])
            ->defaultGroup('area')
            ->groupingSettingsHidden()
            ->paginated(false);
    }
}
