<?php

namespace App\Actions\Crm;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports enquiries as CSV for staff allowed to export every property's enquiries (FR-CRM-6: the Super
 * Admin), audited with the number of rows. The rows are those of the given query, already narrowed to
 * the actor's scope and filters. Cells that a spreadsheet would read as a formula are prefixed with an
 * apostrophe, so an enquiry cannot inject one.
 */
class ExportLeads
{
    /**
     * @param  Builder<Lead>  $query
     *
     * @throws AuthorizationException
     */
    public function handle(User $actor, Builder $query): StreamedResponse
    {
        Gate::forUser($actor)->authorize('export', Lead::class);

        $leads = (clone $query)->with(['property', 'product', 'city', 'owner', 'segment', 'closeReason'])->orderBy('id')->get();

        activity('leads')
            ->causedBy($actor)
            ->event('leads_exported')
            ->withProperties(['rows' => $leads->count()])
            ->log('Enquiries exported');

        return response()->streamDownload(function () use ($leads): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['Reference', 'Received', 'Status', 'Closing reason', 'Name', 'Email', 'Phone', 'Company', 'Location', 'Workspace type', 'City', 'Team size', 'Description', 'Message', 'Owner', 'First response', 'Source'], escape: '');

            foreach ($leads as $lead) {
                fputcsv($output, array_map($this->safeCell(...), [
                    $lead->reference,
                    $lead->created_at?->timezone(config('backoffice.timezone'))->format('Y-m-d H:i'),
                    $lead->status->label(),
                    $lead->closeReason?->label,
                    $lead->submitted_name,
                    $lead->submitted_email,
                    $lead->submitted_phone,
                    $lead->submitted_company,
                    $lead->property?->name,
                    $lead->product?->name,
                    $lead->city?->name,
                    $lead->team_size,
                    $lead->segment?->name,
                    $lead->message,
                    $lead->owner?->name,
                    $lead->first_responded_at?->timezone(config('backoffice.timezone'))->format('Y-m-d H:i'),
                    $lead->source,
                ]), escape: '');
            }

            fclose($output);
        }, 'smartgro-enquiries-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function safeCell(mixed $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
