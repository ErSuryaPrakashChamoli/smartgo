<?php

namespace App\Filament\Clusters\DesignPreviews\Concerns;

use App\Enums\Access\StaffRole;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;

/**
 * Shared behaviour of the back-office design previews: they exist only outside production, only
 * the Super Admin can open them, and nothing on them is ever saved.
 */
trait IsDesignPreview
{
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return ! app()->isProduction() && $user instanceof User && $user->hasRole(StaffRole::SuperAdmin);
    }

    /**
     * Called by every preview form's submit button. The form is validated as it will be for real,
     * then nothing is stored.
     */
    public function save(): void
    {
        if (method_exists($this, 'form')) {
            $this->form->getState();
        }

        Notification::make()
            ->info()
            ->title('Design preview: nothing was saved')
            ->body('This screen shows the planned workflow. Saving arrives with the inventory module in Phase 5.')
            ->send();
    }

    protected function previewNotice(string $description, string $heading = 'Design preview'): Callout
    {
        return Callout::make($heading)
            ->description($description)
            ->info();
    }
}
