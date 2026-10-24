<?php

namespace App\Console\Commands\Brand;

use App\Actions\Brand\GenerateBrandRenditions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('brand:renditions {--source= : Logo file to read (defaults to resources/brand/source/smartgro-logo.webp)} {--output= : Directory to write to (defaults to public/)}')]
#[Description('Generate the web logo, reversed logo, browser-tab icon and app icon from the original logo file')]
class GenerateBrandRenditionsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(GenerateBrandRenditions $generateBrandRenditions): int
    {
        $source = $this->option('source') ?: resource_path('brand/source/smartgro-logo.webp');
        $output = $this->option('output') ?: public_path();

        foreach ($generateBrandRenditions->handle($source, $output) as $file) {
            $this->components->twoColumnDetail($file, 'written');
        }

        return self::SUCCESS;
    }
}
