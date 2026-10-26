<?php

namespace App\Filament\Clusters\Website\Resources\MediaAssets\Schemas;

use App\Enums\Content\MediaOrigin;
use App\Models\MediaAsset;
use App\Services\Content\MediaAssetRules;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ImageEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Upload and description form. The server-side rules are in MediaAssetRules; the file and its origin
 * are set once, at upload.
 */
class MediaAssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Image')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('file')
                            ->label('Image file')
                            ->image()
                            ->acceptedFileTypes(MediaAsset::ACCEPTED_MIME_TYPES)
                            ->maxSize(MediaAssetRules::MAX_KILOBYTES)
                            ->storeFiles(false)
                            ->required()
                            ->helperText('JPEG, PNG or WebP, up to 10 MB and 200 to 8,000 pixels. The website shows resized WebP copies; the original is kept private.')
                            ->columnSpanFull()
                            ->visibleOn('create'),
                        ImageEntry::make('preview')
                            ->hiddenLabel()
                            ->state(fn (?MediaAsset $record): ?string => $record?->renditionPath('w960'))
                            ->imageHeight(240)
                            ->visibleOn('edit'),
                        Select::make('origin')
                            ->label('Origin')
                            ->options(collect(MediaOrigin::cases())->mapWithKeys(fn (MediaOrigin $origin): array => [$origin->value => $origin->label()])->all())
                            ->required()
                            ->live()
                            ->helperText('AI concepts and renders are labelled "Concept visual" wherever they appear (BR-MED-2). The origin cannot be changed later.')
                            ->visibleOn('create'),
                        Text::make(fn (?MediaAsset $record): string => 'Origin: '.$record?->origin->label().($record?->origin->isConcept() ? ' (shown as "Concept visual")' : ''))
                            ->visibleOn('edit'),
                        TextInput::make('title')->required()->maxLength(120)->helperText('For finding the image in the library; not shown on the website.'),
                        Toggle::make('is_decorative')->label('Decorative only: screen readers skip it')->live()->inline(false),
                        TextInput::make('alt_text')
                            ->label('Default alt text')
                            ->maxLength(150)
                            ->helperText('Suggested when the image is added to a page; each page can adjust it.')
                            ->hidden(fn (Get $get): bool => (bool) $get('is_decorative')),
                        TextInput::make('caption')->maxLength(200),
                    ]),
                Section::make('Licence and credit')
                    ->columns(2)
                    ->schema([
                        TextInput::make('licence_holder')->maxLength(120)->markAsRequired(fn (Get $get, ?MediaAsset $record): bool => ($get('origin') ?? $record?->origin?->value) === MediaOrigin::LicensedStock->value),
                        TextInput::make('licence_reference')->maxLength(120)->markAsRequired(fn (Get $get, ?MediaAsset $record): bool => ($get('origin') ?? $record?->origin?->value) === MediaOrigin::LicensedStock->value),
                        TextInput::make('licence_type')->maxLength(40),
                        DatePicker::make('licence_expires_at')->label('Licence ends on')->helperText('After this date the image disappears from the website. Staff are reminded 30 days before.'),
                        TextInput::make('credit_line')->maxLength(150)->helperText('Shown under the image when it appears on a page.'),
                        Textarea::make('usage_restrictions')->maxLength(1000)->rows(2),
                    ]),
                Checkbox::make('not_from_old_website')
                    ->label('This file is not taken from the old smartgro.space website')
                    ->accepted()
                    ->validationMessages(['accepted' => 'Nothing from the old website may be reused (OD-19).'])
                    ->visibleOn('create'),
            ]);
    }
}
