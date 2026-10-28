<?php

namespace App\Filament\Clusters\Properties;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

/**
 * SmartGro's properties, their cities and the workspace categories they offer (Phase 5). Floors,
 * spaces, capacity and prices join here when real inventory is supplied.
 */
class PropertiesCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 5;

    /**
     * Tabs above the content, so tables keep the full width.
     */
    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
}
