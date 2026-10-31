<?php

namespace App\Filament\Clusters\Properties\Resources\InventoryImports;

use App\Enums\Access\Capability;
use App\Enums\Inventory\ImportStatus;
use App\Filament\Clusters\Properties\PropertiesCluster;
use App\Filament\Clusters\Properties\Resources\InventoryImports\Pages\CreateInventoryImport;
use App\Filament\Clusters\Properties\Resources\InventoryImports\Pages\ListInventoryImports;
use App\Filament\Clusters\Properties\Resources\InventoryImports\Pages\ViewInventoryImport;
use App\Models\InventoryImport;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Inventory imports with the SG-INV template (INVENTORY_IMPORT_SPEC, ADR-0012): upload, the findings row
 * by row and a preview of the changes (a dry run), submission by the uploader, then approval and an
 * all-or-nothing commit by someone else. Uploaders see their own imports; approvers see all. Hidden on a
 * database without the inventory tables.
 */
class InventoryImportResource extends Resource
{
    protected static ?string $model = InventoryImport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $cluster = PropertiesCluster::class;

    protected static ?string $navigationLabel = 'Inventory imports';

    protected static ?string $modelLabel = 'inventory import';

    protected static ?int $navigationSort = 9;

    protected static bool $isGloballySearchable = false;

    public static function canAccess(): bool
    {
        return InventoryImport::isAvailable() && parent::canAccess();
    }

    /**
     * @return Builder<InventoryImport>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var User $user */
        $user = auth()->user();

        return parent::getEloquentQuery()->when(! $user->can(Capability::ApproveInventoryImport), fn (Builder $query) => $query->where('uploaded_by', $user->getKey()));
    }

    public static function infolist(Schema $schema): Schema
    {
        $timezone = config('backoffice.timezone');

        return $schema->columns(1)->components([
            Section::make('Workbook')->columns(2)->schema([
                TextEntry::make('original_name')->label('File'),
                TextEntry::make('status')->badge()->formatStateUsing(fn (ImportStatus $state): string => $state->label())->color(fn (ImportStatus $state): string => $state->color()),
                TextEntry::make('uploader.name')->label('Uploaded by'),
                TextEntry::make('created_at')->label('Uploaded')->dateTime('j M Y, H:i T', $timezone),
                TextEntry::make('template_id')->label('Template')->state(fn (InventoryImport $record): string => $record->template_id ?? 'Not recognised'),
                TextEntry::make('file_sha256')->label('Checksum (SHA-256)')->fontFamily('mono')->limit(16),
                TextEntry::make('approver')->label('Approved by')->state(fn (InventoryImport $record): string => $record->approver?->name ?? 'Not approved'),
                TextEntry::make('committed_at')->label('Imported')->state(fn (InventoryImport $record): string => $record->committed_at?->timezone($timezone)->format('j M Y, H:i T') ?? 'Not imported'),
            ]),
            Section::make('What the import would change')->description('A dry run: nothing is written until someone other than the uploader approves it.')
                ->visible(fn (InventoryImport $record): bool => $record->summary !== null)
                ->schema([View::make('filament.inventory-imports.summary')->viewData(fn (InventoryImport $record): array => ['summary' => $record->summary])]),
            Section::make('Findings')->description('Errors stop the import; each warning must be acknowledged when submitting.')
                ->schema([View::make('filament.inventory-imports.issues')->viewData(fn (InventoryImport $record): array => ['issues' => $record->issues()->orderByRaw("field(severity, 'error', 'warning', 'note')")->orderBy('id')->get()])]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('uploader'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('original_name')->label('File')->weight('semibold')->description(fn (InventoryImport $record): string => 'Import '.$record->getKey()),
                TextColumn::make('uploader.name')->label('Uploaded by'),
                TextColumn::make('created_at')->label('Uploaded')->dateTime('j M Y, H:i', config('backoffice.timezone')),
                TextColumn::make('status')->badge()->formatStateUsing(fn (ImportStatus $state): string => $state->label())->color(fn (ImportStatus $state): string => $state->color()),
            ])
            ->emptyStateHeading('No inventory imports')
            ->emptyStateDescription('Download the SG-INV-1.3 template, fill in real inventory supplied by SmartGro, and upload it here.')
            ->recordActions([Action::make('open')->label('Open')->icon(Heroicon::OutlinedArrowRight)->url(fn (InventoryImport $record): string => self::getUrl('view', ['record' => $record]))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryImports::route('/'),
            'create' => CreateInventoryImport::route('/upload'),
            'view' => ViewInventoryImport::route('/{record}'),
        ];
    }
}
