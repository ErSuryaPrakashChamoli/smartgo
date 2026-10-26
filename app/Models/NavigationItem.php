<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A published menu item: a link to a page (by ID, so a change of address never breaks it), a link to
 * another website, or a group heading whose links are its children (FR-CMS-4).
 */
class NavigationItem extends Model
{
    public const string LINK_PAGE = 'page';

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
