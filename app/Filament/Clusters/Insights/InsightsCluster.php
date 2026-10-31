<?php

namespace App\Filament\Clusters\Insights;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

/**
 * Reports for staff (Phase 12, FR-AN-2; the "Insights" cluster of SYSTEM_ARCHITECTURE §3.1, labelled
 * "Reports" so it is not confused with the website's insights articles): totals worked out from the
 * application's own records. No visitor tracking: that stays off (OD-100).
 */
class InsightsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Reports';

    protected static ?string $clusterBreadcrumb = 'Reports';

    protected static ?string $slug = 'reports';

    protected static ?int $navigationSort = 20;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
}
