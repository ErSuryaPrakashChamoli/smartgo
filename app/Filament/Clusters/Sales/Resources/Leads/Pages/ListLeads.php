<?php

namespace App\Filament\Clusters\Sales\Resources\Leads\Pages;

use App\Actions\Crm\ExportLeads;
use App\Filament\Clusters\Sales\Resources\Leads\LeadResource;
use App\Models\Lead;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn (): bool => Gate::allows('export', Lead::class))
                ->requiresConfirmation()
                ->modalDescription('The file contains personal details of the enquiries in the current list, with its filters. The export is recorded in the audit log.')
                ->action(function (ExportLeads $exportLeads): StreamedResponse {
                    /** @var User $user */
                    $user = auth()->user();

                    return $exportLeads->handle($user, $this->getFilteredTableQuery());
                }),
        ];
    }
}
