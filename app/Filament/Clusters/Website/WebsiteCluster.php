<?php

namespace App\Filament\Clusters\Website;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

/**
 * Website content managed without code (Phase 4): pages now; navigation, posts, FAQs, media and
 * public settings join in later steps.
 */
class WebsiteCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?int $navigationSort = 10;

    /**
     * Tabs above the content, so content tables keep the full width.
     */
    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
}
