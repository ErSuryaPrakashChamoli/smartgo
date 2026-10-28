<?php

namespace App\Services\Content\Blocks;

use App\Models\Property;
use App\Services\Content\SiteNavigation;
use App\Services\Website\WorkspaceCatalogue;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;

/**
 * SmartGro's main published contact details (Website → Settings), for a contact page (IA /contact,
 * Phase 6), optionally followed by the locations on the website with their addresses. The block stores
 * only its heading, introduction and that choice: the details are always read from the published
 * setting, so they are kept in one place and nothing unpublished is shown (BR-CMS-9).
 */
class ContactDetailsBlock implements ContentBlock
{
    public function __construct(private SiteNavigation $siteNavigation, private WorkspaceCatalogue $catalogue) {}

    public function type(): string
    {
        return 'contact';
    }

    public function label(): string
    {
        return 'Contact details';
    }

    public function editorBlock(): Block
    {
        return Block::make($this->type())
            ->label($this->label())
            ->icon(Heroicon::OutlinedPhone)
            ->schema([
                TextInput::make('heading')->label('Title')->maxLength(90)->placeholder('Talk to SmartGro')
                    ->helperText('Shows SmartGro\'s main contact details as published in Website → Settings. Change the details there, not here.'),
                Textarea::make('intro')->label('Introduction')->rows(2)->maxLength(300),
                Toggle::make('show_locations')->label('List the locations on the website with their addresses'),
            ]);
    }

    public function rules(string $prefix, bool $forSubmission): array
    {
        return [
            $prefix => ['array:heading,intro,show_locations'],
            "{$prefix}.heading" => ['nullable', 'string', 'max:90', 'not_regex:/[<>]/'],
            "{$prefix}.intro" => ['nullable', 'string', 'max:300', 'not_regex:/[<>]/'],
            "{$prefix}.show_locations" => ['nullable', 'boolean'],
        ];
    }

    public function normalize(array $data): array
    {
        return [...$data, 'show_locations' => (bool) ($data['show_locations'] ?? false)];
    }

    public function present(array $data): array
    {
        return [
            'heading' => filled($data['heading'] ?? null) ? (string) $data['heading'] : 'Talk to SmartGro',
            'intro' => filled($data['intro'] ?? null) ? (string) $data['intro'] : null,
            'contact' => $this->siteNavigation->mainContact(),
            'locations' => (bool) ($data['show_locations'] ?? false)
                ? $this->catalogue->listedProperties()->map(fn (Property $property): array => ['name' => 'SmartGro '.$property->name, 'address' => $property->addressLine(), 'path' => (string) $property->path()])->all()
                : [],
        ];
    }

    public function component(): string
    {
        return 'blocks.contact';
    }
}
