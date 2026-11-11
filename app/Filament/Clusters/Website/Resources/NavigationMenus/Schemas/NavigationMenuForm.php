<?php

namespace App\Filament\Clusters\Website\Resources\NavigationMenus\Schemas;

use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Models\Page;
use App\Models\Product;
use App\Models\Property;
use App\Models\SpaceListing;
use App\Models\SpaceType;
use App\Services\Content\ContentLink;
use App\Services\Content\NavigationPayload;
use App\Services\Content\PagePayload;
use App\Services\Website\SiteSections;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * The menu editor form. Pages, parts of the website and locations are picked from a list, never typed,
 * so a change of address never breaks a link (FR-CMS-4); the server-side rules are in NavigationPayload.
 * Links and sub-groups inside a main-menu drop-down also take a short description and an icon (OD-149).
 * Items are reordered by dragging or with the arrow buttons.
 */
class NavigationMenuForm
{
    /**
     * Where a link can go, as offered in "Goes to".
     *
     * @var array<string, string>
     */
    private const array LINK_TYPES = [
        ContentLink::TO_PAGE => 'A page on this website',
        NavigationPayload::SECTION => 'A part of this website, such as the locations list',
        NavigationPayload::PROPERTY => 'A location',
        NavigationPayload::CATEGORY => 'A workspace type',
        ContentLink::TO_URL => 'Another website',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Items')
                    ->description('Items appear in this order. Links to pages that are not published yet, locations that are not open and lists with nothing published stay hidden on the website until they are.')
                    ->schema([
                        Repeater::make('items')
                            ->hiddenLabel()
                            ->schema(fn (?NavigationMenu $record): array => [
                                ...self::linkFields(self::topLevelTypes($record)),
                                Repeater::make('children')
                                    ->label('Links in this group')
                                    ->schema(fn (): array => [
                                        ...self::linkFields(($record?->structure()['groups'] ?? 'none') === 'allowed'
                                            ? [
                                                ...self::LINK_TYPES,
                                                NavigationPayload::GROUP => 'A sub-group (a heading with its own links)',
                                                ...(SpaceListing::isAvailable() ? [NavigationPayload::SPACE_TYPE => 'A space type (its spaces on the website, listed by location)'] : []),
                                            ]
                                            : self::LINK_TYPES, $record?->offersDetails() ?? false),
                                        Repeater::make('children')
                                            ->label('Links in this sub-group')
                                            ->schema(self::linkFields(self::LINK_TYPES, $record?->offersDetails() ?? false))
                                            ->columns(2)
                                            ->reorderableWithButtons()
                                            ->maxItems(fn (): int => $record?->structure()['max_children'] ?? 0)
                                            ->addActionLabel('Add link')
                                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                                            ->collapsible()
                                            ->columnSpanFull()
                                            ->visible(fn (Get $get): bool => $get('type') === NavigationPayload::GROUP),
                                    ])
                                    ->columns(2)
                                    ->reorderableWithButtons()
                                    ->maxItems(fn (): int => $record?->structure()['max_children'] ?? 0)
                                    ->addActionLabel('Add link')
                                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                                    ->collapsible()
                                    ->columnSpanFull()
                                    ->visible(fn (Get $get): bool => $get('type') === NavigationPayload::GROUP),
                            ])
                            ->columns(2)
                            ->reorderableWithButtons()
                            ->maxItems(fn (?NavigationMenu $record): int => $record?->structure()['max_items'] ?? 0)
                            ->addActionLabel('Add item')
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->collapsible(),
                    ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function topLevelTypes(?NavigationMenu $record): array
    {
        return match ($record?->structure()['groups'] ?? 'none') {
            'allowed' => [...self::LINK_TYPES, NavigationPayload::GROUP => 'A group of links (opens a drop-down)'],
            'only' => [NavigationPayload::GROUP => 'A column heading with its links'],
            default => self::LINK_TYPES,
        };
    }

    /**
     * @param  array<string, string>  $types
     * @return list<mixed>
     */
    private static function linkFields(array $types, bool $withDetails = false): array
    {
        return [
            TextInput::make('label')
                ->label('Label')
                ->markAsRequired()
                ->maxLength(40),
            Select::make('type')
                ->label('Goes to')
                ->options($types)
                ->default(count($types) === 1 ? array_key_first($types) : null)
                ->live()
                ->required(),
            Select::make('page_id')
                ->label('Page')
                ->options(fn (): array => Page::query()->with('workingRevision')->get()
                    ->mapWithKeys(fn (Page $page): array => [$page->getKey() => ($page->isPublished()
                        ? "{$page->title} ({$page->path()})"
                        : ($page->workingRevision?->payload['title'] ?? 'Untitled page').' (not published)')
                        .(($page->template ?? $page->workingRevision?->payload['template'] ?? null) === PagePayload::SAMPLE_TEMPLATE ? ' · sample content' : '')])
                    ->sort()
                    ->all())
                ->searchable()
                ->markAsRequired()
                ->visible(fn (Get $get): bool => $get('type') === ContentLink::TO_PAGE),
            Select::make('section')
                ->label('Part of the website')
                ->options(fn (): array => app(SiteSections::class)->options())
                ->markAsRequired()
                ->visible(fn (Get $get): bool => $get('type') === NavigationPayload::SECTION),
            Select::make('property_id')
                ->label('Location')
                ->options(fn (): array => Property::query()->with('city')->orderBy('code')->get()
                    ->mapWithKeys(fn (Property $property): array => [$property->getKey() => $property->isPubliclyListed()
                        ? "SmartGro {$property->name} ({$property->path()})"
                        : "SmartGro {$property->name} (not on the website: {$property->operational_status->label()})"])
                    ->all())
                ->searchable()
                ->markAsRequired()
                ->visible(fn (Get $get): bool => $get('type') === NavigationPayload::PROPERTY),
            Select::make('product_id')
                ->label('Workspace type')
                ->options(fn (): array => Product::query()->orderBy('sort_order')->orderBy('id')->get()
                    ->mapWithKeys(fn (Product $product): array => [$product->getKey() => $product->is_active
                        ? "{$product->name} (/workspaces/{$product->slug})"
                        : "{$product->name} (not on the website)"])
                    ->all())
                ->helperText('Goes to the workspace type\'s page once it is offered at a location on the website; until then to the enquiry form with this type chosen, while that form is open.')
                ->searchable()
                ->markAsRequired()
                ->visible(fn (Get $get): bool => $get('type') === NavigationPayload::CATEGORY),
            Select::make('space_type_id')
                ->label('Space type')
                ->options(fn (): array => SpaceType::query()->orderBy('sort_order')->orderBy('id')->pluck('name', 'id')->all())
                ->helperText('Opens as a tab in the drop-down showing this type\'s spaces, by location, from Website → Spaces on the website. Hidden while none is on the website.')
                ->markAsRequired()
                ->visible(fn (Get $get): bool => $get('type') === NavigationPayload::SPACE_TYPE),
            TextInput::make('url')
                ->label('Web address')
                ->placeholder('https://')
                ->maxLength(2048)
                ->markAsRequired()
                ->helperText('Another website, starting with https://. Pages on this website are picked from the list.')
                ->visible(fn (Get $get): bool => $get('type') === ContentLink::TO_URL),
            ...($withDetails ? self::detailFields() : []),
        ];
    }

    /**
     * The description and icon of a drop-down link or sub-group (OD-149).
     *
     * @return list<mixed>
     */
    private static function detailFields(): array
    {
        return [
            TextInput::make('description')
                ->label('Short description')
                ->maxLength(120)
                ->helperText('Optional, plain text. Shown under the label in a drop-down with categories (sub-groups), such as Solutions.'),
            Select::make('icon')
                ->label('Icon')
                ->options(fn (): array => collect(NavigationItem::ICONS)
                    ->mapWithKeys(fn (string $label, string $name): array => [$name => '<span style="display:inline-flex;align-items:center;gap:0.5rem">'
                        .svg('heroicon-o-'.$name, '', ['style' => 'width:1.25rem;height:1.25rem', 'aria-hidden' => 'true'])->toHtml().e($label).'</span>'])
                    ->all())
                ->allowHtml()
                ->native(false)
                ->placeholder('Automatic')
                ->helperText('Optional. Shown in a drop-down with categories; without one, a workspace type or a location shows its own icon.'),
        ];
    }
}
