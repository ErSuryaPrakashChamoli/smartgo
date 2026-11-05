<?php

namespace App\Filament\Clusters\Sales\Resources\AssignmentRules;

use App\Enums\Access\Capability;
use App\Enums\Crm\AssignmentOutcome;
use App\Enums\Crm\AssignmentStrategy;
use App\Filament\Clusters\Sales\Resources\AssignmentRules\Pages\CreateAssignmentRule;
use App\Filament\Clusters\Sales\Resources\AssignmentRules\Pages\EditAssignmentRule;
use App\Filament\Clusters\Sales\Resources\AssignmentRules\Pages\ListAssignmentRules;
use App\Filament\Clusters\Sales\Resources\AssignmentRules\Pages\ViewAssignmentRule;
use App\Filament\Clusters\Sales\SalesCluster;
use App\Models\AssignmentRule;
use App\Models\City;
use App\Models\CustomerSegment;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Property;
use App\Models\User;
use App\Services\Website\EnquiryForm;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The rules that give new enquiries an owner (FR-CRM-2, BR-CRM-3), for the Super Admin only (OD-36). None
 * exists by default: until a rule is switched on, new enquiries stay in the unassigned queue. A rule is
 * changed only while it is off, and switched on or off explicitly (SetAssignmentRuleActive). Rules are
 * never deleted, so past decisions keep their rule. Hidden until the assignment tables exist.
 */
class AssignmentRuleResource extends Resource
{
    protected static ?string $model = AssignmentRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $cluster = SalesCluster::class;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Assignment rules';

    protected static ?string $modelLabel = 'assignment rule';

    protected static ?string $slug = 'assignment-rules';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isGloballySearchable = false;

    public static function canAccess(): bool
    {
        return AssignmentRule::isAvailable() && parent::canAccess();
    }

    /**
     * Filament loads a rule's record before it checks access, so a rule's own address is refused here first
     * while the assignment tables are missing, rather than ending in a query error (D1, OD-163).
     */
    public static function resolveRecordRouteBinding(int|string $key, ?Closure $modifyQuery = null): ?Model
    {
        abort_unless(AssignmentRule::isAvailable(), 403);

        return parent::resolveRecordRouteBinding($key, $modifyQuery);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Rule')
                ->description('Rules are tried from the lowest priority number. The first active rule an enquiry matches decides who gets it; with none, it stays in the unassigned queue.')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->required()->maxLength(120),
                    TextInput::make('priority')->required()->integer()->minValue(1)->maxValue(9999)
                        ->helperText('Each number is used once.'),
                    Radio::make('strategy')
                        ->label('How it picks the owner')
                        ->options(AssignmentStrategy::options())
                        ->descriptions(collect(AssignmentStrategy::cases())->mapWithKeys(fn (AssignmentStrategy $strategy): array => [$strategy->value => $strategy->description()])->all())
                        ->required()
                        ->live()
                        ->columnSpanFull(),
                ]),
            Section::make('Which enquiries')
                ->description('Leave a list empty for any. An enquiry must meet every list that is filled in; one without the detail (no city, say) does not meet that list.')
                ->columns(2)
                ->schema([
                    Select::make('criteria.city_ids')->label('Cities')->multiple()->options(fn (): array => City::query()->orderBy('name')->pluck('name', 'id')->all()),
                    Select::make('criteria.property_ids')->label('Locations')->multiple()->options(fn (): array => Property::query()->orderBy('code')->pluck('name', 'id')->map(fn (string $name): string => 'SmartGro '.$name)->all()),
                    Select::make('criteria.product_ids')->label('Workspace types')->multiple()->options(fn (): array => Product::query()->orderBy('sort_order')->pluck('name', 'id')->all()),
                    Select::make('criteria.segment_ids')->label('Describes themselves as')->multiple()->options(fn (): array => CustomerSegment::query()->orderBy('sort_order')->pluck('name', 'id')->all()),
                    Select::make('criteria.team_sizes')->label('Team sizes')->multiple()->options(EnquiryForm::TEAM_SIZES),
                    Select::make('criteria.sources')->label('Sources')->multiple()->options(Lead::SOURCES),
                ]),
            Section::make('People')
                ->description(fn (Get $get): string => match (AssignmentStrategy::tryFrom((string) $get('strategy'))) {
                    AssignmentStrategy::FixedUser => 'The one person who gets every matching enquiry.',
                    AssignmentStrategy::LeastLoaded => 'A tie goes to whoever is higher in this list.',
                    default => 'Enquiries go to these people in turn, in this order.',
                }.' Only active staff who may work on enquiries are listed; someone who cannot work on an enquiry\'s location is passed over for it.')
                ->schema([
                    Repeater::make('assignees')
                        ->hiddenLabel()
                        ->schema([
                            Select::make('user_id')
                                ->label('Person')
                                ->options(fn (): array => self::assigneeOptions())
                                ->required()
                                ->distinct()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        ])
                        ->reorderable()
                        ->addActionLabel('Add a person')
                        ->maxItems(fn (Get $get): ?int => $get('strategy') === AssignmentStrategy::FixedUser->value ? 1 : null)
                        ->defaultItems(0),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('assignees')->withCount(['decisions as assigned_count' => fn (Builder $decisions): Builder => $decisions->where('outcome', AssignmentOutcome::Assigned)]))
            ->defaultSort('priority')
            ->columns([
                TextColumn::make('priority')->numeric()->sortable(),
                TextColumn::make('name')->weight('semibold')->description(fn (AssignmentRule $record): string => $record->strategy->label()),
                TextColumn::make('criteria')->label('Which enquiries')->state(fn (AssignmentRule $record): array => self::criteriaLines($record))->listWithLineBreaks(),
                TextColumn::make('people')->label('People')->state(fn (AssignmentRule $record): string => $record->assignees->pluck('name')->join(', ') ?: 'Nobody yet'),
                TextColumn::make('is_active')->label('Status')->badge()->formatStateUsing(fn (bool $state): string => $state ? 'On' : 'Off')->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextColumn::make('assigned_count')->label('Enquiries assigned')->numeric(),
            ])
            ->recordActions([
                ViewAction::make()->label('Open'),
                EditAction::make()->visible(fn (AssignmentRule $record): bool => ! $record->is_active),
            ])
            ->emptyStateHeading('No assignment rules')
            ->emptyStateDescription('Until a rule is switched on, every new enquiry stays in the unassigned queue for staff to assign by hand.')
            ->emptyStateIcon('heroicon-o-arrows-right-left');
    }

    /**
     * Active staff who may work on enquiries, by name.
     *
     * @return array<int, string>
     */
    public static function assigneeOptions(): array
    {
        return User::query()->where('is_active', true)->orderBy('name')->get()
            ->filter(fn (User $user): bool => $user->can(Capability::ManageLeads))
            ->mapWithKeys(fn (User $user): array => [$user->getKey() => $user->name])
            ->all();
    }

    /**
     * The rule's criteria as readable lines, e.g. "Cities: Noida"; "Any enquiry" when none is set.
     *
     * @return list<string>
     */
    public static function criteriaLines(AssignmentRule $rule): array
    {
        $names = [
            'city_ids' => ['Cities', fn (array $ids): array => City::query()->whereKey($ids)->orderBy('name')->pluck('name')->all()],
            'property_ids' => ['Locations', fn (array $ids): array => Property::query()->whereKey($ids)->orderBy('code')->pluck('name')->map(fn (string $name): string => 'SmartGro '.$name)->all()],
            'product_ids' => ['Workspace types', fn (array $ids): array => Product::query()->whereKey($ids)->orderBy('sort_order')->pluck('name')->all()],
            'segment_ids' => ['Describes themselves as', fn (array $ids): array => CustomerSegment::query()->whereKey($ids)->orderBy('sort_order')->pluck('name')->all()],
            'team_sizes' => ['Team sizes', fn (array $values): array => array_values(array_intersect_key(EnquiryForm::TEAM_SIZES, array_flip($values)))],
            'sources' => ['Sources', fn (array $values): array => array_values(array_intersect_key(Lead::SOURCES, array_flip($values)))],
        ];

        $lines = collect($names)
            ->filter(fn (array $name, string $criterion): bool => ($rule->criteria[$criterion] ?? []) !== [])
            ->map(fn (array $name, string $criterion): string => $name[0].': '.implode(', ', $name[1]($rule->criteria[$criterion])))
            ->values()->all();

        return $lines === [] ? ['Any enquiry'] : $lines;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssignmentRules::route('/'),
            'create' => CreateAssignmentRule::route('/create'),
            'view' => ViewAssignmentRule::route('/{record}'),
            'edit' => EditAssignmentRule::route('/{record}/edit'),
        ];
    }
}
