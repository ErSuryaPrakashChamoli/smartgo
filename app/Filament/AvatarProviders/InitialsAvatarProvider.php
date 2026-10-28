<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Staff avatars drawn in the page as an inline SVG of the person's initials. Filament's default sends
 * each name to ui-avatars.com, which would share staff names with a third party on every page.
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $initials = str(Filament::getNameForDefaultAvatar($record))
            ->trim()
            ->explode(' ')
            ->map(fn (string $word): string => mb_substr((string) preg_replace('/^[^\p{L}\p{N}]+/u', '', $word), 0, 1))
            ->filter()
            ->take(2)
            ->map(fn (string $letter): string => mb_strtoupper($letter))
            ->join('');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" fill="#0B2C1B"/>'
            .'<text x="32" y="33" fill="#FFFFFF" font-family="Inter, Arial, sans-serif" font-size="26" font-weight="600" text-anchor="middle" dominant-baseline="central">'
            .htmlspecialchars($initials, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
