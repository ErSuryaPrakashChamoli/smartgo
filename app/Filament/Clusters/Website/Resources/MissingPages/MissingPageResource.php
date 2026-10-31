<?php

namespace App\Filament\Clusters\Website\Resources\MissingPages;

use App\Filament\Clusters\Website\Resources\MissingPages\Pages\ListMissingPages;
use App\Filament\Clusters\Website\WebsiteCluster;
use App\Models\MissingPage;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Addresses on the website that answered "not found" (FR-SEO-4's 404 monitor), most asked for first, so
 * staff can add a redirect where an old or mistyped address deserves one (Site setup → Redirects, with
 * its approval). Only the path and how often and when: nothing about who asked. An entry that is only
 * noise can be dismissed; that is audited. Hidden on a database without the table.
 */
class MissingPageResource extends Resource
{
    protected static ?string $model = MissingPage::class;

    protected static ?string $cluster = WebsiteCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'Site setup';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Missing pages';

    protected static ?string $modelLabel = 'missing page';

    protected static ?string $slug = 'missing-pages';

    protected static bool $isGloballySearchable = false;

    public static function canAccess(): bool
    {
        return MissingPage::isAvailable() && parent::canAccess();
    }

    public static function table(Table $table): Table
    {
        $timezone = (string) config('backoffice.timezone');

        return $table
            ->defaultSort('hits', 'desc')
            ->description('Addresses visitors or links asked for that do not exist. Add a redirect for those that should lead somewhere: Site setup → Redirects, with its approval. Removed addresses (410) are not listed.')
            ->columns([
                TextColumn::make('path')->label('Address')->weight('semibold')->searchable()->copyable()->wrap(),
                TextColumn::make('hits')->label('Times asked for')->numeric()->sortable(),
                TextColumn::make('last_seen_at')->label('Last asked for')->since($timezone)->dateTimeTooltip('j M Y, H:i', $timezone)->sortable(),
                TextColumn::make('first_seen_at')->label('First asked for')->dateTime('j M Y', $timezone)->sortable()->toggleable(),
            ])
            ->recordActions([
                Action::make('dismiss')
                    ->label('Dismiss')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('It is listed again if it is asked for again.')
                    ->visible(fn (MissingPage $record): bool => Gate::allows('delete', $record))
                    ->action(function (MissingPage $record): void {
                        /** @var User $user */
                        $user = auth()->user();
                        Gate::forUser($user)->authorize('delete', $record);

                        activity('redirects')
                            ->causedBy($user)
                            ->event('missing_page_dismissed')
                            ->withProperties(['path' => $record->path, 'hits' => $record->hits])
                            ->log('Missing page dismissed');
                        $record->delete();

                        Notification::make()->success()->title('Dismissed')->send();
                    }),
            ])
            ->emptyStateHeading('No missing pages')
            ->emptyStateDescription('Addresses that answer "not found" are listed here.')
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMissingPages::route('/'),
        ];
    }
}
