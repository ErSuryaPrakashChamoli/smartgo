<?php

namespace App\Enums\Access;

use App\Models\User;

/**
 * The six back-office roles defined by the owner (decision OD-33).
 */
enum StaffRole: string
{
    case SuperAdmin = 'super_admin';
    case OperationsManager = 'operations_manager';
    case PropertyManager = 'property_manager';
    case SalesCrmExecutive = 'sales_crm_executive';
    case MarketingManager = 'marketing_manager';
    case Finance = 'finance';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::OperationsManager => 'Operations Manager',
            self::PropertyManager => 'Property Manager',
            self::SalesCrmExecutive => 'Sales/CRM Executive',
            self::MarketingManager => 'Marketing Manager',
            self::Finance => 'Finance',
        };
    }

    /**
     * Capabilities assigned to the role (SECURITY_MODEL §3.1). Every role is listed explicitly,
     * the Super Admin included: a new capability reaches no role until it is added here after
     * review against the matrix (OD-74). The Super Admin holds capabilities rather than a gate
     * bypass, so separation-of-duties checks ("never approve your own work") still apply to it.
     *
     * @return list<Capability>
     */
    public function capabilities(): array
    {
        return match ($this) {
            self::SuperAdmin => [
                Capability::AccessBackoffice, Capability::ManageUsers, Capability::ViewAuditLog,
                Capability::ViewContent, Capability::EditContent, Capability::ApproveContent, Capability::PublishContent,
                Capability::ManageMedia, Capability::PublishTestimonials,
                Capability::ManageContactSettings, Capability::ApproveContactSettings, Capability::PublishContactSettings,
                Capability::ViewLegalSettings, Capability::ManageLegalSettings, Capability::VerifyLegalSettings,
                Capability::ManageRedirects, Capability::ApproveRedirects, Capability::PublishRedirects,
                Capability::ManageBrand, Capability::ApproveBrand, Capability::PublishBrand,
                Capability::ManageTracking, Capability::ApproveTracking, Capability::PublishTracking,
                Capability::ViewProperties, Capability::ManageProperties, Capability::ManageProducts,
                Capability::ViewProfiles, Capability::EditProfiles,
                Capability::ViewLeads, Capability::ManageLeads, Capability::AssignLeads, Capability::ExportLeads, Capability::ManageLeadSettings,
                Capability::ViewTwinModels, Capability::ManageTwinModels,
            ],
            self::OperationsManager => [
                Capability::AccessBackoffice,
                Capability::ViewProperties, Capability::ManageProperties, Capability::ManageProducts,
                Capability::ViewProfiles, Capability::ViewTwinModels,
            ],
            self::MarketingManager => [
                Capability::AccessBackoffice,
                Capability::ViewContent, Capability::EditContent, Capability::ApproveContent, Capability::PublishContent,
                Capability::ManageMedia, Capability::PublishTestimonials,
                Capability::ManageContactSettings, Capability::ApproveContactSettings, Capability::PublishContactSettings,
                Capability::ManageRedirects, Capability::ApproveRedirects, Capability::PublishRedirects,
                Capability::ManageBrand, Capability::ApproveBrand, Capability::PublishBrand,
                Capability::ManageTracking, Capability::ApproveTracking, Capability::PublishTracking,
                Capability::ViewProperties, Capability::ViewProfiles, Capability::EditProfiles,
                Capability::ViewTwinModels, Capability::ManageTwinModels,
            ],
            self::Finance => [
                Capability::AccessBackoffice,
                Capability::ViewLegalSettings, Capability::ManageLegalSettings, Capability::VerifyLegalSettings,
                Capability::ViewProperties,
            ],
            self::PropertyManager => [
                Capability::AccessBackoffice,
                Capability::ViewProperties, Capability::ManageProperties,
                Capability::ViewProfiles, Capability::EditProfiles, Capability::ManageMedia,
                Capability::ViewLeads,
            ],
            self::SalesCrmExecutive => [
                Capability::AccessBackoffice, Capability::ViewProperties, Capability::ViewProfiles,
                Capability::ViewLeads, Capability::ManageLeads, Capability::AssignLeads,
            ],
        };
    }

    /**
     * Whether, for this user, the role's capabilities reach only the properties assigned to them
     * (`property_user`): always for a Property Manager ("Own properties", SECURITY_MODEL §3.1), and for
     * a Sales/CRM Executive unless the Super Admin has recorded that this person works on every property
     * (OD-36's per-user restriction, on by default). A user who also holds a role without this limit
     * has that role's capabilities for every property.
     */
    public function limitsToAssignedProperties(User $user): bool
    {
        return match ($this) {
            self::PropertyManager => true,
            self::SalesCrmExecutive => ! $user->has_all_property_access,
            default => false,
        };
    }

    /**
     * Whether the role can be limited to assigned properties at all, so assignments matter for it.
     */
    public function usesPropertyAssignments(): bool
    {
        return in_array($this, [self::PropertyManager, self::SalesCrmExecutive], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(
            self::values(),
            array_map(fn (self $role): string => $role->label(), self::cases()),
        );
    }
}
