<?php

namespace App\Filament\Clusters\Properties\Resources\OfferingProfiles\Tables;

use App\Enums\Access\Capability;
use App\Enums\Content\RevisionState;
use App\Models\OfferingProfile;
use App\Models\Product;
use App\Models\Property;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OfferingProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'offering.property.city', 'offering.product', 'publishedRevision', 'workingRevision.author', 'workingRevision.reviewer',
            ]))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('offering.product.name')
                    ->label('Workspace category')
                    ->weight('semibold')
                    ->description(fn (OfferingProfile $record): string => 'SmartGro '.$record->offering->property->name.', '.$record->offering->property->city->name),
                TextColumn::make('status')
                    ->label('On the website')
                    ->badge()
                    ->formatStateUsing(fn (string $state, OfferingProfile $record): string => match (true) {
                        ! $record->offering->is_active => 'No longer offered',
                        $state === 'unpublished' => 'Taken down',
                        $state === 'published' && $record->offering->isPubliclyListed() => 'Live: v'.$record->publishedRevision?->number,
                        $state === 'published' => 'Published, property not on the website',
                        default => 'Not published',
                    })
                    ->color(fn (string $state, OfferingProfile $record): string => match (true) {
                        $state === 'published' && $record->offering->isPubliclyListed() => 'success',
                        $state === 'unpublished' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('workingRevision.state')
                    ->label('In progress')
                    ->badge()
                    ->formatStateUsing(fn (RevisionState $state, OfferingProfile $record): string => 'v'.$record->workingRevision?->number.': '.$state->label())
                    ->color(fn (RevisionState $state): string => $state->color())
                    ->placeholder('Nothing in progress'),
                TextColumn::make('published_at')
                    ->label('Last published')
                    ->dateTime('j M Y, H:i', config('backoffice.timezone'))
                    ->placeholder('Never'),
            ])
            ->filters([
                SelectFilter::make('property')
                    ->label('Property')
                    ->options(function (): array {
                        /** @var User $user */
                        $user = auth()->user();

                        return Property::query()->visibleTo($user, Capability::ViewProfiles)->orderBy('code')->pluck('name', 'id')->all();
                    })
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null) ? $query : $query->whereHas('offering', fn (Builder $offering): Builder => $offering->where('property_id', $data['value']))),
                SelectFilter::make('product')
                    ->label('Workspace category')
                    ->options(fn (): array => Product::query()->orderBy('sort_order')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null) ? $query : $query->whereHas('offering', fn (Builder $offering): Builder => $offering->where('product_id', $data['value']))),
            ])
            ->emptyStateHeading('No workspace offerings yet')
            ->emptyStateDescription('Operations staff record which workspace categories each property offers on the property\'s page. Each one then gets a profile here.')
            ->recordActions([
                EditAction::make()->label('Open'),
            ]);
    }
}
