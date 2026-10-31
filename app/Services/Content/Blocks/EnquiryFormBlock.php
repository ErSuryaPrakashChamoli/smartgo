<?php

namespace App\Services\Content\Blocks;

use App\Models\CustomerSegment;
use App\Models\Product;
use App\Models\Property;
use App\Services\Content\SiteNavigation;
use App\Services\Website\EnquiryForm;
use App\Services\Website\WorkspaceCatalogue;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rule;

/**
 * The enquiry form inside a page (FR-CMS-1 "form embed"): the same form as /enquire, sending to the
 * same place under the same rules, optionally with a location and workspace type already chosen. It
 * opens and closes with /enquire: until a privacy notice is published, visitors see the same notice
 * and SmartGro's published contact details instead (D8.1, D-15). Enquiries sent from it record the page
 * they came from. A page can hold one, because the form's fields have fixed names and ids.
 */
class EnquiryFormBlock implements ContentBlock
{
    public function __construct(private EnquiryForm $form, private WorkspaceCatalogue $catalogue, private SiteNavigation $siteNavigation) {}

    public function type(): string
    {
        return 'enquiry';
    }

    public function label(): string
    {
        return 'Enquiry form';
    }

    public function editorBlock(): Block
    {
        return Block::make($this->type())
            ->label($this->label())
            ->icon(Heroicon::OutlinedEnvelope)
            ->schema([
                TextInput::make('heading')->label('Title')->maxLength(90)->placeholder('Ask about a workspace')
                    ->helperText('The same form as the Enquire page, under the same rules. Until a privacy notice is published, visitors see SmartGro\'s contact details instead, as on the Enquire page. One per page.'),
                Textarea::make('intro')->label('Introduction')->rows(2)->maxLength(300),
                Select::make('property_id')
                    ->label('Location already chosen')
                    ->options(fn (): array => $this->catalogue->listedProperties()->mapWithKeys(fn (Property $property): array => [$property->getKey() => 'SmartGro '.$property->name])->all())
                    ->placeholder('None: the visitor chooses')
                    ->helperText('Locations on the website. Visitors can still change it.'),
                Select::make('workspace')
                    ->label('Workspace type already chosen')
                    ->options(fn (): array => Product::query()->shown()->pluck('name', 'slug')->all())
                    ->placeholder('None: the visitor chooses'),
            ]);
    }

    public function rules(string $prefix, bool $forSubmission): array
    {
        return [
            $prefix => ['array:heading,intro,property_id,workspace'],
            "{$prefix}.heading" => ['nullable', 'string', 'max:90', 'not_regex:/[<>]/'],
            "{$prefix}.intro" => ['nullable', 'string', 'max:300', 'not_regex:/[<>]/'],
            "{$prefix}.property_id" => ['nullable', 'integer', Rule::exists('properties', 'id')],
            "{$prefix}.workspace" => ['nullable', 'string', Rule::exists('products', 'slug')],
        ];
    }

    public function normalize(array $data): array
    {
        return [
            ...$data,
            'property_id' => filled($data['property_id'] ?? null) ? (int) $data['property_id'] : null,
            'workspace' => filled($data['workspace'] ?? null) ? (string) $data['workspace'] : null,
        ];
    }

    public function present(array $data): array
    {
        $presented = [
            'heading' => filled($data['heading'] ?? null) ? (string) $data['heading'] : 'Ask about a workspace',
            'intro' => filled($data['intro'] ?? null) ? (string) $data['intro'] : null,
            'isOpen' => $this->form->isOpen(),
            'contact' => $this->siteNavigation->mainContact(),
        ];

        if (! $presented['isOpen']) {
            return $presented;
        }

        $properties = $this->catalogue->listedProperties();
        $categories = Product::query()->shown()->get();
        $request = request();

        return [
            ...$presented,
            'notice' => $this->form->privacyNotice(),
            'started' => $this->form->startedToken(),
            'properties' => $properties,
            'categories' => $categories,
            'segments' => CustomerSegment::query()->offered()->get(),
            'teamSizes' => EnquiryForm::TEAM_SIZES,
            // A location or workspace type that has left the website since is simply not chosen.
            'selected' => [
                'property' => $properties->firstWhere('id', $data['property_id'] ?? null)?->getKey(),
                'workspace' => $categories->firstWhere('slug', $data['workspace'] ?? null)?->slug,
            ],
            'from' => mb_substr($request->getPathInfo(), 0, 255),
            'utm' => collect(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'])
                ->mapWithKeys(fn (string $key): array => [$key => is_string($request->query($key)) ? mb_substr($request->query($key), 0, 120) : null])
                ->filter()->all(),
        ];
    }

    public function component(): string
    {
        return 'blocks.enquiry';
    }
}
