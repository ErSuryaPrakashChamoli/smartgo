<?php

namespace App\Filament\Clusters\Sales\Resources\PipelineStages\Pages;

use App\Filament\Clusters\Sales\Resources\PipelineStages\PipelineStageResource;
use Filament\Resources\Pages\EditRecord;

class EditPipelineStage extends EditRecord
{
    protected static string $resource = PipelineStageResource::class;
}
