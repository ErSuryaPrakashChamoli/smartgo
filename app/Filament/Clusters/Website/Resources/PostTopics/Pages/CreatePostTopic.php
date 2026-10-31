<?php

namespace App\Filament\Clusters\Website\Resources\PostTopics\Pages;

use App\Filament\Clusters\Website\Resources\PostTopics\PostTopicResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePostTopic extends CreateRecord
{
    protected static string $resource = PostTopicResource::class;

    protected static bool $canCreateAnother = false;
}
