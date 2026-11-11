<?php

namespace App\Models;

use App\Enums\Crm\PipelineOutcome;
use Database\Factories\PipelineStageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A stage of the sales pipeline (DATABASE_ERD §6, FR-CRM-4), kept by the Super Admin (OD-36, SECURITY_MODEL
 * §3.1 "Assignment rules, pipeline stages"). Nothing is preset (D-09): until an open stage exists no deal can
 * be opened, and until a won or lost stage exists none can be won or lost. A stage's outcome is fixed once a
 * deal has used it, and a stage is switched off rather than deleted, so past deals keep it. Audited.
 */
class PipelineStage extends Model
{
    /** @use HasFactory<PipelineStageFactory> */
    use HasFactory, LogsActivity;

    /**
     * The fields the back-office form sets.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'sort_order', 'probability_pct', 'outcome', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => PipelineOutcome::class,
            'sort_order' => 'integer',
            'probability_pct' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (PipelineStage $stage): void {
            if ($stage->isDirty('outcome') && $stage->deals()->exists()) {
                throw ValidationException::withMessages(['outcome' => 'Deals already use this stage, so whether it means open, won or lost stays as it is.']);
            }
        });
    }

    /**
     * @return HasMany<Deal, $this>
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    /**
     * The active stages with the given outcome, in the Super Admin's order.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOffered(Builder $query, PipelineOutcome $outcome): void
    {
        $query->where('outcome', $outcome->value)->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    /**
     * The stage as staff see it, e.g. "Proposal (40%)".
     */
    public function displayName(): string
    {
        return $this->name.($this->probability_pct !== null ? ' ('.$this->probability_pct.'%)' : '');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'sort_order', 'probability_pct', 'outcome', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('leads');
    }
}
