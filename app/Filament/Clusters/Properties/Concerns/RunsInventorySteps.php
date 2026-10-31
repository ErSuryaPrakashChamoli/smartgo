<?php

namespace App\Filament\Clusters\Properties\Concerns;

use App\Models\User;
use Closure;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Runs an inventory action from a screen: the action checks permission and the rules again under the
 * space's lock, so hiding a button is only a convenience. A refusal is shown with its reason and keeps
 * the form open.
 */
trait RunsInventorySteps
{
    protected function runInventoryStep(Closure $step, string $success): mixed
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $result = $step($user);
        } catch (AuthorizationException $exception) {
            Notification::make()->danger()->title('Not allowed')->body($exception->getMessage())->send();

            throw new Halt;
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title('Not saved')->body(collect($exception->errors())->flatten()->first())->persistent()->send();

            throw new Halt;
        }

        Notification::make()->success()->title($success)->send();

        return $result;
    }
}
