<?php

namespace App\Filament\Clusters\Website\Resources\NavigationMenus\Schemas;

use App\Models\NavigationMenu;
use App\Models\Page;
use App\Services\Content\ContentLink;
use App\Services\Content\NavigationPayload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * The menu editor form. Pages are picked from a list, never typed, so a change of address never breaks
 * a link (FR-CMS-4); the server-side rules are in NavigationPayload.
 */
class NavigationMenuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Items')
                    ->description('Items appear in this order. Links to pages that are not published yet stay hidden on the website until the page is published.')
                    ->schema([
                        Repeater::make('items')
                            ->hiddenLabel()
                            ->schema(fn (?NavigationMenu $record): array => [
                                ...self::linkFields(self::topLevelTypes($record)),
                                Repeater::make('children')
                                    ->label('Links in this group')
                                    ->schema(self::linkFields([ContentLink::TO_PAGE => 'A page on this website', ContentLink::TO_URL => 'Another website']))
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
        $links = [ContentLink::TO_PAGE => 'A page on this website', ContentLink::TO_URL => 'Another website'];

        return match ($record?->structure()['groups'] ?? 'none') {
            'allowed' => [...$links, NavigationPayload::GROUP => 'A group of links (opens a drop-down)'],
            'only' => [NavigationPayload::GROUP => 'A column heading with its links'],
            default => $links,
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
