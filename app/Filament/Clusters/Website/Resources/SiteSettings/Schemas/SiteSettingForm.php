<?php

namespace App\Filament\Clusters\Website\Resources\SiteSettings\Schemas;

use App\Models\SiteSetting;
use Closure;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The editor of one settings group; only that group's fields are shown. SiteSettingPayload is the
 * authority for every format. Nothing is filled in by default: values come from the owner.
 */
class SiteSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        $isGroup = fn (string $handle): Closure => fn (?SiteSetting $record): bool => $record?->handle === $handle;

        return $schema
            ->columns(1)
            ->components([
                Section::make('Contact details')
                    ->description('Shown in the website footer once published. Leave a field empty to hide it.')
                    ->columns(2)
                    ->visible($isGroup('contact'))
                    ->schema([
                        TextInput::make('phone')->label('Phone')->tel()->maxLength(20)->helperText('For example +91 120 000 0000.'),
                        TextInput::make('whatsapp')->label('WhatsApp number')->maxLength(16)->helperText('International format: + and the country code, digits only.'),
                        TextInput::make('email')->label('Email')->email()->maxLength(120),
                        TextInput::make('opening_hours')->label('Opening hours')->maxLength(120)->helperText('Plain text, for example Monday to Saturday, 9:00 to 19:00.'),
                        Textarea::make('address')->label('Address')->maxLength(300)->rows(3)->columnSpanFull(),
                    ]),
                Section::make('Social links')
                    ->description('Links to SmartGro profiles, shown in the footer once published. Each must be an https link to that network.')
                    ->columns(2)
                    ->visible($isGroup('social'))
                    ->schema([
                        TextInput::make('linkedin')->label('LinkedIn')->url()->maxLength(255),
                        TextInput::make('instagram')->label('Instagram')->url()->maxLength(255),
                        TextInput::make('facebook')->label('Facebook')->url()->maxLength(255),
                        TextInput::make('x')->label('X')->url()->maxLength(255),
                        TextInput::make('youtube')->label('YouTube')->url()->maxLength(255),
                    ]),
                Section::make('Tracking IDs')
                    ->description('Approved IDs are stored, but no tracking code is added to the website until cookie consent is decided (OD-100). Adding an ID never allows a new script domain.')
                    ->columns(3)
                    ->visible($isGroup('tracking'))
                    ->schema([
                        TextInput::make('ga4_measurement_id')->label('Google Analytics 4 measurement ID')->maxLength(14)->helperText('G-XXXXXXXXXX'),
                        TextInput::make('gtm_container_id')->label('Google Tag Manager container ID')->maxLength(14)->helperText('GTM-XXXXXXX. Its tags also need approval (OD-75).'),
                        TextInput::make('meta_pixel_id')->label('Meta pixel ID')->maxLength(20)->helperText('Digits only.'),
                    ]),
                Section::make('Legal entity details')
                    ->description('Checked by a second person against the registration documents before use (OD-55). Not shown on the website.')
                    ->columns(2)
                    ->visible($isGroup('legal'))
                    ->schema([
                        TextInput::make('legal_name')->label('Legal name')->maxLength(200)->markAsRequired(),
                        TextInput::make('gstin')->label('GSTIN')->maxLength(15),
                        Textarea::make('registered_address')->label('Registered address')->maxLength(400)->rows(3)->markAsRequired(),
                        TextInput::make('cin')->label('Company identification number (CIN)')->maxLength(21),
                    ]),
            ]);
    }
}
