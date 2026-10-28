<?php

namespace App\Actions\Twin;

use App\Enums\Twin\CaptureType;
use App\Models\Property;
use App\Models\TwinAnchor;
use App\Models\TwinModel;
use App\Models\User;
use App\Services\Twin\GlbValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Adds a 3D model of a property from a GLB file (FR-3D-1). The file must pass every automated check
 * (GlbValidator, BR-3D-4); otherwise nothing is stored and every reason is returned in plain language.
 * The anchors found in the file are recorded with the model, the file goes to the private disk under a
 * random name, and the upload is audited.
 */
class CreateTwinModel
{
    public function __construct(private GlbValidator $glbValidator, private RecordTwinModelActivity $recordTwinModelActivity) {}

    /**
     * @param  array<string, mixed>  $attributes  `property_id`, `title`, `capture_type`, `captured_on` and `captured_by`
     *
     * @throws ValidationException
     */
    public function handle(User $uploader, UploadedFile $file, array $attributes): TwinModel
    {
        Gate::forUser($uploader)->authorize('create', TwinModel::class);

        $validated = Validator::make([...$attributes, 'file' => $file], [
            'file' => ['required', 'file', 'extensions:glb'],
            'property_id' => ['required', 'integer', Rule::exists('properties', 'id')],
            'title' => ['required', 'string', 'max:120', 'not_regex:/[<>]/'],
            'capture_type' => ['required', Rule::enum(CaptureType::class)],
            'captured_on' => ['nullable', 'date', 'before_or_equal:today'],
            'captured_by' => ['nullable', 'string', 'max:120', 'not_regex:/[<>]/'],
        ], [
            'file.extensions' => 'Upload a .glb file (binary glTF).',
            'captured_on.before_or_equal' => 'The capture date cannot be in the future.',
            '*.not_regex' => 'Use plain text: < and > are not allowed.',
        ])->validate();

        $inspection = $this->glbValidator->validate($file->getRealPath());

        if ($inspection['problems'] !== []) {
            throw ValidationException::withMessages(['file' => $inspection['problems']]);
        }

        $property = Property::query()->findOrFail($validated['property_id']);
        $twinModel = (new TwinModel)->forceFill([
            'property_id' => $property->getKey(),
            'title' => $validated['title'],
            'capture_type' => CaptureType::from($validated['capture_type']),
            'captured_on' => $validated['captured_on'] ?? null,
            'captured_by' => $validated['captured_by'] ?? null,
            'file_bytes' => $inspection['report']['bytes'],
            'triangle_count' => $inspection['report']['triangles'],
            'validation_report' => $inspection['report'],
            'uploaded_by' => $uploader->getKey(),
        ]);
        $twinModel->save();

        foreach ($inspection['anchors'] as $anchor) {
            (new TwinAnchor)->forceFill([
                'twin_model_id' => $twinModel->getKey(),
                'node_name' => $anchor['node_name'],
                'anchor_type' => $anchor['type'],
                'reference' => $anchor['reference'],
            ])->save();
        }

        try {
            $twinModel->addMedia($file)
                ->usingFileName(Str::lower(Str::random(40)).'.glb')
                ->toMediaCollection(TwinModel::COLLECTION);
        } catch (Throwable $exception) {
            report($exception);
            $twinModel->delete();

            throw ValidationException::withMessages(['file' => 'The model could not be stored. Try again.']);
        }

        $this->recordTwinModelActivity->handle($twinModel, $uploader, 'twin_model_uploaded', '3D model uploaded', [
            'property' => $property->code,
            'file_bytes' => $twinModel->file_bytes,
            'triangle_count' => $twinModel->triangle_count,
            'anchors' => count($inspection['anchors']),
        ]);

        return $twinModel;
    }
}
