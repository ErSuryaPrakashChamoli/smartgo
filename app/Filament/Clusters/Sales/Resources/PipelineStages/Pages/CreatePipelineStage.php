<?php

namespace App\Filament\Clusters\Sales\Resources\PipelineStages\Pages;

use App\Filament\Clusters\Sales\Resources\PipelineStages\PipelineStageResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePipelineStage extends CreateRecord
{
    protected static string $resource = PipelineStageResource::class;

    protected static bool $canCreateAnother = false;
}
