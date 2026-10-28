<?php

namespace App\Filament\Clusters\Sales\Resources\LeadCloseReasons\Pages;

use App\Filament\Clusters\Sales\Resources\LeadCloseReasons\LeadCloseReasonResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLeadCloseReason extends CreateRecord
{
    protected static string $resource = LeadCloseReasonResource::class;

    protected static bool $canCreateAnother = false;
}
