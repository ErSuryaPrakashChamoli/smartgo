<?php

namespace App\Filament\Widgets;

use App\Enums\Content\RevisionState;
use App\Filament\Clusters\Properties\Resources\OfferingProfiles\OfferingProfileResource;
use App\Filament\Clusters\Properties\Resources\PropertyProfiles\PropertyProfileResource;
use App\Filament\Clusters\Website\Resources\CaseStudies\CaseStudyResource;
use App\Filament\Clusters\Website\Resources\Faqs\FaqResource;
use App\Filament\Clusters\Website\Resources\NavigationMenus\NavigationMenuResource;
use App\Filament\Clusters\Website\Resources\Pages\PageResource;
use App\Filament\Clusters\Website\Resources\Posts\PostResource;
use App\Filament\Clusters\Website\Resources\RedirectMaps\RedirectMapResource;
use App\Filament\Clusters\Website\Resources\SiteSettings\SiteSettingResource;
use App\Filament\Clusters\Website\Resources\Testimonials\TestimonialResource;
use App\Models\CaseStudy;
use App\Models\Faq;
use App\Models\NavigationMenu;
use App\Models\OfferingProfile;
use App\Models\Page;
use App\Models\Post;
use App\Models\PropertyProfile;
use App\Models\RedirectMap;
use App\Models\Revision;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The versions waiting for the signed-in user's approval, across all website content (UJ-8). Only
 * content the user may approve is listed; a version they worked on is marked, because someone else
 * must approve it (BR-CMS-2).
 */
class WaitingForApproval extends TableWidget
{
    /**
     * The editor of each kind of content.
     *
     * @var array<class-string<Model>, class-string>
     */
    private const array EDITORS = [
        Page::class => PageResource::class,
        Post::class => PostResource::class,
        CaseStudy::class => CaseStudyResource::class,
        Testimonial::class => TestimonialResource::class,
        Faq::class => FaqResource::class,
        PropertyProfile::class => PropertyProfileResource::class,
        OfferingProfile::class => OfferingProfileResource::class,
        NavigationMenu::class => NavigationMenuResource::class,
        SiteSetting::class => SiteSettingResource::class,
        RedirectMap::class => RedirectMapResource::class,
    ];

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user !== null && (self::approvableTypes($user) !== [] || SiteSetting::handlesVisibleTo($user) !== []);
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();

        return $table
            ->heading('Waiting for approval')
            ->description('Versions submitted for someone other than their authors to approve. Open one to preview it and approve or reject it.')
            ->query(fn (): Builder => $this->approvableRevisions($user))
            ->defaultSort('submitted_at')
            ->columns([
                TextColumn::make('revisionable_type')
                    ->label('Content')
                    ->weight('semibold')
                    ->formatStateUsing(fn (Revision $record): string => $this->title($record))
                    ->description(fn (Revision $record): string => $this->kind($record->revisionable).', version '.$record->number),
                TextColumn::make('author.name')
                    ->label('Written by')
                    ->description(fn (Revision $record): ?string => $record->author_id === $user->getKey() || in_array($user->getKey(), $record->editor_ids ?? [], true)
                        ? 'You worked on this version, so someone else must approve it.'
                        : null)
                    ->wrap(),
                TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->since(config('backoffice.timezone'))
                    ->dateTimeTooltip('j M Y, H:i', config('backoffice.timezone')),
            ])
            ->recordUrl(fn (Revision $record): ?string => $this->editorUrl($record))
            ->emptyStateHeading('Nothing is waiting for your approval')
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    /**
     * @return list<class-string<Model>>
     */
    private static function approvableTypes(User $user): array
    {
        return array_values(array_filter(
            array_keys(self::EDITORS),
            fn (string $model): bool => $model !== SiteSetting::class && $user->can((new $model)->revisionCapabilities()['approve']),
        ));
    }

    /**
     * @return Builder<Revision>
     */
    private function approvableRevisions(User $user): Builder
    {
        $types = array_map(fn (string $model): string => (new $model)->getMorphClass(), self::approvableTypes($user));
        $settingHandles = array_values(array_filter(
            SiteSetting::handlesVisibleTo($user),
            fn (string $handle): bool => $user->can((new SiteSetting)->forceFill(['handle' => $handle])->revisionCapabilities()['approve']),
        ));

        return Revision::query()
            ->with(['revisionable', 'author'])
            ->where('state', RevisionState::Submitted)
            ->where(fn (Builder $query): Builder => $query
                ->whereIn('revisionable_type', $types === [] ? [''] : $types)
                ->when($settingHandles !== [], fn (Builder $query): Builder => $query->orWhereHasMorph('revisionable', [SiteSetting::class], fn (Builder $query): Builder => $query->whereIn('handle', $settingHandles))));
    }

    private function editorUrl(Revision $revision): ?string
    {
        $resource = self::EDITORS[$revision->revisionable::class] ?? null;

        return $resource === null ? null : $resource::getUrl('edit', ['record' => $revision->revisionable]);
    }

    private function kind(Model $content): string
    {
        return match (true) {
            $content instanceof Page => 'Page',
            $content instanceof Post => 'Article',
            $content instanceof CaseStudy => 'Customer story',
            $content instanceof Testimonial => 'Testimonial',
            $content instanceof Faq => 'FAQ',
            $content instanceof PropertyProfile => 'Property profile',
            $content instanceof OfferingProfile => 'Workspace offering',
            $content instanceof NavigationMenu => 'Menu',
            $content instanceof SiteSetting => 'Settings',
            default => 'Redirects',
        };
    }

    private function title(Revision $revision): string
    {
        $content = $revision->revisionable;
        $payload = $revision->payload;

        return (string) match (true) {
            $content instanceof Testimonial => 'From '.($payload['person_name'] ?? 'unnamed'),
            $content instanceof Faq => $payload['question'] ?? 'FAQ',
            $content instanceof PropertyProfile => 'SmartGro '.$content->property->name,
            $content instanceof OfferingProfile => $content->offering->displayName(),
            $content instanceof NavigationMenu => $content->name,
            $content instanceof SiteSetting => $content->group()['name'],
            $content instanceof RedirectMap => 'Manual redirects',
            default => $payload['title'] ?? 'Untitled',
        };
    }
}
