<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Actions\Access\ChangeTwoStepLoginRequirement;
use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\SecuritySetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Whether two-step login is compulsory for staff (OD-124). Off by default in local development; always
 * on elsewhere. Staff can always set it up for themselves on their profile page.
 */
class Security extends Page
{
    protected static ?string $cluster = SettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Security';

    protected static ?string $title = 'Security';

    public static function canAccess(): bool
    {
        return (auth()->user()?->can('update', SecuritySetting::class) ?? false) && SecuritySetting::isAvailable();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Callout::make(fn (): string => SecuritySetting::requiresTwoStepLogin() ? 'Two-step login is required' : 'Two-step login is optional')
                ->description(fn (): string => match (true) {
                    SecuritySetting::isTwoStepLoginAlwaysRequired() => 'Every staff member must set up two-step login before using the back office. It is always required on this site and cannot be switched off here.',
                    SecuritySetting::requiresTwoStepLogin() => 'Every staff member must set up two-step login before using the back office.',
                    default => 'Staff can use the back office with their password alone. Anyone can still set up two-step login for themselves on their profile page, and is then asked for a code every time they sign in.',
                })
                ->icon(fn (): Heroicon => SecuritySetting::requiresTwoStepLogin() ? Heroicon::OutlinedShieldCheck : Heroicon::OutlinedShieldExclamation)
                ->color(fn (): string => SecuritySetting::requiresTwoStepLogin() ? 'success' : 'warning'),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('requireTwoStepLogin')
                ->label('Require two-step login')
                ->requiresConfirmation()
                ->modalDescription('Everyone without two-step login, you included, will have to set it up before they can continue in the back office.')
                ->visible(fn (): bool => ! SecuritySetting::requiresTwoStepLogin())
                ->action(function (ChangeTwoStepLoginRequirement $changeTwoStepLoginRequirement): void {
                    $changeTwoStepLoginRequirement->handle(auth()->user(), true);

                    Notification::make()->success()->title('Two-step login is now required')->send();
                }),
            Action::make('makeTwoStepLoginOptional')
                ->label('Make two-step login optional')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('Staff will be able to use the back office with their password alone. Anyone who has set up two-step login keeps it.')
                ->visible(fn (): bool => SecuritySetting::requiresTwoStepLogin() && ! SecuritySetting::isTwoStepLoginAlwaysRequired())
                ->action(function (ChangeTwoStepLoginRequirement $changeTwoStepLoginRequirement): void {
                    $changeTwoStepLoginRequirement->handle(auth()->user(), false);

                    Notification::make()->success()->title('Two-step login is now optional')->send();
                }),
        ];
    }
}
