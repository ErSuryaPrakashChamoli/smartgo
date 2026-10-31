<?php

namespace App\Filament\Clusters\Website\Resources\PostTopics\Pages;

use App\Filament\Clusters\Website\Resources\PostTopics\PostTopicResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPostTopics extends ListRecords
{
    protected static string $resource = PostTopicResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add topic')];
    }
}
