<?php

namespace App\Filament\Clusters\Sales\Resources\Leads\Pages;

use App\Actions\Crm\ExportLeads;
use App\Filament\Clusters\Sales\Resources\Leads\LeadResource;
use App\Models\Lead;
use App\Models\User;
use App\Services\Crm\FirstResponseClock;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    /**
     * Inside the back office only (BR-CRM-4's alert is not sent, O-1): how many enquiries the user may see
     * are past the first-response target, once the owner has set one (A-41).
     */
    public function getSubheading(): ?string
    {
        /** @var User $user */
        $user = auth()->user();
        $overdue = count(app(FirstResponseClock::class)->overdueLeadIds($user));

        return $overdue === 0 ? null : $overdue.' '.str('enquiry')->plural($overdue).' past the first-response target. Filter by "First response overdue" to see them.';
    }

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
