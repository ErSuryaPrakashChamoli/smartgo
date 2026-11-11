<?php

namespace App\Filament\Clusters\Website\Resources\HomeBanners\Schemas;

use App\Models\HomeBanner;
use App\Models\MediaAsset;
use App\Services\Content\ContentLink;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The home banner editor. The rules on HomeBanner are the authority. The image is picked from the media library,
 * where its origin and licence are recorded; an image that is not a photo of a SmartGro location is labelled on the
 * website (BR-MED-2).
 */
class HomeBannerForm
{
    public static function configure(Schema $schema): Schema
    {
        $contentLink = app(ContentLink::class);

        return $schema
            ->columns(1)
            ->components([
                Section::make('Banner')
                    ->columns(4)
                    ->schema([
                        TextInput::make('heading')
                            ->label('Heading')
                            ->required()
                            ->maxLength(120)
                            ->helperText('A short line, for example "A better space to do your best work."')
                            ->columnSpan(3),
                        TextInput::make('sort_order')
                            ->label('Position')
                            ->integer()
                            ->minValue(0)
                            ->maxValue(9999)
                            ->default(0)
                            ->helperText('Lower numbers come first. The carousel shows the first '.HomeBanner::MAX_SHOWN.'.'),
                        Textarea::make('text')
                            ->label('Text')
                            ->maxLength(300)
                            ->rows(3)
                            ->helperText('One or two sentences under the heading. Do not state prices or availability here.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Image')
                    ->description('Landscape images at least 1,600 pixels wide look best. Stock photos, AI images and renders are labelled on the website as not being a SmartGro location.')
                    ->columns(2)
                    ->schema([
                        Select::make('media_asset_id')
                            ->label('Image from the media library')
                            ->options(fn (): array => MediaAsset::query()->latest()->limit(500)->get()
                                ->mapWithKeys(fn (MediaAsset $asset): array => [$asset->getKey() => $asset->title.' ('.$asset->origin->label().')'])
                                ->all())
                            ->searchable()
                            ->markAsRequired()
                            ->helperText('Add new images under Website → Media library first.'),
                        TextInput::make('image_alt')
                            ->label('Alt text for this banner (optional)')
                            ->maxLength(150)
                            ->helperText('Leave empty to use the description recorded in the media library.'),
                    ]),
                Section::make('Buttons')
                    ->description('Both are optional. A button whose page or part of the website is not there yet stays hidden.')
                    ->schema([
                        $contentLink->editor('primary_link', 'Main button', required: false),
                        $contentLink->editor('secondary_link', 'Second button', required: false),
                    ]),
            ]);
    }
}
