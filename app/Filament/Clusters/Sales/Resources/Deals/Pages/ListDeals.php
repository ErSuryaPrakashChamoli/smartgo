<?php

namespace App\Filament\Clusters\Sales\Resources\Deals\Pages;

use App\Enums\Crm\PipelineOutcome;
use App\Filament\Clusters\Sales\Resources\Deals\DealResource;
use App\Models\PipelineStage;
use Filament\Resources\Pages\ListRecords;

class ListDeals extends ListRecords
{
    protected static string $resource = DealResource::class;

    public function getSubheading(): ?string
    {
        return PipelineStage::query()->offered(PipelineOutcome::Open)->exists()
            ? null
            : 'No open pipeline stages are set up yet, so no deal can be opened. The Super Admin adds them in Sales → Pipeline stages.';
    }
}
