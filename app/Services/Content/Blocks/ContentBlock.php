<?php

namespace App\Services\Content\Blocks;

use Filament\Forms\Components\Builder\Block;

/**
 * One kind of section in the page builder (FR-CMS-1). A block declares its editor fields, the
 * server-side rules its data must pass, and how its data is made safe for its view. The rules are
 * the authority: the editor's own checks only help people fill the form.
 */
interface ContentBlock
{
    /**
     * The type stored in a revision payload.
     */
    public function type(): string;

    /**
     * The name editors see.
     */
    public function label(): string;

    public function editorBlock(): Block;

    /**
     * Rules for the block's data, keyed from `$prefix` (the data's path in the payload). A draft may
     * be incomplete but never unsafe; a version submitted for approval must be complete.
     *
     * @return array<string, mixed>
     */
    public function rules(string $prefix, bool $forSubmission): array;

    /**
     * The validated data as it is stored: rich text is sanitised before it reaches the database.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalize(array $data): array;

    /**
     * The view data, with links resolved and rich text sanitised.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function present(array $data): array;

    /**
     * The anonymous Blade component that renders the block.
     */
    public function component(): string;
}
