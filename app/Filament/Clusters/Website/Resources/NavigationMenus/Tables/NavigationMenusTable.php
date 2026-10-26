<?php

namespace App\Filament\Clusters\Website\Resources\NavigationMenus\Tables;

use App\Enums\Content\RevisionState;
use App\Models\NavigationMenu;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The four menus with their live version and the version being worked on.
 */
class NavigationMenusTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['publishedRevision.publisher', 'workingRevision.author', 'workingRevision.reviewer'])->withCount('items'))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('name')
                    ->label('Menu')
                    ->weight('semibold')
                    ->description(fn (NavigationMenu $record): string => $record->structure()['purpose'])
                    ->wrap(),
                TextColumn::make('published_revision_id')
                    ->label('On the website')
                    ->badge()
                    ->getStateUsing(fn (NavigationMenu $record): string => $record->isPublished() ? 'Live: v'.$record->publishedRevision?->number : 'Not published')
                    ->color(fn (NavigationMenu $record): string => $record->isPublished() ? 'success' : 'gray')
                    ->description(fn (NavigationMenu $record): ?string => $record->isPublished() ? $record->items_count.' items' : null),
                TextColumn::make('workingRevision.state')
                    ->label('In progress')
                    ->badge()
                    ->formatStateUsing(fn (RevisionState $state, NavigationMenu $record): string => 'v'.$record->workingRevision?->number.': '.$state->label())
                    ->color(fn (RevisionState $state): string => $state->color())
                    ->placeholder('Nothing in progress'),
                TextColumn::make('published_at')
                    ->label('Last published')
                    ->dateTime('j M Y, H:i', config('backoffice.timezone'))
                    ->placeholder('Never'),
            ])
            ->recordActions([
                EditAction::make()->label('Open'),
            ])
            ->paginated(false);
    }
}
