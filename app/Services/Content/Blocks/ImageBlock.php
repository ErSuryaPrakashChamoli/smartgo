<?php

namespace App\Services\Content\Blocks;

use App\Models\MediaAsset;
use Closure;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rule;

/**
 * An image from the media library (FR-CMS-5). The alt text and caption belong to this use and are
 * approved with the page; an image needs alt text unless it is decorative (BR-MED-4). Concept visuals
 * carry their label (BR-MED-2), and an image whose licence has expired is not shown (BR-MED-3).
 */
class ImageBlock implements ContentBlock
{
    public function type(): string
    {
        return 'image';
    }

    public function label(): string
    {
        return 'Image';
    }

    public function editorBlock(): Block
    {
        return Block::make($this->type())
            ->label($this->label())
            ->icon(Heroicon::OutlinedPhoto)
            ->schema([
                Select::make('media_asset_id')
                    ->label('Image from the media library')
                    ->options(fn (): array => MediaAsset::query()->latest()->limit(500)->get()
                        ->mapWithKeys(fn (MediaAsset $asset): array => [$asset->getKey() => $asset->title.' ('.$asset->origin->label().')'])
                        ->all())
                    ->searchable()
                    ->markAsRequired()
                    ->live()
                    ->afterStateUpdated(fn (Set $set, mixed $state) => $set('alt', MediaAsset::query()->find($state)?->alt_text)),
                Toggle::make('decorative')
                    ->label('Decorative only: screen readers skip it')
                    ->live(),
                TextInput::make('alt')
                    ->label('Alt text')
                    ->maxLength(150)
                    ->helperText('What the image shows, for people who cannot see it.')
                    ->hidden(fn (Get $get): bool => (bool) $get('decorative')),
                TextInput::make('caption')->maxLength(200),
            ]);
    }

    public function rules(string $prefix, bool $forSubmission): array
    {
        return [
            $prefix => ['array:media_asset_id,alt,decorative,caption'],
            "{$prefix}.media_asset_id" => [$forSubmission ? 'required' : 'nullable', 'integer', Rule::exists('media_assets', 'id'), $this->licenceRule()],
            "{$prefix}.decorative" => ['nullable', 'boolean'],
            "{$prefix}.alt" => ['nullable', 'string', 'max:150', 'not_regex:/[<>]/', ...($forSubmission ? ["required_unless:{$prefix}.decorative,true"] : [])],
            "{$prefix}.caption" => ['nullable', 'string', 'max:200', 'not_regex:/[<>]/'],
        ];
    }

    public function normalize(array $data): array
    {
        $decorative = (bool) ($data['decorative'] ?? false);

        return [
            'media_asset_id' => isset($data['media_asset_id']) ? (int) $data['media_asset_id'] : null,
            'decorative' => $decorative,
            'alt' => $decorative ? null : ($data['alt'] ?? null),
            'caption' => $data['caption'] ?? null,
        ];
    }

    public function present(array $data): array
    {
        $asset = isset($data['media_asset_id']) ? MediaAsset::query()->with('media')->find($data['media_asset_id']) : null;

        if ($asset === null || $asset->isLicenceExpired() || $asset->file() === null) {
            return ['image' => null];
        }

        return [
            'image' => [
                'src' => $asset->renditionPath('w960'),
                'srcset' => collect(MediaAsset::RENDITIONS)->map(fn (int $width, string $name): string => $asset->renditionPath($name).' '.$width.'w')->join(', '),
                'dimensions' => $asset->dimensions(),
                'alt' => ($data['decorative'] ?? false) ? '' : (string) ($data['alt'] ?? ''),
                'caption' => $data['caption'] ?? null,
                'credit' => $asset->credit_line,
                'isConcept' => $asset->origin->isConcept(),
            ],
        ];
    }

    public function component(): string
    {
        return 'blocks.image';
    }

    private function licenceRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_numeric($value) && MediaAsset::query()->find((int) $value)?->isLicenceExpired()) {
                $fail('This image\'s licence has expired, so it cannot be used.');
            }
        };
    }
}
