<?php

namespace App\Filament\Clusters\Insights\Pages;

use App\Enums\Access\Capability;
use App\Filament\Clusters\Insights\InsightsCluster;
use App\Filament\Clusters\Properties\Resources\OfferingProfiles\OfferingProfileResource;
use App\Filament\Clusters\Properties\Resources\PropertyProfiles\PropertyProfileResource;
use App\Filament\Clusters\Website\Resources\CaseStudies\CaseStudyResource;
use App\Filament\Clusters\Website\Resources\Faqs\FaqResource;
use App\Filament\Clusters\Website\Resources\Pages\PageResource;
use App\Filament\Clusters\Website\Resources\Posts\PostResource;
use App\Filament\Clusters\Website\Resources\Testimonials\TestimonialResource;
use App\Models\CaseStudy;
use App\Models\Faq;
use App\Models\OfferingProfile;
use App\Models\Page as ContentPage;
use App\Models\Post;
use App\Models\PropertyProfile;
use App\Models\Testimonial;
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
 * Content freshness (FR-AN-2): every item live on the website with when its current version was
 * published, oldest first, so staff can see what has not been reviewed for longest. No age is called
 * "stale": no review period is decided. Read only; each item opens in its own editor.
 */
class ContentFreshness extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $cluster = InsightsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Content freshness';

    protected static ?string $title = 'Content freshness';

    protected static ?string $slug = 'content-freshness';

    protected static ?int $navigationSort = 2;

    /**
     * @var Collection<string, array<string, mixed>>|null
     */
    private ?Collection $rows = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Capability::ViewContent) ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'Everything live on the website, with when its current version was published, oldest first.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $timezone = (string) config('backoffice.timezone');

        return $table
            ->records(fn (?array $filters): array => $this->filtered($filters ?? []))
            ->columns([
                TextColumn::make('item')->weight('semibold')->description(fn (array $record): string => $record['kind'])->wrap(),
                TextColumn::make('published_at')->label('Current version published')->dateTime('j M Y', $timezone)->placeholder('Not recorded'),
                TextColumn::make('days')->label('Days since')->numeric()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('kind')->label('Kind')->options(array_combine(array_keys(self::KINDS), array_keys(self::KINDS))),
            ])
            ->recordActions([
                Action::make('edit')->label('Open in its editor')->icon(Heroicon::OutlinedPencilSquare)->color('gray')->url(fn (array $record): string => $record['edit_url']),
            ])
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Nothing is live yet')
            ->emptyStateDescription('Published pages, articles, customer stories, questions, testimonials and location profiles appear here.');
    }

    /**
     * @var array<string, array{0: class-string, 1: class-string}>
     */
    private const array KINDS = [
        'Page' => [ContentPage::class, PageResource::class],
        'Article' => [Post::class, PostResource::class],
        'Customer story' => [CaseStudy::class, CaseStudyResource::class],
        'Question' => [Faq::class, FaqResource::class],
        'Testimonial' => [Testimonial::class, TestimonialResource::class],
        'Property profile' => [PropertyProfile::class, PropertyProfileResource::class],
        'Workspace profile' => [OfferingProfile::class, OfferingProfileResource::class],
    ];

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, array<string, mixed>>
     */
    private function filtered(array $filters): array
    {
        $this->rows ??= $this->rows();
        $kind = $filters['kind']['value'] ?? null;

        return $this->rows->filter(fn (array $row): bool => blank($kind) || $row['kind'] === $kind)->all();
    }

    /**
     * Published items of every kind, one query per kind, oldest first.
     *
     * @return Collection<string, array<string, mixed>>
     */
    private function rows(): Collection
    {
        $now = now();

        return collect(self::KINDS)
            ->flatMap(fn (array $classes, string $kind): Collection => $classes[0]::query()->where('status', 'published')->with($this->relations($classes[0]))->get()
                ->map(fn ($record): array => [
                    'key' => $kind.'-'.$record->getKey(),
                    'kind' => $kind,
                    'item' => $this->name($record),
                    'published_at' => $record->published_at,
                    'days' => $record->published_at === null ? null : (int) $record->published_at->diffInDays($now),
                    'edit_url' => $classes[1]::getUrl('edit', ['record' => $record]),
                ]))
            ->sortBy(fn (array $row): string => $row['published_at']?->toIso8601String() ?? '0000')
            ->keyBy('key');
    }

    /**
     * @return list<string>
     */
    private function relations(string $class): array
    {
        return match ($class) {
            PropertyProfile::class => ['property'],
            OfferingProfile::class => ['offering.property', 'offering.product'],
            default => [],
        };
    }

    private function name(mixed $record): string
    {
        return match (true) {
            $record instanceof PropertyProfile => 'SmartGro '.$record->property?->name,
            $record instanceof OfferingProfile => $record->offering?->product?->name.' at SmartGro '.$record->offering?->property?->name,
            $record instanceof Faq => (string) $record->question,
            $record instanceof Testimonial => (string) ($record->person_name ?? 'Testimonial'),
            $record instanceof ContentPage => filled($record->title) ? (string) $record->title : '/'.$record->slug,
            default => (string) $record->title,
        };
    }
}
