<?php

namespace App\Filament\Clusters\DesignPreviews\Pages;

use App\Filament\Clusters\DesignPreviews\Concerns\IsDesignPreview;
use App\Filament\Clusters\DesignPreviews\DesignPreviewsCluster;
use App\Services\Design\DesignPreviewContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Cities and their public visibility (OD-16, OD-18, OD-22; BR-PROP-4). "Operating" is derived from
 * published properties and never typed in; visibility is the administrator's switch.
 */
class Cities extends Page implements HasTable
{
    use InteractsWithTable;
    use IsDesignPreview;

    protected static ?string $cluster = DesignPreviewsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAsiaAustralia;

    protected static ?string $navigationLabel = 'Cities';

    protected static ?int $navigationSort = 8;

    protected static ?string $title = 'Cities';

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->previewNotice('The five canonical cities (OD-16). Noida is shown visible, as it will be at launch; the others are hidden by default (OD-18). Switching visibility saves nothing here.'),
            EmbeddedTable::make(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addCity')
                ->label('Add city')
                ->icon(Heroicon::OutlinedPlus)
                ->schema([
                    TextInput::make('name')->label('Official name')->required(),
                    TagsInput::make('aliases')->label('Old or alternative names')->helperText('Each alias redirects permanently to this city, as "Gurgaon" does to Gurugram.'),
                ])
                ->action(fn () => $this->save()),
        ];
    }

    public function table(Table $table): Table
    {
        $properties = collect(app(DesignPreviewContent::class)->properties())->countBy('citySlug');

        return $table
            ->records(fn (): array => collect(app(DesignPreviewContent::class)->cities())
                ->mapWithKeys(function (array $city) use ($properties): array {
                    $count = $properties[$city['slug']] ?? 0;
                    $isVisible = $city['slug'] === 'noida';

                    return [$city['slug'] => [
                        'name' => $city['name'],
                        'aliases' => $city['aliases'] === [] ? '—' : collect($city['aliases'])->map(fn (string $alias): string => ucfirst($alias).' → redirects')->implode(', '),
                        'properties' => $count,
                        'operating' => $count > 0 ? 'Operating' : 'No property',
                        'visible' => $isVisible,
                        'visibility' => $isVisible ? 'Visible' : 'Hidden',
                        'visitors' => match (true) {
                            ! $isVisible => 'Page not found',
                            $count > 0 => 'City page with its properties',
                            default => '"Coming soon: register your interest"',
                        },
                    ]];
                })
                ->all())
            ->columns([
                TextColumn::make('name')->label('City')->weight(FontWeight::SemiBold),
                TextColumn::make('aliases'),
                TextColumn::make('properties')->label('Properties')->numeric(),
                TextColumn::make('operating')->badge()->color(fn (string $state): string => $state === 'Operating' ? 'success' : 'gray'),
                TextColumn::make('visibility')->label('Public visibility')->badge()->color(fn (string $state): string => $state === 'Visible' ? 'primary' : 'gray'),
                TextColumn::make('visitors')->label('What visitors see'),
            ])
            ->recordActions([
                Action::make('toggleVisibility')
                    ->label(fn (array $record): string => $record['visible'] ? 'Hide' : 'Make visible')
                    ->color(fn (array $record): string => $record['visible'] ? 'gray' : 'primary')
                    ->requiresConfirmation()
                    ->modalHeading(fn (array $record): string => ($record['visible'] ? 'Hide ' : 'Make visible: ').$record['name'])
                    ->modalDescription(fn (array $record): string => match (true) {
                        $record['visible'] => 'Visitors will get "page not found" for this city, and it leaves the menus. The change is recorded in the audit log.',
                        $record['properties'] > 0 => 'The city page and its published properties appear on the website and in the menus.',
                        default => 'This city has no property, so visitors will see a "Coming soon: register your interest" page. It lists no properties, prices or inventory, and is hidden from search engines.',
                    })
                    ->action(fn () => $this->save()),
            ])
            ->paginated(false);
    }
}
