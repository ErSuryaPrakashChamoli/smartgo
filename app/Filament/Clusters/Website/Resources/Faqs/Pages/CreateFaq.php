<?php

namespace App\Filament\Clusters\Website\Resources\Faqs\Pages;

use App\Filament\Clusters\Website\Concerns\CreatesFirstDraft;
use App\Filament\Clusters\Website\Resources\Faqs\FaqResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFaq extends CreateRecord
{
    use CreatesFirstDraft;

    protected static string $resource = FaqResource::class;

    protected static bool $canCreateAnother = false;
}
