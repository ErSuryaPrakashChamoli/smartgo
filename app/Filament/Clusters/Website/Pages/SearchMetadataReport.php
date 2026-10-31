<?php

namespace App\Filament\Clusters\Website\Pages;

use App\Enums\Access\Capability;
use App\Filament\Clusters\Website\WebsiteCluster;
use App\Services\Website\SearchMetadata;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

/**
 * Search titles and descriptions across the website (FR-SEO-1's validator report): every page search
 * engines may index, with the title and description it gives them, and what to look at: a title or a
 * description another page also uses, or no description. Read only: each finding is fixed in the item's
 * own editor, through its approval workflow; pages whose wording comes from the website template say so.
 * Built from the published records each time the page is opened, as the sitemap is.
 */
class SearchMetadataReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $cluster = WebsiteCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static ?string $navigationLabel = 'Search titles';

    protected static ?int $navigationSort = 90;

    protected static ?string $title = 'Search titles and descriptions';

    protected static ?string $slug = 'search-titles';

    /**
     * @var Collection<int, array<string, mixed>>|null
     */
    private ?Collection $rows = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Capability::ViewContent) ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'Every page search engines may index, with the title and description it gives them. Nothing here changes a page.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?array $filters, ?string $search): array => $this->filtered($filters ?? [], $search))
            ->columns([
                TextColumn::make('page')
                    ->weight('semibold')
                    ->description(fn (array $record): string => $record['type'])
                    ->searchable(),
                TextColumn::make('title')->label('Search title')->wrap(),
                TextColumn::make('description')->label('Search description')->wrap()->placeholder('No description'),
                TextColumn::make('findings')
                    ->label('To look at')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => SearchMetadata::FINDINGS[$state] ?? $state)
                    ->color(fn (string $state): string => $state === SearchMetadata::EXPECTED_REPEAT ? 'gray' : 'warning')
                    ->placeholder('Nothing'),
                TextColumn::make('source')->label('Comes from')->color('gray')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('finding')
                    ->label('To look at')
                    ->options([...array_diff_key(SearchMetadata::FINDINGS, [SearchMetadata::EXPECTED_REPEAT => true]), 'any' => 'Anything to look at']),
                SelectFilter::make('type')
                    ->label('Kind of page')
                    ->options(SearchMetadata::TYPES),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Open page')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (array $record): string => $record['url'], shouldOpenInNewTab: true),
                Action::make('edit')
                    ->label('Edit')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (array $record): bool => $record['edit_url'] !== null)
                    ->url(fn (array $record): ?string => $record['edit_url']),
            ])
            ->paginated([25, 50, 100])
            ->emptyStateHeading('No page matches')
            ->emptyStateDescription('Only pages search engines may index are listed: published, at their own address, and not marked "noindex".');
    }

    /**
     * The report's rows, worked out once per request, narrowed by the filters and the search.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, array<string, mixed>>
     */
    private function filtered(array $filters, ?string $search): array
    {
        $this->rows ??= app(SearchMetadata::class)->rows();
        $finding = $filters['finding']['value'] ?? null;
        $type = $filters['type']['value'] ?? null;

        return $this->rows
            ->filter(fn (array $row): bool => match ($finding) {
                null, '' => true,
                'any' => array_diff($row['findings'], [SearchMetadata::EXPECTED_REPEAT]) !== [],
                default => in_array($finding, $row['findings'], true),
            })
            ->filter(fn (array $row): bool => blank($type) || $row['type'] === (SearchMetadata::TYPES[$type] ?? null))
            ->filter(fn (array $row): bool => blank($search) || str_contains(mb_strtolower($row['page'].' '.$row['title'].' '.$row['description'].' '.$row['url']), mb_strtolower($search)))
            ->keyBy('key')
            ->all();
    }
}
