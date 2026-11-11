<?php

namespace App\Models;

use App\Enums\Access\Capability;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\PublishesRevisionFields;
use App\Models\Contracts\Unpublishable;
use App\Services\Content\ContentRules;
use Closure;
use Database\Factories\HomeHighlightFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * One highlight in the moving strip under the home page's banners (OD-169), in `sort_order`: a short line with an
 * icon. Example text that SmartGro has not confirmed is marked `is_sample`: the strip then says "Example highlights",
 * and it can be submitted and published only where sample content is allowed (OD-149), never on the owner's server
 * as configured (OD-152). Versions are approved and published by different people with the website-content
 * permissions; a highlight can be taken off the website at once (OD-98). A database not migrated for highlights
 * shows facts from the records instead (isAvailable()).
 */
class HomeHighlight extends Model implements Unpublishable
{
    /** @use HasFactory<HomeHighlightFactory> */
    use HasFactory, HasRevisions, PublishesRevisionFields;

    /**
     * Icons an editor can choose, as Heroicon names => what they suggest.
     *
     * @var array<string, string>
     */
    public const array ICONS = [
        'sparkles' => 'Sparkle (general)',
        'wifi' => 'Wi-Fi',
        'clock' => 'Clock (hours, access)',
        'key' => 'Key (access)',
        'lock-closed' => 'Lock (security)',
        'shield-check' => 'Shield (safety)',
        'bolt' => 'Bolt (power)',
        'presentation-chart-bar' => 'Presentation (meeting rooms)',
        'user-group' => 'People (teams, community)',
        'building-office-2' => 'Building (offices)',
        'map-pin' => 'Map pin (location)',
        'truck' => 'Vehicle (parking, transport)',
        'printer' => 'Printer',
        'computer-desktop' => 'Screen (desks, equipment)',
        'chat-bubble-left-right' => 'Chat (reception, support)',
        'sun' => 'Sun (daylight)',
        'heart' => 'Heart (wellbeing)',
        'cake' => 'Cake (events, pantry)',
        'calendar-days' => 'Calendar (bookings)',
        'check-circle' => 'Tick',
    ];

    public const string DEFAULT_ICON = 'sparkles';

    /**
     * The strip shows at most this many highlights, the first by position.
     */
    public const int MAX_SHOWN = 24;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_sample' => 'boolean', 'sort_order' => 'integer', 'published_at' => 'datetime'];
    }

    /**
     * Whether this database has highlights. Asked once a request.
     */
    public static function isAvailable(): bool
    {
        return once(fn (): bool => Schema::hasTable((new self)->getTable()));
    }

    public function contentLabel(): string
    {
        return 'highlight';
    }

    public function revisionCapabilities(): array
    {
        return ['view' => Capability::ViewContent, 'edit' => Capability::EditContent, 'approve' => Capability::ApproveContent, 'publish' => Capability::PublishContent];
    }

    protected function revisionFields(): array
    {
        return ['text', 'icon', 'is_sample', 'sort_order'];
    }

    protected function revisionRules(array $payload, bool $forSubmission): array
    {
        $rules = app(ContentRules::class);

        return [
            'rules' => [
                'text' => $rules->plainText($forSubmission, 80),
                'icon' => ['nullable', 'string', Rule::in(array_keys(self::ICONS))],
                'is_sample' => ['nullable', 'boolean', ...($forSubmission ? [$this->sampleAllowedRule()] : [])],
                'sort_order' => ['nullable', 'integer', 'between:0,9999'],
            ],
            'messages' => [...$rules->messages(), 'icon.in' => 'Choose an icon from the list.'],
        ];
    }

    protected function normalizeRevisionPayload(array $payload): array
    {
        return [
            ...$payload,
            'icon' => filled($payload['icon'] ?? null) ? $payload['icon'] : self::DEFAULT_ICON,
            'is_sample' => (bool) ($payload['is_sample'] ?? false),
            'sort_order' => (int) ($payload['sort_order'] ?? 0),
        ];
    }

    /**
     * Example text can be submitted and published only where sample content is allowed (OD-149). Drafts keep the
     * mark, so they can be finished.
     */
    private function sampleAllowedRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ((bool) $value && ! config('backoffice.sample_content')) {
                $fail('This website does not publish example text. Write the highlight in SmartGro\'s own words and untick "Example text" first.');
            }
        };
    }
}
