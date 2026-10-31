<?php

namespace App\Filament\Clusters\Website\Resources\Faqs\Pages;

use App\Filament\Clusters\Website\Concerns\HasRevisionWorkflow;
use App\Filament\Clusters\Website\Resources\Faqs\FaqResource;
use App\Filament\Clusters\Website\Resources\Faqs\Schemas\FaqForm;
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
        if (! $this->record->isPublished()) {
            return 'Not published yet';
        }

        return match (true) {
            $this->record->property !== null => 'Live on the page of SmartGro '.$this->record->property->name,
            $this->record->product !== null => 'Live on the '.$this->record->product->name.' page',
            default => 'Live at /faqs',
        };
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
            'shown_on' => FaqForm::shownOn($payload),
            'property_id' => $payload['property_id'] ?? null,
            'product_id' => $payload['product_id'] ?? null,
        ];
    }
}
