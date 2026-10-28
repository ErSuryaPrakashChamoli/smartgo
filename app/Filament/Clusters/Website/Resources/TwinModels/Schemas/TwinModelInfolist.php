<?php

namespace App\Filament\Clusters\Website\Resources\TwinModels\Schemas;

use App\Enums\Twin\AnchorType;
use App\Enums\Twin\CaptureType;
use App\Http\Controllers\Backoffice\TwinModelFileController;
use App\Models\TwinAnchor;
use App\Models\TwinModel;
use Closure;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Vite;

/**
 * One 3D model: what it shows and how it was made, the checks it passed, a preview that loads only when
 * asked for (BR-3D-6), and the anchors found in it, which double as the text alternative to the view.
 * Missing details are stated as text rather than with Filament's placeholder, whose grey fails WCAG AA
 * contrast (axe, 2026-10-28).
 */
class TwinModelInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Model')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('property.name')->label('Property')->formatStateUsing(fn (string $state): string => 'SmartGro '.$state),
                        TextEntry::make('capture_type')->label('How it was made')->formatStateUsing(fn (CaptureType $state): string => $state->label()),
                        TextEntry::make('notice')
                            ->label('Notice shown with the model')
                            ->state(fn (TwinModel $record): string => $record->capture_type->disclaimer() ?? 'None: a measured scan of the location')
                            ->helperText('Set by how the model was made; it cannot be removed (BR-3D-2).')
                            ->columnSpanFull(),
                        TextEntry::make('captured_by')->label('Made by')->state(fn (TwinModel $record): string => $record->captured_by ?? 'Not recorded'),
                        TextEntry::make('captured_on')->label('Captured or modelled on')->state(fn (TwinModel $record): string => $record->captured_on?->format('j M Y') ?? 'Not recorded'),
                        TextEntry::make('uploader.name')->label('Uploaded by'),
                        TextEntry::make('created_at')->label('Uploaded')->dateTime('j M Y, H:i T', config('backoffice.timezone')),
                    ]),
                Section::make('Checks passed')
                    ->description('Every check runs at upload, against the limits in force then (shown here); a file that fails one is not stored (BR-3D-4, OD-119).')
                    ->columns(3)
                    ->collapsible()
                    ->schema([
                        TextEntry::make('file_bytes')->label('File size')->formatStateUsing(fn (int $state, TwinModel $record): string => self::megabytes($state).' MB (limit '.self::megabytes((int) self::limit($record, 'bytes')).' MB)'),
                        TextEntry::make('triangle_count')->label('Triangles')->formatStateUsing(fn (int $state, TwinModel $record): string => number_format($state).' (limit '.number_format((int) self::limit($record, 'triangles')).')'),
                        TextEntry::make('draw_calls')
                            ->label('Pieces drawn separately')
                            ->state(fn (TwinModel $record): string => self::figure($record, 'draw_calls', fn (int $calls): string => number_format($calls).' (limit '.number_format((int) self::limit($record, 'draw_calls')).')')),
                        TextEntry::make('materials')
                            ->state(fn (TwinModel $record): string => self::figure($record, 'materials', fn (int $materials): string => number_format($materials).' (limit '.number_format((int) self::limit($record, 'materials')).')')),
                        TextEntry::make('textures')
                            ->state(fn (TwinModel $record): string => self::figure($record, 'textures', fn (array $textures): string => $textures['count'] === 0 ? 'None' : sprintf(
                                '%d (%s); largest %s pixels on a side (limit %s); %s pixels in all (limit %s)',
                                $textures['count'],
                                collect($textures['formats'])->map(fn (string $format): string => strtoupper($format))->join(', '),
                                number_format($textures['largest_side']),
                                number_format((int) self::limit($record, 'texture_side')),
                                number_format($textures['pixels']),
                                number_format((int) self::limit($record, 'texture_pixels')),
                            ))),
                        TextEntry::make('geometry_bytes')
                            ->label('Geometry once decompressed')
                            ->state(fn (TwinModel $record): string => self::figure($record, 'geometry_bytes', fn (int $bytes): string => self::megabytes($bytes).' MB (limit '.self::megabytes((int) self::limit($record, 'geometry_bytes')).' MB)')),
                        TextEntry::make('size_metres')
                            ->label('Size')
                            ->state(fn (TwinModel $record): string => self::figure($record, 'size_metres', fn (array $size): string => collect($size)->map(fn (int|float $length): string => rtrim(rtrim(number_format($length, 2), '0'), '.'))->join(' × ').' m')),
                        TextEntry::make('compression')
                            ->state(fn (TwinModel $record): string => collect($record->validation_report['compression'] ?? [])->map(fn (string $method): string => ucfirst($method))->join(' and ')),
                        TextEntry::make('required_extensions')
                            ->label('Features the file needs')
                            ->state(fn (TwinModel $record): string => collect($record->validation_report['required_extensions'] ?? [])->join(', ') ?: 'None'),
                        TextEntry::make('generator')->label('Exported with')->state(fn (TwinModel $record): string => $record->validation_report['generator'] ?? 'Not stated'),
                        TextEntry::make('outside_files')->label('Outside files')->state('None'),
                    ]),
                Section::make('Preview')
                    ->description('Nothing 3D loads until you ask for it. Not shown on the website: that needs a published scene.')
                    ->schema([
                        View::make('filament.twin-models.viewer')->viewData(fn (TwinModel $record): array => [
                            'moduleUrl' => Vite::asset('resources/js/twin-viewer.js'),
                            'modelUrl' => route(TwinModelFileController::ROUTE, ['twinModel' => $record]),
                            'heading' => $record->capture_type->title('SmartGro '.$record->property->name),
                            'notice' => $record->capture_type->disclaimer(),
                            'size' => self::megabytes($record->file_bytes),
                            'cameras' => $record->cameraAnchors->map(fn (TwinAnchor $anchor): array => ['node' => $anchor->node_name, 'label' => 'Camera position '.$anchor->reference])->all(),
                        ]),
                    ]),
                Section::make('Anchors')
                    ->description('Named nodes found in the file. Spaces are linked to the inventory in a scene once floors and spaces exist (Phase 5).')
                    ->schema([
                        RepeatableEntry::make('anchors')
                            ->hiddenLabel()
                            ->columns(3)
                            ->schema([
                                TextEntry::make('anchor_type')->label('Type')->formatStateUsing(fn (AnchorType $state): string => $state->label()),
                                TextEntry::make('reference')->label('Code, name or number'),
                                TextEntry::make('node_name')->label('Node name in the file')->fontFamily('mono'),
                            ]),
                    ]),
            ]);
    }

    private static function megabytes(int $bytes): string
    {
        return number_format($bytes / 1024 / 1024, 1);
    }

    /**
     * A figure from the check report, described; "Not recorded" for a model checked before it was reported.
     */
    private static function figure(TwinModel $record, string $key, Closure $describe): string
    {
        $value = $record->validation_report[$key] ?? null;

        return $value === null ? 'Not recorded' : $describe($value);
    }

    /**
     * The limit the model was checked against.
     */
    private static function limit(TwinModel $record, string $key): int|float
    {
        return $record->validation_report['budgets'][$key] ?? 0;
    }
}
