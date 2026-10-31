<?php

namespace App\Filament\Clusters\Website\Resources\PostTopics\Pages;

use App\Filament\Clusters\Website\Resources\PostTopics\PostTopicResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPostTopic extends EditRecord
{
    protected static string $resource = PostTopicResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
