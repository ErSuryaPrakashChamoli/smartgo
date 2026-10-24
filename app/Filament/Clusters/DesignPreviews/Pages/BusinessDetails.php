<?php

namespace App\Filament\Clusters\DesignPreviews\Pages;

use App\Filament\Clusters\DesignPreviews\Concerns\IsDesignPreview;
use App\Filament\Clusters\DesignPreviews\DesignPreviewsCluster;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Public contact details and registered legal entity (D-01). The SmartGro brand (OD-01, OD-40) is
 * kept apart from the legal entity that appears on invoices. Nothing missing is ever filled in:
 * each field shows whether it is missing or still unverified.
 *
 * @property-read Schema $form
 */
class BusinessDetails extends Page
{
    use IsDesignPreview;

    protected static ?string $cluster = DesignPreviewsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $navigationLabel = 'Contact and legal details';

    protected static ?int $navigationSort = 13;

    protected static ?string $title = 'Contact and legal details';

    /**
     * Format of an Indian GSTIN: state code, PAN, entity number, "Z", check character.
     */
    public const GSTIN_PATTERN = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'brand_name' => 'SmartGro',
            'tagline' => 'WORK SMART. GROW TOGETHER.',
            'domain' => 'smartgro.space',
            'show_contact_on_website' => false,
            'legal_verified' => false,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $missing = fn (string $supplier = 'SmartGro'): \Closure => fn (?string $state): string => blank($state)
            ? "Missing: to be supplied by {$supplier}."
            : 'Entered, not yet verified.';

        return $schema
            ->components([
                Section::make('Brand')
                    ->description('Confirmed by the owner (OD-01, OD-40). This is the brand, not the legal entity.')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->columns(3)
                    ->schema([
                        TextInput::make('brand_name')->label('Public name')->disabled(),
                        TextInput::make('tagline')->disabled(),
                        TextInput::make('domain')->disabled(),
                    ]),
                Section::make('Public contact details')
                    ->description('Shown on the website, in emails and on the contact page once verified.')
                    ->icon(Heroicon::OutlinedPhone)
                    ->columns(2)
                    ->schema([
                        TextInput::make('business_email')->label('Business email')->email()->helperText($missing()),
                        TextInput::make('phone')->label('Phone number')->tel()->helperText($missing()),
                        Textarea::make('business_address')->label('Business address')->rows(3)->helperText($missing()),
                        TextInput::make('whatsapp')->label('WhatsApp number')->disabled()->helperText('Not decided: WhatsApp use depends on D-10.'),
                        Toggle::make('show_contact_on_website')
                            ->label('Show these details on the website')
                            ->disabled()
                            ->helperText('Available once every field above is entered and verified.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Registered legal entity')
                    ->description('Used on invoices, legal pages and contracts. It may differ from the brand name.')
                    ->icon(Heroicon::OutlinedBuildingLibrary)
                    ->columns(2)
                    ->schema([
                        TextInput::make('legal_name')->label('Registered legal name')->helperText($missing()),
                        Select::make('entity_type')->label('Type of entity')->placeholder('Missing')->options([
                            'private_limited' => 'Private limited company',
                            'llp' => 'Limited liability partnership',
                            'partnership' => 'Partnership firm',
                            'proprietorship' => 'Sole proprietorship',
                        ]),
                        Textarea::make('registered_address')->label('Registered address')->rows(3)->helperText($missing()),
                        TextInput::make('gstin')
                            ->label('GSTIN')
                            ->maxLength(15)
                            ->regex(self::GSTIN_PATTERN)
                            ->validationMessages(['regex' => 'Enter a 15-character GSTIN: state code, PAN, entity number, "Z" and a check character, in capital letters.'])
                            ->helperText($missing()),
                        Toggle::make('legal_verified')
                            ->label('Verified against the registration documents')
                            ->disabled()
                            ->helperText('Verification by a second person (proposed: Finance or the Super Admin; needs owner confirmation).')
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->previewNotice('Only the brand facts are confirmed. Every contact and legal field starts empty; nothing is invented, saved or published.'),
            Callout::make('No invoices with missing or unverified details')
                ->description('Invoices cannot be issued, and legal pages cannot show company details, until the registered legal name, registered address and GSTIN are entered and verified (D-01). The brand name is never used as a substitute for the legal name.')
                ->danger(),
            Form::make([EmbeddedSchema::make('form')])
                ->livewireSubmitHandler('save')
                ->footer([Actions::make([Action::make('save')->label('Save details')->submit('save')])]),
        ]);
    }
}
