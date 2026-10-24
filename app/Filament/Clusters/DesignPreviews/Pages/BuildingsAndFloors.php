<?php

namespace App\Filament\Clusters\DesignPreviews\Pages;

use App\Filament\Clusters\DesignPreviews\Concerns\IsDesignPreview;
use App\Filament\Clusters\DesignPreviews\DesignPreviewsCluster;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\UnorderedList;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Buildings and floors (DATABASE_ERD §3: buildings, floors). The floors of the four real
 * properties are not allocated yet (OD-32), so the populated layout uses a fictional property.
 *
 * @property-read Schema $form
 */
class BuildingsAndFloors extends Page
{
    use IsDesignPreview;

    protected static ?string $cluster = DesignPreviewsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquare3Stack3d;

    protected static ?string $navigationLabel = 'Buildings and floors';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Buildings and floors';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'property' => 'sample',
            'floors' => [
                ['building' => 'sample-a', 'building_level' => 0, 'label' => 'Ground floor'],
                ['building' => 'sample-a', 'building_level' => 1, 'label' => 'First floor'],
                ['building' => 'sample-b', 'building_level' => 2, 'label' => 'Second floor'],
            ],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $buildings = ['sample-a' => 'Sample building A (primary)', 'sample-b' => 'Sample building B'];

        return $schema
            ->components([
                Section::make('Property')
                    ->schema([
                        Select::make('property')
                            ->options([
                                'a-70' => 'A-70, Sector 2 (no floors yet)',
                                'a-71' => 'A-71, Sector 2 (no floors yet)',
                                'a-23' => 'A-23, Sector 3 (no floors yet)',
                                'h-105' => 'H-105, Sector 62 (no floors yet)',
                                'sample' => 'Sample property (fictional, preview only)',
                            ])
                            ->required(),
                    ]),
                Section::make('Floors')
                    ->description('Each floor belongs to one building of the property. Spaces are added to floors on the inventory screens.')
                    ->schema([
                        Repeater::make('floors')
                            ->hiddenLabel()
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 3])->schema([
                                    Select::make('building')->options($buildings)->required(),
                                    TextInput::make('building_level')->label('Level')->numeric()->required()->helperText('0 = ground, -1 = basement'),
                                    TextInput::make('label')->required()->maxLength(60),
                                ]),
                                FileUpload::make('floor_plan')
                                    ->label('Floor plan')
                                    ->helperText('PDF, PNG or SVG. Used for the interactive floor plan on the property page.')
                                    ->disabled(),
                            ])
                            ->itemLabel(fn (array $state): string => $state['label'] ?? 'New floor')
                            ->collapsible()
                            ->addActionLabel('Add floor'),
                    ]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->previewNotice('Saving does nothing. Floors are created for real in the inventory module (Phase 5).'),
            Callout::make('The four real properties have no floors yet')
                ->description('SmartGro has 10 floors across A-70, A-71, A-23 and H-105, but their allocation is pending (OD-32). This screen is shown with the fictional Sample property.')
                ->warning(),
            Form::make([EmbeddedSchema::make('form')])
                ->livewireSubmitHandler('save')
                ->footer([Actions::make([Action::make('save')->label('Save floors')->submit('save')])]),
            Section::make('Rules checked when saving')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->compact()
                ->schema([
                    UnorderedList::make([
                        Text::make('Each level is used only once in a building.'),
                        Text::make('A property has exactly one primary building.'),
                        Text::make('A floor with spaces cannot be removed; its spaces must be moved or decommissioned first.'),
                        Text::make('Every change is recorded in the audit log.'),
                    ]),
                ]),
        ]);
    }
}
