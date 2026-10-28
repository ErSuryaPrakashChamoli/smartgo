<?php

namespace App\Filament\Clusters\Website\Resources\TwinModels\Schemas;

use App\Enums\Twin\CaptureType;
use App\Models\Property;
use App\Services\Twin\TwinModelLimits;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Upload form. The automated checks are in GlbValidator; the property, the file and the capture type
 * are set once, at upload. Nobody types a coordinate: anchors are read from the file (FR-3D-2).
 */
class TwinModelForm
{
    /**
     * Larger than the model limit (config/twin.php), so an oversized file still reaches the checks and the
     * uploader sees the plain-language reason; config/livewire.php allows the same for uploads in progress.
     */
    public const int UPLOAD_LIMIT_KILOBYTES = 32 * 1024;

    public static function configure(Schema $schema): Schema
    {
        $limits = app(TwinModelLimits::class);

        return $schema
            ->columns(1)
            ->components([
                Section::make('Model')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('file')
                            ->label('GLB file')
                            ->maxSize(self::UPLOAD_LIMIT_KILOBYTES)
                            ->storeFiles(false)
                            ->required()
                            ->helperText(sprintf(
                                'A binary glTF (.glb) file from the 3D-modelling supplier, as the supplier specification sets out: up to %s MB and %s triangles; compressed with Draco or Meshopt; textures in PNG, JPEG, WebP or KTX2, at most %s pixels on a side; measured in metres; nothing outside the file; nodes named SG_SPACE_…, SG_AMENITY_… or SG_CAM_…. Every reason a file fails is listed.',
                                rtrim(rtrim(number_format($limits->bytes / 1024 / 1024, 1), '0'), '.'),
                                number_format($limits->triangles),
                                number_format($limits->textureSide),
                            ))
                            ->columnSpanFull(),
                        Select::make('property_id')
                            ->label('Property')
                            ->options(fn (): array => Property::query()->orderBy('code')->get()->mapWithKeys(fn (Property $property): array => [$property->getKey() => 'SmartGro '.$property->name])->all())
                            ->required()
                            ->helperText('It cannot be changed later.'),
                        Select::make('capture_type')
                            ->label('How it was made')
                            ->options(CaptureType::options())
                            ->required()
                            ->helperText('Only a measured scan may be called a scan. An architectural or conceptual model always shows its notice (BR-3D-1, BR-3D-2). It cannot be changed later.'),
                        TextInput::make('title')->required()->maxLength(120)->helperText('For finding the model in the back office; not shown on the website.'),
                        TextInput::make('captured_by')->label('Made by')->maxLength(120)->helperText('The 3D-modelling supplier or the person who scanned the location.'),
                        DatePicker::make('captured_on')->label('Captured or modelled on')->maxDate(now()),
                    ]),
            ]);
    }
}
