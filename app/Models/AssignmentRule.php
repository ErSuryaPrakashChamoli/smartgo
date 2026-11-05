<?php

namespace App\Models;

use App\Enums\Crm\AssignmentStrategy;
use Database\Factories\AssignmentRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

/**
 * A rule that gives a new enquiry an owner (FR-CRM-2, BR-CRM-3, DATABASE_ERD §6), kept by the Super Admin
 * (OD-36, SECURITY_MODEL §3.1). It matches an enquiry on what the enquiry records: city, location,
 * workspace type, customer segment, team size and source, each an empty list for "any". The first
 * matching active rule decides, lowest priority number first. None exists or is active by default.
 */
class AssignmentRule extends Model
{
    /** @use HasFactory<AssignmentRuleFactory> */
    use HasFactory;

    /**
     * The tables assignment rules need; a database not migrated yet (the owner's server) has none of them,
     * and enquiries are then left unassigned as before.
     *
     * @var list<string>
     */
    public const array TABLES = ['assignment_rules', 'assignment_rule_user', 'lead_assignments'];

    /**
     * Each criterion and the enquiry column it is matched against.
     *
     * @var array<string, string>
     */
    public const array CRITERIA = [
        'city_ids' => 'city_id',
        'property_ids' => 'property_id',
        'product_ids' => 'product_id',
        'segment_ids' => 'customer_segment_id',
        'team_sizes' => 'team_size',
        'sources' => 'source',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'strategy' => AssignmentStrategy::class,
            'criteria' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public static function isAvailable(): bool
    {
        return once(fn (): bool => collect(self::TABLES)->every(fn (string $table): bool => Schema::hasTable($table)));
    }

    /**
     * The people the rule may give enquiries to, in the Super Admin's order.
     *
     * @return BelongsToMany<User, $this>
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'assignment_rule_user')->withPivot('position')->withTimestamps()->orderByPivot('position');
    }

    /**
     * Who had the last round-robin turn.
     *
     * @return BelongsTo<User, $this>
     */
    public function lastAssignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_assigned_user_id');
    }

    /**
     * @return HasMany<LeadAssignment, $this>
     */
    public function decisions(): HasMany
    {
        return $this->hasMany(LeadAssignment::class);
    }

    /**
     * Whether the enquiry meets every criterion the rule sets. An enquiry without the recorded value (no
     * city, say) does not meet a criterion that lists cities.
     */
    public function matches(Lead $lead): bool
    {
        foreach (self::CRITERIA as $criterion => $column) {
            $values = $this->criteria[$criterion] ?? [];
            $value = $lead->{$column};

            if ($values !== [] && ($value === null || ! in_array(str_ends_with($criterion, '_ids') ? (int) $value : (string) $value, $values, true))) {
                return false;
            }
        }

        return true;
    }
}
