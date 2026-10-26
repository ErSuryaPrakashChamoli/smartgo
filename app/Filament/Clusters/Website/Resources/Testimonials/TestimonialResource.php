<?php

namespace App\Filament\Clusters\Website\Resources\Testimonials;

use App\Filament\Clusters\Website\Resources\Pages\RelationManagers\RevisionsRelationManager;
use App\Filament\Clusters\Website\Resources\Testimonials\Pages\CreateTestimonial;
use App\Filament\Clusters\Website\Resources\Testimonials\Pages\EditTestimonial;
use App\Filament\Clusters\Website\Resources\Testimonials\Pages\ListTestimonials;
use App\Filament\Clusters\Website\Resources\Testimonials\Schemas\TestimonialForm;
use App\Filament\Clusters\Website\Resources\Testimonials\Tables\TestimonialsTable;
use App\Filament\Clusters\Website\WebsiteCluster;
use App\Models\Testimonial;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Customer testimonials (FR-CMS-7), shown on the website through the testimonial section. Published
 * only with consent on file and a verified statement (BR-CMS-7), by someone with `testimonials.publish`.
 */
class TestimonialResource extends Resource
{
    protected static ?string $model = Testimonial::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static ?string $cluster = WebsiteCluster::class;

    protected static ?string $navigationLabel = 'Testimonials';

    protected static ?string $modelLabel = 'testimonial';

    protected static ?int $navigationSort = 6;

    protected static bool $isGloballySearchable = false;

    public static function form(Schema $schema): Schema
    {
        return TestimonialForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TestimonialsTable::configure($table);
    }

    /**
     * @param  Testimonial  $record
     */
    public static function getRecordTitle(?Model $record): string
    {
        $name = $record?->person_name ?? $record?->openRevision()?->payload['person_name'] ?? null;

        return $name === null ? 'New testimonial' : "Testimonial from {$name}";
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
            'index' => ListTestimonials::route('/'),
            'create' => CreateTestimonial::route('/create'),
            'edit' => EditTestimonial::route('/{record}/edit'),
        ];
    }
}
