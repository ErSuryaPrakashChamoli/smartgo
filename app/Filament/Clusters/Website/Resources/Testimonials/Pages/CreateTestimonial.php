<?php

namespace App\Filament\Clusters\Website\Resources\Testimonials\Pages;

use App\Filament\Clusters\Website\Concerns\CreatesFirstDraft;
use App\Filament\Clusters\Website\Resources\Testimonials\TestimonialResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTestimonial extends CreateRecord
{
    use CreatesFirstDraft;

    protected static string $resource = TestimonialResource::class;

    protected static bool $canCreateAnother = false;
}
