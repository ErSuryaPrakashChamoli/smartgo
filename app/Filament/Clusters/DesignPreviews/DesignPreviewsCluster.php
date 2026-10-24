<?php

namespace App\Filament\Clusters\DesignPreviews;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

/**
 * Phase 2 back-office design previews: real Filament screens with sample or confirmed data that
 * save nothing. Visible only to the Super Admin and never in production; the Phase 5 resources
 * replace them.
 */
class DesignPreviewsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?string $navigationLabel = 'Design previews';

    protected static ?int $navigationSort = 200;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
}
