<?php

namespace App\Filament\Clusters\Properties\Resources\Spaces\Schemas;

use App\Enums\Inventory\InventoryMode;
use App\Enums\Inventory\SpaceStatus;
use App\Models\Space;
use App\Services\Inventory\SpaceAvailability;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * One space: what it is, how it is counted and sold, and its availability today as staff see it (exact
 * counts, BR-AV-3) and as a customer would (BR-AV-1). Missing details are stated as text.
 */
class SpaceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Today')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('availability')->label('Availability')
                            ->state(fn (Space $record): string => app(SpaceAvailability::class)->on($record)->summary($record->inventory_mode === InventoryMode::Exclusive)),
                        TextEntry::make('free_seats')->label('Free seats')
                            ->state(fn (Space $record): string => implode(', ', app(SpaceAvailability::class)->on($record)->freeSeats) ?: 'None')
                            ->visible(fn (Space $record): bool => $record->inventory_mode->hasSeats()),
                        TextEntry::make('customer_state')->label('What a customer would see')
                            ->state(fn (Space $record): string => self::customerState($record))
                            ->helperText('Not shown on the website yet. The 90-day horizon is an unconfirmed default (D-36).'),
                    ]),
                Section::make('Space')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('code'),
                        TextEntry::make('name')->state(fn (Space $record): string => $record->name ?? 'No name'),
                        TextEntry::make('property.name')->label('Property')->formatStateUsing(fn (string $state): string => 'SmartGro '.$state),
                        TextEntry::make('floor')->label('Floor')->state(fn (Space $record): string => $record->floor->displayName()),
                        TextEntry::make('spaceType.name')->label('Type'),
                        TextEntry::make('inventory_mode')->label('Counted as')->formatStateUsing(fn (InventoryMode $state): string => $state->label().'. '.$state->description()),
                        TextEntry::make('capacity')->label(fn (Space $record): string => $record->inventory_mode->hasSeats() ? 'Seats in use' : 'Seats')
                            ->state(fn (Space $record): string => (string) app(SpaceAvailability::class)->capacityOf($record)),
                        TextEntry::make('area_sqft')->label('Area')->state(fn (Space $record): string => $record->area_sqft !== null ? number_format($record->area_sqft).' sq ft' : 'Not recorded'),
                        TextEntry::make('operational_status')->label('Status')->badge()
                            ->formatStateUsing(fn (SpaceStatus $state): string => $state->label())
                            ->color(fn (SpaceStatus $state): string => $state->color()),
                        TextEntry::make('is_publicly_listable')->label('May be shown to customers')->state(fn (Space $record): string => $record->is_publicly_listable ? 'Yes' : 'No'),
                        TextEntry::make('sold_as')->label('Can be sold as')->columnSpanFull()
                            ->state(fn (Space $record): string => $record->offerings()->with('product')->get()->map(fn ($offering): string => $offering->product->name)->implode(', ') ?: 'Not yet: no category chosen'),
                    ]),
            ]);
    }

    private static function customerState(Space $record): string
    {
        $today = app(SpaceAvailability::class)->on($record);

        return $today->customerState->label().($today->customerState->value === 'available_from' ? ' '.$today->freeFrom?->format('j M Y') : '');
    }
}
