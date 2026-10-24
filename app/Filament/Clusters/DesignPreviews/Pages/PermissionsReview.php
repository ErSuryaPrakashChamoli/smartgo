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
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

/**
 * Permissions review for back-office items 8 to 14: view, edit, approve and publish are separate
 * capabilities. Rows come from the approved matrix (SECURITY_MODEL §3.1) unless marked as needing
 * owner confirmation; only users.manage and audit_log.view exist in code today (SECURITY_MODEL §11).
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
     * Role keys in column order; values are "Yes", a scope ("Own properties", "Totals", "Not own") or "—".
     *
     * @var list<string>
     */
    private const ROLES = ['super_admin', 'operations_manager', 'property_manager', 'sales_crm_executive', 'marketing_manager', 'finance'];

    /**
     * @return list<array{area: string, capability: string, purpose: string, grants: list<string>, basis: string}>
     */
    public static function catalogue(): array
    {
        $row = fn (string $area, string $capability, string $purpose, array $grants, string $basis): array => compact('area', 'capability', 'purpose', 'grants', 'basis');

        return [
            $row('Staff and audit', 'users.manage', 'Manage staff, roles and two-step login resets', ['Yes', '—', '—', '—', '—', '—'], 'implemented'),
            $row('Staff and audit', 'audit_log.view', 'Read the audit log', ['Yes', '—', '—', '—', '—', '—'], 'implemented'),
            $row('Pricing', 'pricing.view', 'See prices', ['Yes', 'Yes', 'Own properties', 'Yes', 'Yes', 'Yes'], 'matrix'),
            $row('Pricing', 'pricing.manage', 'Create and edit draft prices', ['Yes', '—', '—', '—', '—', 'Yes'], 'matrix'),
            $row('Pricing', 'pricing.approve', 'Activate prices', ['Yes', '—', '—', '—', '—', 'Not own'], 'matrix'),
            $row('Pricing', 'quotations.manage', 'Prepare enterprise quotations', ['Yes', '—', '—', 'Up to limit', '—', 'Approves'], 'matrix'),
            $row('Enquiries', 'leads.view', 'See enquiries', ['Yes', 'Totals', 'Own properties', 'Yes', 'Totals', 'Deal values'], 'matrix'),
            $row('Enquiries', 'leads.manage', 'Change status, log follow-ups', ['Yes', '—', '—', 'Yes', '—', '—'], 'matrix'),
            $row('Enquiries', 'leads.assign', 'Assign an owner by hand', ['Yes', '—', '—', 'Yes?', '—', '—'], 'confirm'),
            $row('Enquiries', 'leads.export', 'Export enquiries (audited)', ['Yes', '—', '—', '—', '—', '—'], 'matrix'),
            $row('Bookings', 'bookings.view', 'See booking requests and bookings', ['Yes', 'Yes', 'Own properties', 'Yes', '—', 'Yes'], 'matrix'),
            $row('Bookings', 'bookings.approve', 'Approve or reject booking requests', ['Yes', 'Yes?', 'Own properties?', 'Yes?', '—', '—'], 'confirm'),
            $row('Bookings', 'bookings.cancel', 'Cancel bookings', ['Yes', 'Yes', 'Own properties', 'Yes', '—', '—'], 'matrix'),
            $row('Tours', 'tours.view', 'See tours', ['Yes', 'Yes', 'Own properties', 'Yes', '—', '—'], 'matrix'),
            $row('Tours', 'tours.manage', 'Confirm, reschedule and cancel tours', ['Yes', '—', 'Own properties', 'Yes', '—', '—'], 'matrix'),
            $row('Contact and legal', 'settings.contact.manage', 'Edit public contact details', ['Yes', '—', '—', '—', 'Yes?', '—'], 'confirm'),
            $row('Contact and legal', 'settings.legal.view', 'See legal entity and GST details', ['Yes', '—', '—', '—', '—', 'Yes'], 'confirm'),
            $row('Contact and legal', 'settings.legal.manage', 'Edit legal entity and GST details', ['Yes', '—', '—', '—', '—', 'Yes'], 'confirm'),
            $row('Contact and legal', 'settings.legal.verify', 'Verify legal details', ['Yes', '—', '—', '—', '—', 'Not own'], 'confirm'),
            $row('Website content', 'content.view', 'Read drafts and previews', ['Yes', 'Profiles', 'Own properties', 'Profiles', 'Yes', '—'], 'matrix'),
            $row('Website content', 'content.edit', 'Edit drafts', ['Yes', '—', 'Own properties', '—', 'Yes', '—'], 'matrix'),
            $row('Website content', 'content.approve', 'Approve submitted content', ['Yes', '—', '—', '—', 'Not own', '—'], 'matrix'),
            $row('Website content', 'content.publish', 'Publish or schedule', ['Yes', '—', '—', '—', 'Yes?', '—'], 'confirm'),
            $row('Website content', 'testimonials.publish', 'Publish testimonials and case studies (consent on file)', ['Yes', '—', '—', '—', 'Not own', '—'], 'matrix'),
            $row('Website content', 'media.manage', 'Upload media with origin and licence', ['Yes', '—', 'Own properties', '—', 'Yes', '—'], 'matrix'),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->previewNotice('A review of the role design for the new back-office areas. It changes no permission: only users.manage and audit_log.view exist in code today; the rest are added by the module that needs them.'),
            EmbeddedTable::make(),
            Callout::make('Safeguards kept in every module')
                ->description('Viewing, editing, approving and publishing are separate. Approvals are by a different person from the author ("Not own"). Legal and GST details, price activation and publishing are limited to the roles above. Every change is audited. The Super Admin holds each capability explicitly, so the "Not own" rules still apply to the Super Admin.')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color('gray'),
            Callout::make('Needs owner confirmation')
                ->description('These rows are proposals: the approved matrix does not settle them.')
                ->warning()
                ->footer([
                    UnorderedList::make([
                        Text::make('Who may assign an enquiry owner by hand: only the Super Admin, or Sales/CRM Executives too?'),
                        Text::make('Which roles approve booking requests: Operations Manager, Property Managers for their own properties, Sales/CRM Executives, or a subset (set per workspace type)?'),
                        Text::make('Who edits the public contact details: the Super Admin only, or the Marketing Manager too?'),
                        Text::make('Legal entity and GST: proposed Super Admin and Finance edit, a different one of them verifies.'),
                        Text::make('Publishing: may the Marketing Manager publish after approval, or only the Super Admin (single or double approval, D-17)?'),
                        Text::make('How many people hold each role: with one person per role, the Super Admin is the second approver.'),
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
            ->heading('Proposed capabilities for back-office items 8 to 14')
            ->description('Columns are the six owner-defined roles (OD-33). "Own properties" means only the properties assigned to that user.')
            ->records(fn (): array => collect(self::catalogue())->mapWithKeys(fn (array $row, int $index): array => [$index + 1 => [
                'area' => $row['area'],
                'capability' => $row['capability'],
                'purpose' => $row['purpose'],
                'basis' => $row['basis'],
                ...array_combine(self::ROLES, $row['grants']),
            ]])->all())
            ->columns([
                TextColumn::make('capability')->fontFamily('mono')->wrap()->description(fn (array $record): string => $record['purpose']),
                ...collect(self::ROLES)->map(fn (string $role): TextColumn => TextColumn::make($role)
                    ->label($labels[$role])
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        $state === '—' => 'gray',
                        str_ends_with($state, '?') => 'warning',
                        $state === 'Yes' => 'success',
                        default => 'info',
                    }))->all(),
                TextColumn::make('basis')->badge()
                    ->formatStateUsing(fn (string $state): string => ['implemented' => 'Implemented', 'matrix' => 'Approved matrix', 'confirm' => 'Needs confirmation'][$state])
                    ->color(fn (string $state): string => ['implemented' => 'success', 'matrix' => 'primary', 'confirm' => 'warning'][$state]),
            ])
            ->groups([Group::make('area')->titlePrefixedWithLabel(false)])
            ->defaultGroup('area')
            ->groupingSettingsHidden()
            ->paginated(false);
    }
}
