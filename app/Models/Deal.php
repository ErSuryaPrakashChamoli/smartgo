<?php

namespace App\Models;

use App\Enums\Access\Capability;
use App\Enums\Crm\PipelineOutcome;
use Database\Factories\DealFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

/**
 * A deal opened from an enquiry (DATABASE_ERD §6, FR-CRM-4): its pipeline stage, the seats, monthly value
 * and expected close date staff type, and the spaces proposed from the canonical inventory. It is won once,
 * creating the proposed spaces' allocations through the inventory action (BR-CRM-7), or lost once with a
 * reason. Seen and worked on by whoever may see and work on its enquiry ("Leads, contacts, deals",
 * SECURITY_MODEL §3.1, BR-CRM-6). Written only by the CRM actions; never deleted.
 */
class Deal extends Model
{
    /** @use HasFactory<DealFactory> */
    use HasFactory;

    /**
     * The tables deals need; a database not migrated yet (the owner's server) has none of them, and the
     * deal screens are then hidden.
     *
     * @var list<string>
     */
    public const array TABLES = ['pipeline_stages', 'deals', 'deal_space'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seats' => 'integer',
            'monthly_value_minor' => 'integer',
            'expected_close_on' => 'immutable_date',
            'won_at' => 'immutable_datetime',
            'lost_at' => 'immutable_datetime',
        ];
    }

    public static function isAvailable(): bool
    {
        return once(fn (): bool => collect(self::TABLES)->every(fn (string $table): bool => Schema::hasTable($table)));
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<PipelineStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'pipeline_stage_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The spaces proposed on the deal, by space.
     *
     * @return HasMany<DealSpace, $this>
     */
    public function proposedSpaces(): HasMany
    {
        return $this->hasMany(DealSpace::class)->orderBy('space_id')->orderBy('id');
    }

    /**
     * The deals whose enquiry the user may see (or work on, for the capability), as for enquiries (BR-CRM-6).
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user, Capability $capability = Capability::ViewLeads): void
    {
        $query->whereHas('lead', fn (Builder $lead) => $lead->visibleTo($user, $capability));
    }

    /**
     * Open, won or lost: a deal is closed by being won or lost, whatever stage it was in.
     */
    public function outcome(): PipelineOutcome
    {
        return match (true) {
            $this->won_at !== null => PipelineOutcome::Won,
            $this->lost_at !== null => PipelineOutcome::Lost,
            default => PipelineOutcome::Open,
        };
    }

    public function isOpen(): bool
    {
        return $this->outcome() === PipelineOutcome::Open;
    }

    /**
     * The deal as staff see it, e.g. "Deal 12 for ENQ-ABCD2345".
     */
    public function title(): string
    {
        return 'Deal '.$this->getKey().' for '.$this->lead->reference;
    }
}
