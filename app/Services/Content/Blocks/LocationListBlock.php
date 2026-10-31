<?php

namespace App\Services\Content\Blocks;

use App\Models\City;
use App\Services\Website\WorkspaceCatalogue;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * The locations on the website, as cards linking to their pages (FR-CMS-1 "location list"): every one,
 * or one city's. The block stores only its title, introduction and the city chosen from a list; the
 * locations are read when the page is shown, from the same published records as the locations pages,
 * so a location appears or leaves with its own publication and nothing unpublished is ever listed.
 * With no location to show, the section is left out.
 */
class LocationListBlock implements ContentBlock
{
    public function __construct(private WorkspaceCatalogue $catalogue) {}

    public function type(): string
    {
        return 'locations';
    }

    public function label(): string
    {
        return 'Location list';
    }

    public function editorBlock(): Block
    {
        return Block::make($this->type())
            ->label($this->label())
            ->icon(Heroicon::OutlinedBuildingOffice2)
            ->schema([
                TextInput::make('heading')->label('Title')->maxLength(90)->placeholder('Our locations'),
                Textarea::make('intro')->label('Introduction')->rows(2)->maxLength(300),
                Select::make('city_id')
                    ->label('Locations in')
                    ->options(fn (): array => City::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->placeholder('Every city')
                    ->helperText('Lists the locations on the website, each linking to its page. They follow their own publication: a location appears once its profile is published and leaves when it is taken off.'),
            ]);
    }

    public function rules(string $prefix, bool $forSubmission): array
    {
        return [
            $prefix => ['array:heading,intro,city_id'],
            "{$prefix}.heading" => ['nullable', 'string', 'max:90', 'not_regex:/[<>]/'],
            "{$prefix}.intro" => ['nullable', 'string', 'max:300', 'not_regex:/[<>]/'],
            "{$prefix}.city_id" => ['nullable', 'integer', Rule::exists('cities', 'id')],
        ];
    }

    public function normalize(array $data): array
    {
        return [...$data, 'city_id' => filled($data['city_id'] ?? null) ? (int) $data['city_id'] : null];
    }

    public function present(array $data): array
    {
        $city = filled($data['city_id'] ?? null) ? City::query()->find($data['city_id']) : null;

        return [
            'heading' => filled($data['heading'] ?? null) ? (string) $data['heading'] : ($city !== null ? 'SmartGro in '.$city->name : 'Our locations'),
            'intro' => filled($data['intro'] ?? null) ? (string) $data['intro'] : null,
            // A city deleted since the version was published lists nothing rather than every location.
            'properties' => filled($data['city_id'] ?? null) && $city === null ? new Collection : $this->catalogue->listedProperties($city),
        ];
    }

    public function component(): string
    {
        return 'blocks.locations';
    }
}
