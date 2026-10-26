<?php

namespace App\Enums\Access;

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
            self::SuperAdmin => [Capability::AccessBackoffice, Capability::ManageUsers, Capability::ViewAuditLog],
            default => [Capability::AccessBackoffice],
        };
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
