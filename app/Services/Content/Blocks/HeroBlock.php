<?php

namespace App\Services\Content\Blocks;

use App\Services\Content\ContentLink;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * The page introduction: the page's only level-1 heading, so it may be used once, as the first section.
 */
class HeroBlock implements ContentBlock
{
    public function __construct(private ContentLink $contentLink) {}

    public function type(): string
    {
        return 'hero';
    }

    public function label(): string
    {
        return 'Introduction';
    }

    public function editorBlock(): Block
    {
        return Block::make($this->type())
            ->label($this->label())
            ->icon(Heroicon::OutlinedSparkles)
            ->maxItems(1)
            ->schema([
                TextInput::make('eyebrow')->label('Small heading above the title')->maxLength(40),
                TextInput::make('heading')->label('Title')->markAsRequired()->maxLength(90),
                Textarea::make('intro')->label('Introduction')->rows(3)->maxLength(300),
                $this->contentLink->editor('link', 'Button (optional)', required: false),
            ]);
    }

    public function rules(string $prefix, bool $forSubmission): array
    {
        return [
            $prefix => ['array:eyebrow,heading,intro,link'],
            "{$prefix}.eyebrow" => ['nullable', 'string', 'max:40'],
            "{$prefix}.heading" => [$forSubmission ? 'required' : 'nullable', 'string', 'max:90'],
            "{$prefix}.intro" => ['nullable', 'string', 'max:300'],
            ...$this->contentLink->rules("{$prefix}.link", required: false, forSubmission: $forSubmission),
        ];
    }

    public function normalize(array $data): array
    {
        return $data;
    }

    public function present(array $data): array
    {
        return [
            'eyebrow' => $data['eyebrow'] ?? null,
            'heading' => (string) ($data['heading'] ?? ''),
            'intro' => $data['intro'] ?? null,
            'link' => $this->contentLink->present($data['link'] ?? null),
        ];
    }

    public function component(): string
    {
        return 'blocks.hero';
    }
}
