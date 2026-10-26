<?php

namespace App\Filament\Clusters\Website\Resources\Faqs;

use App\Filament\Clusters\Website\Resources\Faqs\Pages\CreateFaq;
use App\Filament\Clusters\Website\Resources\Faqs\Pages\EditFaq;
use App\Filament\Clusters\Website\Resources\Faqs\Pages\ListFaqs;
use App\Filament\Clusters\Website\Resources\Faqs\Schemas\FaqForm;
use App\Filament\Clusters\Website\Resources\Faqs\Tables\FaqsTable;
use App\Filament\Clusters\Website\Resources\Pages\RelationManagers\RevisionsRelationManager;
use App\Filament\Clusters\Website\WebsiteCluster;
use App\Models\Faq;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Frequently asked questions (FR-CMS-7), listed at /faqs once published.
 */
class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static ?string $cluster = WebsiteCluster::class;

    protected static ?string $navigationLabel = 'FAQs';

    protected static ?string $modelLabel = 'FAQ';

    protected static ?string $pluralModelLabel = 'FAQs';

    protected static ?int $navigationSort = 7;

    protected static bool $isGloballySearchable = false;

    public static function form(Schema $schema): Schema
    {
        return FaqForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FaqsTable::configure($table);
    }

    /**
     * @param  Faq  $record
     */
    public static function getRecordTitle(?Model $record): string
    {
        return $record?->question ?? $record?->openRevision()?->payload['question'] ?? 'New FAQ';
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
            'index' => ListFaqs::route('/'),
            'create' => CreateFaq::route('/create'),
            'edit' => EditFaq::route('/{record}/edit'),
        ];
    }
}
