<?php

namespace App\Filament\Clusters\Website\Resources\Faqs\Pages;

use App\Filament\Clusters\Website\Concerns\HasRevisionWorkflow;
use App\Filament\Clusters\Website\Resources\Faqs\FaqResource;
use App\Models\Faq;
use Filament\Resources\Pages\EditRecord;

/**
 * The FAQ editor; the workflow lives in HasRevisionWorkflow.
 *
 * @property Faq $record
 */
class EditFaq extends EditRecord
{
    use HasRevisionWorkflow;

    protected static string $resource = FaqResource::class;

    public function getTitle(): string
    {
        return FaqResource::getRecordTitle($this->record);
    }

    public function getSubheading(): ?string
    {
        return $this->record->isPublished() ? 'Live at /faqs' : 'Not published yet';
    }

    protected function getHeaderActions(): array
    {
        return $this->workflowHeaderActions();
    }

    protected function contentNoun(): string
    {
        return 'FAQ';
    }

    protected function formDataFromPayload(array $payload): array
    {
        return [
            'question' => $payload['question'] ?? null,
            'answer' => $payload['answer'] ?? null,
            'sort_order' => $payload['sort_order'] ?? 0,
        ];
    }
}
