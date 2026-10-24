<?php

namespace App\Filament\Clusters\DesignPreviews\Pages;

use App\Filament\Clusters\DesignPreviews\Concerns\IsDesignPreview;
use App\Filament\Clusters\DesignPreviews\DesignPreviewsCluster;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Website content management (BR-CMS-1 to BR-CMS-8, BR-MED-1 to BR-MED-4). Draft → Submitted →
 * Approved → Published, with approval by a different person. Nothing from the old website is
 * accepted, and testimonials need consent on file. Nothing is saved or published from this preview.
 *
 * @property-read Schema $form
 */
class WebsiteContent extends Page implements HasTable
{
    use InteractsWithTable;
    use IsDesignPreview;

    protected static ?string $cluster = DesignPreviewsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Website content';

    protected static ?int $navigationSort = 14;

    protected static ?string $title = 'Website content';

    protected ?string $subheading = 'Editing: Noida city page · Draft, last changed by you';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'title' => 'Coworking and office spaces in Noida',
            'slug' => 'noida',
            'blocks' => [
                ['type' => 'hero', 'data' => ['heading' => 'Coworking and office spaces in Noida', 'intro' => 'Draft copy: final wording is written in Phase 6.', 'cta_label' => 'Book a tour', 'cta_target' => 'tour']],
                ['type' => 'faq', 'data' => ['items' => [['question' => 'Which area of Noida suits my team?', 'answer' => null]]]],
            ],
            'meta_title' => 'Coworking and office spaces in Noida | SmartGro',
            'is_indexable' => true,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Page')->columns(2)->schema([
                    TextInput::make('title')->required()->maxLength(120),
                    TextInput::make('slug')->label('Page address')->prefix('/locations/')->required()
                        ->helperText('Changing the address of a published page creates a redirect automatically (BR-CMS-6).'),
                ]),
                Section::make('Sections')->schema([
                    Builder::make('blocks')
                        ->hiddenLabel()
                        ->blocks([
                            Block::make('hero')->label('Introduction')->icon(Heroicon::OutlinedSparkles)->schema([
                                TextInput::make('heading')->required()->maxLength(90),
                                Textarea::make('intro')->rows(2),
                                TextInput::make('cta_label')->label('Button text')->required(),
                                Select::make('cta_target')->label('Button goes to')->options(['tour' => 'Book a tour', 'enquiry' => 'Enquiry form', 'enterprise' => 'Enterprise page'])->required(),
                            ]),
                            Block::make('text')->label('Text section')->icon(Heroicon::OutlinedBars3BottomLeft)->schema([
                                TextInput::make('heading'),
                                Textarea::make('body')->rows(4)->required(),
                            ]),
                            Block::make('faq')->label('FAQs')->icon(Heroicon::OutlinedQuestionMarkCircle)->schema([
                                Repeater::make('items')->schema([
                                    TextInput::make('question')->required(),
                                    Textarea::make('answer')->rows(2)->placeholder('Answer to be supplied by SmartGro'),
                                ])->addActionLabel('Add question'),
                            ]),
                            Block::make('media')->label('Image')->icon(Heroicon::OutlinedPhoto)->schema([
                                Select::make('asset')->label('From the media library')->options([])->placeholder('No approved media yet'),
                                TextInput::make('alt_text')->label('Alt text')->required()->helperText('Required unless the image is decorative (BR-MED-4).'),
                                Select::make('origin')->required()->options([
                                    'client_photography' => 'SmartGro photography',
                                    'commissioned' => 'Commissioned',
                                    'licensed_stock' => 'Licensed stock',
                                    'ai_generated_concept' => 'AI concept (labelled "Concept visual")',
                                    'render' => 'Render (labelled "Concept visual")',
                                ])->helperText('Concept visuals and renders never appear in a property gallery (BR-MED-2).'),
                                TextInput::make('licence_reference')->label('Licence reference'),
                                Checkbox::make('not_from_old_website')
                                    ->label('This file is not taken from the old smartgro.space website')
                                    ->accepted()
                                    ->validationMessages(['accepted' => 'Nothing from the old website may be reused (OD-19).']),
                            ]),
                            Block::make('testimonial')->label('Testimonial')->icon(Heroicon::OutlinedChatBubbleBottomCenterText)->schema([
                                Textarea::make('quote')->required()->rows(2),
                                TextInput::make('person')->required(),
                                TextInput::make('consent_reference')->label('Consent record')->required()->helperText('Signed consent from the person quoted.'),
                                Checkbox::make('verified')
                                    ->label('I have checked that this is a real, unedited statement')
                                    ->accepted()
                                    ->validationMessages(['accepted' => 'Testimonials need consent on file and verification (BR-CMS-7).']),
                            ]),
                        ])
                        ->addActionLabel('Add section')
                        ->collapsible(),
                ]),
                Section::make('Search engines')->columns(2)->schema([
                    TextInput::make('meta_title')->label('Title in search results')->maxLength(60)->required(),
                    Toggle::make('is_indexable')->label('Allow search engines to index this page')->inline(false),
                    Textarea::make('meta_description')->label('Description in search results')->maxLength(160)->rows(2)->columnSpanFull(),
                ]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->previewNotice('No page is published yet and no real copy exists: the text is a draft placeholder. Saving, submitting and publishing do nothing here.'),
            Callout::make('Content rules')
                ->description('Nothing is copied from the old smartgro.space website: no text, images, files or data (OD-19, BR-CMS-8). Testimonials and case studies need a real, consented source (BR-CMS-7); client logos and business claims need SmartGro\'s written confirmation. Authors cannot approve their own changes (BR-CMS-2).')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color('gray'),
            EmbeddedTable::make(),
            Section::make('Edit: Noida city page')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->schema([
                    Form::make([EmbeddedSchema::make('form')])->livewireSubmitHandler('save'),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('saveDraft')->label('Save draft')->color('gray')->action(fn () => $this->save()),
            Action::make('submitForReview')->label('Submit for review')->requiresConfirmation()->action(fn () => $this->save()),
            Action::make('approve')->label('Approve')->color('success')->disabled()->tooltip('You cannot approve your own changes (BR-CMS-2).'),
            Action::make('publish')->label('Publish')->disabled()->tooltip('Only an approved version can be published.'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pages')
            ->records(fn (): array => [
                1 => ['page' => 'Home', 'path' => '/', 'status' => 'Design under owner review', 'tone' => 'warning'],
                2 => ['page' => 'Locations', 'path' => '/locations', 'status' => 'Not written', 'tone' => 'gray'],
                3 => ['page' => 'Noida city page', 'path' => '/locations/noida', 'status' => 'Draft', 'tone' => 'info'],
                4 => ['page' => 'Property profiles (A-70, A-71, A-23, H-105)', 'path' => '/locations/noida/…', 'status' => 'Not written', 'tone' => 'gray'],
                5 => ['page' => 'Workspace pages (7)', 'path' => '/workspaces/…', 'status' => 'Not written', 'tone' => 'gray'],
                6 => ['page' => 'Enterprise', 'path' => '/enterprise', 'status' => 'Not written', 'tone' => 'gray'],
                7 => ['page' => 'About and contact', 'path' => '/about, /contact', 'status' => 'Waiting for SmartGro details', 'tone' => 'warning'],
                8 => ['page' => 'Privacy, terms and cookies', 'path' => '/legal/…', 'status' => 'Waiting for legal review (D-15)', 'tone' => 'warning'],
            ])
            ->columns([
                TextColumn::make('page')->weight(FontWeight::SemiBold),
                TextColumn::make('path')->color('gray'),
                TextColumn::make('status')->badge()->color(fn (array $record): string => $record['tone']),
            ])
            ->paginated(false);
    }
}
