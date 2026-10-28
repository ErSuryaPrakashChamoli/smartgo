<?php

use App\Services\Twin\TwinModelLimits;

it('states every configured limit in the supplier specification C-6.5, which the owner made authoritative (OD-119 b)', function () {
    $root = dirname(__DIR__, 4);
    $limits = TwinModelLimits::fromConfig((require $root.'/config/twin.php')['limits']);
    $specification = str(file_get_contents($root.'/docs/requirements/CLIENT_INPUTS_REQUIRED.md'))->after('| C-6.5 |')->before("\n")->toString();
    $megabytes = fn (int $bytes): string => rtrim(rtrim(number_format($bytes / 1048576, 1), '0'), '.').' MB';
    $number = fn (int|float $value): string => rtrim(rtrim(number_format($value, 2), '0'), '.');

    expect($specification)
        ->toContain('at most **'.$megabytes($limits->bytes).'** per file')
        ->toContain('**'.number_format($limits->triangles).' triangles** per model')
        ->toContain('at most **'.number_format($limits->drawCalls).' pieces drawn separately**')
        ->toContain('at most **'.number_format($limits->materials).' materials**')
        ->toContain('at most **'.number_format($limits->textures).' textures**')
        ->toContain('at most **'.number_format($limits->textureSide).' pixels** on its longest side')
        ->toContain('**'.number_format($limits->texturePixels).' pixels** in all')
        ->toContain('at most **'.$megabytes($limits->geometryBytes).'** of geometry once decompressed')
        ->toContain('at most **'.number_format($limits->nodes).' nodes**')
        ->toContain('a **'.$megabytes($limits->descriptionBytes).'** description')
        ->toContain('**'.$number($limits->smallestMetres).' m to '.$number($limits->largestMetres).' m**')
        ->toContain('more than **'.$number($limits->checkSeconds).' seconds**');
});
