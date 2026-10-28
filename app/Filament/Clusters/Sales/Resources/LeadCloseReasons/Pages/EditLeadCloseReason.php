<?php

namespace App\Filament\Clusters\Sales\Resources\LeadCloseReasons\Pages;

use App\Filament\Clusters\Sales\Resources\LeadCloseReasons\LeadCloseReasonResource;
use Filament\Resources\Pages\EditRecord;

class EditLeadCloseReason extends EditRecord
{
    protected static string $resource = LeadCloseReasonResource::class;
}
