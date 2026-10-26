<?php

namespace App\Filament\Clusters\Website\Resources\CaseStudies\Pages;

use App\Filament\Clusters\Website\Concerns\CreatesFirstDraft;
use App\Filament\Clusters\Website\Resources\CaseStudies\CaseStudyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCaseStudy extends CreateRecord
{
    use CreatesFirstDraft;

    protected static string $resource = CaseStudyResource::class;

    protected static bool $canCreateAnother = false;
}
