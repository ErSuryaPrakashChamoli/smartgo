<?php

namespace App\Models;

use App\Enums\Crm\LeadStatus;
use Database\Factories\LeadCloseReasonFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A reason from the managed lists for marking an enquiry lost or disqualified (BR-CRM-5, FR-CRM-5).
 * The Super Admin keeps the lists; nothing is preset, so until a reason exists for a status, no
 * enquiry can be given that status. Switched off rather than deleted, so closed enquiries keep their
 * reason. Changes are audited.
 */
class LeadCloseReason extends Model
{
    /** @use HasFactory<LeadCloseReasonFactory> */
    use HasFactory, LogsActivity;

    /**
     * The fields the back-office form sets; every other write goes through forceFill in an action.
     *
     * @var list<string>
     */
    protected $fillable = ['status', 'label', 'sort_order', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => LeadStatus::class, 'is_active' => 'boolean'];
    }

    /**
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'close_reason_id');
    }

    /**
     * The reasons staff can choose for the given closing status, in their set order.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOfferedFor(Builder $query, LeadStatus $status): void
    {
        $query->where('status', $status->value)->where('is_active', true)->orderBy('sort_order')->orderBy('label');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'label', 'sort_order', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('leads');
    }
}
