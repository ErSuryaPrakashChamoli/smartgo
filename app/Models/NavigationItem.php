<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Schema;

/**
 * A published menu item: a link to a page, a property or a workspace type (by ID, so a change of address
 * never breaks it), to a fixed part of the website (by `section` key), to another website, or a group heading whose
 * links are its children (FR-CMS-4). Inside a main-menu drop-down an item may also carry a short description
 * and an icon (OD-149).
 */
class NavigationItem extends Model
{
    /**
     * Icons an editor may choose for a drop-down link or category, by Heroicons name (outline style).
     *
     * @var array<string, string>
     */
    public const array ICONS = [
        'computer-desktop' => 'Desk',
        'arrows-right-left' => 'Flexible',
        'squares-2x2' => 'Shared space',
        'building-office' => 'Office',
        'building-office-2' => 'Office building',
        'lock-closed' => 'Private',
        'key' => 'Key',
        'user-group' => 'Meeting',
        'presentation-chart-bar' => 'Presentation',
        'academic-cap' => 'Training',
        'ticket' => 'Pass',
        'calendar-days' => 'Calendar',
        'clock' => 'Time',
        'envelope' => 'Post',
        'map-pin' => 'Location',
        'identification' => 'Registration',
        'document-check' => 'Document',
        'building-library' => 'Institution',
        'briefcase' => 'Business',
        'rectangle-stack' => 'Package',
        'squares-plus' => 'Combination',
        'chat-bubble-left-right' => 'Conversation',
        'sparkles' => 'Highlight',
        'globe-alt' => 'Web',
    ];

    /**
     * Whether the description and icon columns exist: databases that have not run the OD-149 migration keep
     * working, without either.
     */
    public static function hasDetails(): bool
    {
        return once(fn (): bool => Schema::hasColumns((new self)->getTable(), ['description', 'icon']));
    }

    public const string LINK_PAGE = 'page';

    public const string LINK_PROPERTY = 'property';

    public const string LINK_CATEGORY = 'category';

    public const string LINK_SECTION = 'section';

    /** A space type whose listed spaces open as a tab in the drop-down (OD-177). */
    public const string LINK_SPACE_TYPE = 'space_type';

    public const string LINK_EXTERNAL = 'external';

    public const string LINK_GROUP = 'group';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<NavigationMenu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(NavigationMenu::class, 'navigation_menu_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }
}
