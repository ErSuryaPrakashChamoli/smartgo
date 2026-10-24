<?php

namespace App\Filament\Clusters\DesignPreviews\Pages;

use App\Filament\Clusters\DesignPreviews\Concerns\IsDesignPreview;
use App\Filament\Clusters\DesignPreviews\DesignPreviewsCluster;
use App\Services\Design\DesignPreviewContent;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Pricing configuration by property and product (DATABASE_ERD §4; BR-PR-1 to BR-PR-3). No real
 * price exists yet (values PR-1 to PR-11 are decided at the Phase 5 gate), so every real cell is
 * "Not set", which is deliberately different from an explicit "Free (₹0)".
 *
 * @property-read Schema $form
 */
class PricingConfiguration extends Page
{
    use IsDesignPreview;

    protected static ?string $cluster = DesignPreviewsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyRupee;

    protected static ?string $navigationLabel = 'Pricing';

    protected static ?int $navigationSort = 9;

    protected static ?string $title = 'Pricing';

    /**
     * @var array<string, array{label: string, icon: string, classes: string, meaning: string}>
     */
    private const STATES = [
        'missing' => ['label' => 'Not set', 'icon' => 'exclamation-triangle', 'classes' => 'bg-danger-50 text-danger-700 ring-danger-600/20', 'meaning' => 'no price recorded; the website shows nothing'],
        'free' => ['label' => 'Free (₹0)', 'icon' => 'gift', 'classes' => 'bg-success-50 text-success-700 ring-success-600/20', 'meaning' => 'explicitly free of charge'],
        'starting' => ['label' => 'From ₹…', 'icon' => 'arrow-trending-up', 'classes' => 'bg-primary-50 text-primary-700 ring-primary-600/20', 'meaning' => 'starting price'],
        'on_request' => ['label' => 'On request', 'icon' => 'chat-bubble-left-ellipsis', 'classes' => 'bg-gray-50 text-gray-700 ring-gray-600/20', 'meaning' => 'price given after an enquiry'],
        'enquiry_only' => ['label' => 'Enquiry only', 'icon' => 'envelope', 'classes' => 'bg-info-50 text-info-700 ring-info-600/20', 'meaning' => 'no price is stored or shown'],
        'quotation' => ['label' => 'Quotation', 'icon' => 'document-text', 'classes' => 'bg-warning-50 text-warning-700 ring-warning-600/20', 'meaning' => 'custom enterprise proposal'],
    ];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['property' => 'a-70', 'product' => 'meeting-rooms', 'pricing_type' => null, 'is_free' => false]);
    }

    public function form(Schema $schema): Schema
    {
        $content = app(DesignPreviewContent::class);
        $isPriced = fn (Get $get): bool => in_array($get('pricing_type'), ['starting', 'fixed'], true);

        return $schema
            ->components([
                Grid::make(['default' => 1, 'md' => 2])->schema([
                    Select::make('property')
                        ->options(collect($content->properties())->mapWithKeys(fn (array $property): array => [$property['slug'] => $property['name'].', '.$property['area']])->all())
                        ->required(),
                    Select::make('product')
                        ->options(collect($content->categories())->pluck('name', 'slug')->all())
                        ->required(),
                ]),
                Radio::make('pricing_type')
                    ->label('How is this priced?')
                    ->options([
                        'starting' => 'Starting price ("from")',
                        'fixed' => 'Fixed price',
                        'on_request' => 'Price on request',
                        'enquiry_only' => 'Enquiry only: no price stored or shown',
                        'quotation' => 'Custom quotation (enterprise)',
                    ])
                    ->descriptions([
                        'quotation' => 'Prepared per deal in the CRM (Phase 9); never shown as a public price.',
                    ])
                    ->required()
                    ->live(),
                Grid::make(['default' => 1, 'md' => 3])->visible($isPriced)->schema([
                    TextInput::make('amount')
                        ->label('Amount')
                        ->prefix('₹')
                        ->numeric()
                        ->minValue(0)
                        ->required($isPriced)
                        ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            if ((float) $value === 0.0 && ! $get('is_free')) {
                                $fail('Enter a price, or mark this as free of charge. A missing price is never stored as ₹0.');
                            }
                        })
                        ->helperText('Leave empty until SmartGro approves a price.'),
                    Select::make('billing_period')
                        ->options(['hour' => 'Per hour', 'half_day' => 'Per half day', 'day' => 'Per day', 'month' => 'Per month', 'one_off' => 'One-off'])
                        ->required($isPriced),
                    Select::make('unit')
                        ->options(['room' => 'Per room', 'seat' => 'Per seat', 'desk' => 'Per desk', 'company' => 'Per company'])
                        ->required($isPriced),
                    Toggle::make('is_free')->label('Free of charge (₹0)')->helperText('Only when SmartGro decides it costs nothing.'),
                    Select::make('tax_treatment')
                        ->label('GST')
                        ->options(['plus_gst' => 'Price + GST', 'gst_included' => 'GST included'])
                        ->placeholder('Not decided')
                        ->helperText('GST treatment is a Phase 5 pricing decision.'),
                    Radio::make('website_display')
                        ->label('Show on the website as')
                        ->options(['hidden' => 'Hidden', 'from_price' => '"From" price', 'exact' => 'Exact price'])
                        ->helperText('Whether prices are shown at all is undecided (D-04).'),
                    DatePicker::make('valid_from'),
                    DatePicker::make('valid_until'),
                ]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        $content = app(DesignPreviewContent::class);
        $properties = collect($content->properties())->map(fn (array $property): string => $property['name'])->all();
        $products = collect($content->categories())->pluck('name')->all();

        return $schema->components([
            $this->previewNotice('No real prices, taxes, discounts or terms exist yet: values PR-1 to PR-11 are decided at the Phase 5 gate. Nothing here is saved or published.'),
            Section::make('Price coverage')
                ->description('Every product at every property. Missing prices stand out and are never treated as free.')
                ->icon(Heroicon::OutlinedTableCells)
                ->schema([
                    View::make('filament.design-previews.price-coverage')->viewData([
                        'properties' => $properties,
                        'products' => $products,
                        'cells' => collect($products)->mapWithKeys(fn (string $product): array => [$product => array_fill_keys($properties, 'missing')])->all(),
                        'states' => self::STATES,
                    ]),
                ]),
            Section::make('Edit price: A-70 · Meeting rooms')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->schema([
                    Form::make([EmbeddedSchema::make('form')])
                        ->livewireSubmitHandler('save')
                        ->footer([Actions::make([Action::make('save')->label('Submit for approval')->submit('save')])]),
                ]),
            Callout::make('Approval before a price goes live')
                ->description('Creating or changing a price needs pricing.manage. Activation needs pricing.approve held by a different person from the author (BR-PR-2): Finance or the Super Admin under the approved matrix.')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color('gray'),
        ]);
    }
}
