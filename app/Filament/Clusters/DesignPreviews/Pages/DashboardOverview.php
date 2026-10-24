<?php

namespace App\Filament\Clusters\DesignPreviews\Pages;

use App\Filament\Clusters\DesignPreviews\Concerns\IsDesignPreview;
use App\Filament\Clusters\DesignPreviews\DesignPreviewsCluster;
use App\Services\Design\DesignPreviewContent;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Dashboard organised by job (DESIGN_SYSTEM §4): what needs attention today, then the launch
 * checklist and the properties. Shows the real current state, so most cards are empty.
 */
class DashboardOverview extends Page implements HasTable
{
    use InteractsWithTable;
    use IsDesignPreview;

    protected static ?string $cluster = DesignPreviewsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $navigationLabel = 'Dashboard overview';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Dashboard overview';

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->previewNotice('Cards show today\'s real state: there are no tours, leads or inventory records yet, so each card shows its empty state.'),
            Grid::make(['default' => 1, 'md' => 2, 'xl' => 4])->schema([
                $this->jobCard("Today's tours", Heroicon::OutlinedCalendarDays, '0', 'No tour requests yet. Requests appear here with their host and time.'),
                $this->jobCard('New leads', Heroicon::OutlinedInboxArrowDown, '0', 'No enquiries yet. New leads show their source and how long they have waited.'),
                $this->jobCard('Spaces needing attention', Heroicon::OutlinedWrenchScrewdriver, '0', 'No inventory entered yet. Spaces in maintenance or blocked appear here.'),
                $this->jobCard('Awaiting your approval', Heroicon::OutlinedCheckBadge, '0', 'Imports, prices and content waiting for a second approver appear here.'),
            ]),
            Grid::make(1)->schema([
                Section::make('Properties')
                    ->description('Confirmed facts only; everything else is pending.')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->schema([EmbeddedTable::make()]),
                Section::make('Launch checklist')
                    ->description('Inputs the platform needs before inventory can go live. "Pending" items come from SmartGro.')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->schema(collect([
                        ['Logo supplied', 'Done', 'success'],
                        ['Staff roles and two-step login', 'Done', 'success'],
                        ['Floor allocation for each property', 'Pending', 'warning'],
                        ['First property inventory', 'Pending', 'warning'],
                        ['Prices and payment rules', 'Pending', 'warning'],
                        ['Property photography', 'Pending', 'warning'],
                        ['Hosting for staging', 'Decision', 'gray'],
                    ])->map(fn (array $item): Flex => Flex::make([
                        Text::make($item[0]),
                        Text::make($item[1])->badge()->color($item[2])->grow(false),
                    ]))->all())
                    ->columns(['default' => 1, 'lg' => 2]),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => collect(app(DesignPreviewContent::class)->properties())
                ->mapWithKeys(fn (array $property): array => [$property['slug'] => [
                    ...$property,
                    'model' => 'Owned and operated',
                    'status' => 'To be confirmed',
                    'floors' => 'Allocation pending',
                    'inventory' => 'Not entered',
                ]])
                ->all())
            ->columns([
                TextColumn::make('name')->label('Property')->weight(FontWeight::SemiBold),
                TextColumn::make('area')->label('Area'),
                TextColumn::make('model')->label('Operating model'),
                TextColumn::make('status')->badge()->color('gray'),
                TextColumn::make('floors')->badge()->color('warning'),
                TextColumn::make('inventory')->badge()->color('warning'),
            ])
            ->paginated(false);
    }

    private function jobCard(string $heading, Heroicon $icon, string $count, string $emptyText): Section
    {
        return Section::make($heading)
            ->icon($icon)
            ->compact()
            ->schema([
                Text::make($count)->size(TextSize::Large)->weight(FontWeight::Bold),
                Text::make($emptyText)->color('gray'),
            ]);
    }
}
