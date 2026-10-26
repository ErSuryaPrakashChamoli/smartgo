<?php

namespace App\Filament\Clusters\Website\Resources\CaseStudies;

use App\Filament\Clusters\Website\Resources\CaseStudies\Pages\CreateCaseStudy;
use App\Filament\Clusters\Website\Resources\CaseStudies\Pages\EditCaseStudy;
use App\Filament\Clusters\Website\Resources\CaseStudies\Pages\ListCaseStudies;
use App\Filament\Clusters\Website\Resources\CaseStudies\Schemas\CaseStudyForm;
use App\Filament\Clusters\Website\Resources\CaseStudies\Tables\CaseStudiesTable;
use App\Filament\Clusters\Website\Resources\Pages\RelationManagers\RevisionsRelationManager;
use App\Filament\Clusters\Website\WebsiteCluster;
use App\Models\CaseStudy;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Customer stories (FR-CMS-7). Published only with the customer's consent on file (BR-CMS-7), by
 * someone with `testimonials.publish`.
 */
class CaseStudyResource extends Resource
{
    protected static ?string $model = CaseStudy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $cluster = WebsiteCluster::class;

    protected static ?string $navigationLabel = 'Customer stories';

    protected static ?string $modelLabel = 'customer story';

    protected static ?string $pluralModelLabel = 'customer stories';

    protected static ?string $slug = 'customer-stories';

    protected static ?int $navigationSort = 5;

    protected static bool $isGloballySearchable = false;

    public static function form(Schema $schema): Schema
    {
        return CaseStudyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CaseStudiesTable::configure($table);
    }

    /**
     * @param  CaseStudy  $record
     */
    public static function getRecordTitle(?Model $record): string
    {
        return $record?->title ?? $record?->openRevision()?->payload['title'] ?? 'Untitled customer story';
    }

    public static function getRelations(): array
    {
        return [
            RevisionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCaseStudies::route('/'),
            'create' => CreateCaseStudy::route('/create'),
            'edit' => EditCaseStudy::route('/{record}/edit'),
        ];
    }
}
