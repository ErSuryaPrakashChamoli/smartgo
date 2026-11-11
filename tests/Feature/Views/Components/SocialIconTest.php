<?php

use App\Services\Content\SiteSettingPayload;
use Illuminate\View\ViewException;

it('draws a decorative logo for every social network the site settings accept', function (string $network) {
    $this->blade('<x-site.social-icon :network="$network" class="size-5" />', ['network' => $network])
        ->assertSee('<svg', false)
        ->assertSee('aria-hidden="true"', false)
        ->assertSee('class="size-5"', false)
        ->assertSee('<path d="M', false);
})->with(array_keys(SiteSettingPayload::SOCIAL_HOSTS));

it('rejects a network it has no logo for instead of drawing an empty icon', function () {
    $this->blade('<x-site.social-icon network="myspace" />');
})->throws(ViewException::class, 'Unknown social network [myspace].');
