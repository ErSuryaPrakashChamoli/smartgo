<?php

namespace App\Filament\Clusters\Website\Resources\NavigationMenus\Schemas;

use App\Models\NavigationMenu;
use App\Models\Page;
use App\Models\Property;
use App\Services\Content\ContentLink;
use App\Services\Content\NavigationPayload;
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
                                    ->schema(self::linkFields(self::LINK_TYPES))
                                    ->columns(2)
                                    ->maxItems(fn (): int => $record?->structure()['max_children'] ?? 0)
                                    ->addActionLabel('Add link')
                                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                                    ->collapsible()
                                    ->columnSpanFull()
                                    ->visible(fn (Get $get): bool => $get('type') === NavigationPayload::GROUP),
                            ])
                            ->columns(2)
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
    private static function linkFields(array $types): array
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
                    ->mapWithKeys(fn (Page $page): array => [$page->getKey() => $page->isPublished()
                        ? "{$page->title} ({$page->path()})"
                        : ($page->workingRevision?->payload['title'] ?? 'Untitled page').' (not published)'])
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
            TextInput::make('url')
                ->label('Web address')
                ->placeholder('https://')
                ->maxLength(2048)
                ->markAsRequired()
                ->helperText('Another website, starting with https://. Pages on this website are picked from the list.')
                ->visible(fn (Get $get): bool => $get('type') === ContentLink::TO_URL),
        ];
    }
}
