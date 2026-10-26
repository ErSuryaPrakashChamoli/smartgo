<?php

namespace App\Filament\Clusters\Website\Resources\Testimonials\Pages;

use App\Filament\Clusters\Website\Resources\Testimonials\TestimonialResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTestimonials extends ListRecords
{
    protected static string $resource = TestimonialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New testimonial'),
        ];
    }
}
