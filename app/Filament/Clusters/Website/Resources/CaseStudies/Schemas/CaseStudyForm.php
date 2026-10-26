<?php

namespace App\Filament\Clusters\Website\Resources\CaseStudies\Schemas;

use App\Models\CaseStudy;
use App\Models\Contracts\RequiresConsent;
use App\Services\Content\BlockLibrary;
use App\Services\Content\ContentRules;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * The customer story editor. The rules on CaseStudy are the authority; consent must be on file before
 * a version can be submitted (BR-CMS-7).
 */
class CaseStudyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Customer story')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Title')
                            ->required()
                            ->maxLength(120),
                        TextInput::make('slug')
                            ->label('Address')
                            ->prefix(url('/customers').'/')
                            ->required()
                            ->maxLength(150)
                            ->regex(ContentRules::SLUG_PATTERN)
                            ->validationMessages(['regex' => 'Use lowercase letters, numbers and hyphens (for example acme-design-studio).'])
                            ->helperText('If a published story gets a new address, the old address redirects to it once the change is published.'),
                        TextInput::make('company_name')
                            ->label('Customer')
                            ->markAsRequired()
                            ->maxLength(120),
                        Textarea::make('summary')
                            ->label('Summary')
                            ->markAsRequired()
                            ->maxLength(300)
                            ->rows(2)
                            ->helperText('Shown under the title and in the list of stories. Plain text.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Consent')
                    ->description('A customer story is published only with the customer\'s written consent on file (BR-CMS-7).')
                    ->columns(2)
                    ->schema([
                        Toggle::make('consent_on_file')
                            ->label('The customer\'s consent to publish this story is on file')
                            ->markAsRequired()
                            ->inline(false),
                        TextInput::make('consent_reference')
                            ->label('Where the consent is kept')
                            ->markAsRequired()
                            ->maxLength(120)
                            ->helperText(fn (?Model $record): string => $record instanceof RequiresConsent && $record->consentWithdrawn()
                                ? 'Consent was withdrawn: enter the reference of the new consent. A reference used before is refused.'
                                : 'For example the signed form or email reference.'),
                        Toggle::make('consent_reconfirmed')
                            ->label('Consent was given again after it was withdrawn, and is recorded under the reference above')
                            ->visible(fn (?Model $record): bool => $record instanceof RequiresConsent && $record->consentWithdrawn())
                            ->markAsRequired()
                            ->inline(false)
                            ->columnSpanFull(),
                    ]),
                Section::make('Sections')
                    ->description('Sections appear in the story in this order.')
                    ->schema([
                        Builder::make('blocks')
                            ->hiddenLabel()
                            ->blocks(app(BlockLibrary::class)->editorBlocks(CaseStudy::BLOCK_TYPES))
                            ->maxItems(BlockLibrary::MAX_BLOCKS)
                            ->addActionLabel('Add section')
                            ->blockNumbers()
                            ->collapsible(),
                    ]),
            ]);
    }
}
