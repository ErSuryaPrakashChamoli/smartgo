<?php

namespace App\Filament\Clusters\Sales\Resources\AssignmentRules\Pages;

use App\Filament\Clusters\Sales\Resources\AssignmentRules\AssignmentRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAssignmentRules extends ListRecords
{
    protected static string $resource = AssignmentRuleResource::class;

    public function getSubheading(): ?string
    {
        return 'New enquiries only. Nothing is assigned by a rule that is off, and no message is sent.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New rule'),
        ];
    }
}
