<?php

namespace App\Filament\Clusters\Website;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

/**
 * Website content managed without code (Phase 4): pages, navigation, media, insights, customer
 * stories, testimonials and FAQs; public settings join in step 4.6.
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
