<?php

namespace App\Actions\Properties;

use App\Models\Product;
use App\Models\Space;
use App\Models\SpaceType;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Sets which space types a workspace category accepts (BR-PRODCFG-2, DATABASE_ERD §4.1), audited like
 * the category's other rules (BR-PRODCFG-1). A type cannot be removed while a space of that type is
 * sold as the category: those spaces are changed first.
 */
class SetCategorySpaceTypes
{
    /**
     * @param  array<int, int|string>  $spaceTypeIds
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, Product $product, array $spaceTypeIds): void
    {
        Gate::forUser($user)->authorize('update', $product);

        $ids = Validator::make(['space_type_ids' => array_values($spaceTypeIds)], [
            'space_type_ids' => ['array'],
            'space_type_ids.*' => ['integer', 'distinct', Rule::exists('space_types', 'id')],
        ], ['space_type_ids.*.exists' => 'A chosen space type no longer exists.'])->validate()['space_type_ids'];
        $ids = collect($ids)->map(fn (int|string $id): int => (int) $id)->sort()->values();

        DB::transaction(function () use ($user, $product, $ids): void {
            $before = $product->spaceTypes()->orderBy('key')->pluck('key', 'space_types.id');
            $inUse = Space::query()
                ->whereIn('space_type_id', $before->keys()->diff($ids)->all())
                ->whereHas('offerings', fn ($offerings) => $offerings->where('product_id', $product->getKey()))
                ->orderBy('code')
                ->pluck('code');

            if ($inUse->isNotEmpty()) {
                throw ValidationException::withMessages(['space_type_ids' => 'These spaces are sold as this category and are of a type no longer ticked; change them first: '.$inUse->implode(', ').'.']);
            }

            $product->spaceTypes()->sync($ids->all());
            $after = SpaceType::query()->whereKey($ids->all())->orderBy('key')->pluck('key');

            if ($before->values()->all() !== $after->all()) {
                activity('properties')
                    ->performedOn($product)
                    ->causedBy($user)
                    ->event('space_types_changed')
                    ->withProperties(['old' => $before->values()->all(), 'attributes' => $after->all()])
                    ->log('Space types the workspace category accepts changed');
            }
        });
    }
}
