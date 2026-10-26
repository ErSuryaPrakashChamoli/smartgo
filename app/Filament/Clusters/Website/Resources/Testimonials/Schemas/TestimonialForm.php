<?php

namespace App\Filament\Clusters\Website\Resources\Testimonials\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The testimonial editor. The rules on Testimonial are the authority; consent and a verified statement
 * are required before a version can be submitted (BR-CMS-7).
 */
class TestimonialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Testimonial')
                    ->columns(3)
                    ->schema([
                        Textarea::make('quote')
                            ->label('Statement')
                            ->markAsRequired()
                            ->maxLength(600)
                            ->rows(4)
                            ->helperText('Exactly as the person gave it. Plain text, without quotation marks.')
                            ->columnSpanFull(),
                        TextInput::make('person_name')
                            ->label('Name')
                            ->required()
                            ->maxLength(80),
                        TextInput::make('person_title')
                            ->label('Job title')
                            ->maxLength(80),
                        TextInput::make('company_name')
                            ->label('Company')
                            ->maxLength(120),
                    ]),
                Section::make('Consent and verification')
                    ->description('A testimonial is published only with the person\'s consent on file and a statement checked against the original (BR-CMS-7).')
                    ->columns(2)
                    ->schema([
                        Toggle::make('consent_on_file')
                            ->label('The person\'s consent to publish this statement with their name is on file')
                            ->markAsRequired()
                            ->inline(false),
                        TextInput::make('consent_reference')
                            ->label('Where the consent is kept')
                            ->markAsRequired()
                            ->maxLength(120)
                            ->helperText('For example the signed form or email reference.'),
                        Toggle::make('statement_verified')
                            ->label('The statement is real and matches the original word for word')
                            ->markAsRequired()
                            ->inline(false)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
