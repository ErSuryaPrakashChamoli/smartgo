<?php

namespace App\Filament\Clusters\Sales\Resources\LeadCloseReasons;

use App\Enums\Crm\LeadStatus;
use App\Filament\Clusters\Sales\Resources\LeadCloseReasons\Pages\CreateLeadCloseReason;
use App\Filament\Clusters\Sales\Resources\LeadCloseReasons\Pages\EditLeadCloseReason;
use App\Filament\Clusters\Sales\Resources\LeadCloseReasons\Pages\ListLeadCloseReasons;
use App\Filament\Clusters\Sales\SalesCluster;
use App\Models\LeadCloseReason;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

/**
 * The managed lists of reasons for marking an enquiry lost or disqualified (BR-CRM-5, FR-CRM-5). Nothing
 * is preset: until a reason exists for a status, no enquiry can be given that status. A reason that has
 * been used keeps its status and is switched off rather than deleted, so closed enquiries keep it.
 */
class LeadCloseReasonResource extends Resource
{
    protected static ?string $model = LeadCloseReason::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?string $cluster = SalesCluster::class;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Closing reasons';

    protected static ?string $modelLabel = 'closing reason';

    protected static ?string $slug = 'closing-reasons';

    protected static ?string $recordTitleAttribute = 'label';

    protected static bool $isGloballySearchable = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Closing reason')
                ->description('Staff choose one of these when they mark an enquiry lost or disqualified.')
                ->columns(2)
                ->schema([
                    Select::make('status')
                        ->label('Used when marking an enquiry')
                        ->options([LeadStatus::Lost->value => LeadStatus::Lost->label(), LeadStatus::Disqualified->value => LeadStatus::Disqualified->label()])
                        ->required()
                        ->live()
                        ->disabled(fn (?LeadCloseReason $record): bool => $record?->leads()->exists() ?? false)
                        ->helperText(fn (?LeadCloseReason $record): ?string => $record?->leads()->exists() ? 'Already used on an enquiry, so it stays with this status.' : null),
                    TextInput::make('label')
                        ->label('Reason')
                        ->required()
                        ->maxLength(80)
                        ->notRegex('/[<>]/')
                        ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('status', $get('status'))),
                    TextInput::make('sort_order')->label('Position in the list')->integer()->minValue(0)->maxValue(65535)->default(0),
                    Toggle::make('is_active')->label('Offered to staff')->default(true)->inline(false)
                        ->helperText('Switch off a reason you no longer want offered. Enquiries already closed with it keep it.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('leads'))
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('status')->orderBy('sort_order')->orderBy('label'))
            ->columns([
                TextColumn::make('label')->label('Reason')->weight('semibold')->searchable(),
                TextColumn::make('status')->label('Used when marking')->badge()->formatStateUsing(fn (LeadStatus $state): string => $state->label())->color('gray'),
                TextColumn::make('sort_order')->label('Position')->numeric(),
                IconColumn::make('is_active')->label('Offered')->boolean(),
                TextColumn::make('leads_count')->label('Enquiries closed with it')->numeric(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Used when marking')->options([LeadStatus::Lost->value => LeadStatus::Lost->label(), LeadStatus::Disqualified->value => LeadStatus::Disqualified->label()]),
            ])
            ->emptyStateHeading('No closing reasons yet')
            ->emptyStateDescription('Add the reasons staff may choose when they mark an enquiry lost or disqualified. Until one exists for a status, no enquiry can be given that status.')
            ->recordActions([EditAction::make()->label('Open')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeadCloseReasons::route('/'),
            'create' => CreateLeadCloseReason::route('/create'),
            'edit' => EditLeadCloseReason::route('/{record}/edit'),
        ];
    }
}
