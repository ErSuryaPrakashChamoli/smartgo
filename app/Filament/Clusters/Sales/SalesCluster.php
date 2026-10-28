<?php

namespace App\Filament\Clusters\Sales;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

/**
 * Enquiries from the website (Phase 5): the inbox, ownership, status, notes and follow-ups. Tours,
 * bookings and the full CRM pipeline join here in later phases.
 */
class SalesCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = 6;

    /**
     * Tabs above the content, so tables keep the full width.
     */
    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
}
