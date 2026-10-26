<?php

namespace App\Services\Content\Blocks;

use App\Services\Content\ContentLink;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * The dark call-to-action band from the design system (`x-site.cta-band`), with one or two buttons.
 */
class CallToActionBlock implements ContentBlock
{
    public function __construct(private ContentLink $contentLink) {}

    public function type(): string
    {
        return 'cta';
    }

    public function label(): string
    {
        return 'Call to action';
    }

    public function editorBlock(): Block
    {
        return Block::make($this->type())
            ->label($this->label())
            ->icon(Heroicon::OutlinedMegaphone)
            ->schema([
                TextInput::make('eyebrow')->label('Small heading above the title')->maxLength(40),
                TextInput::make('heading')->label('Title')->markAsRequired()->maxLength(90),
                Textarea::make('text')->rows(2)->maxLength(300),
                $this->contentLink->editor('primary_link', 'Main button', required: true),
                $this->contentLink->editor('secondary_link', 'Second button (optional)', required: false),
            ]);
    }

    public function rules(string $prefix, bool $forSubmission): array
    {
        return [
            $prefix => ['array:eyebrow,heading,text,primary_link,secondary_link'],
            "{$prefix}.eyebrow" => ['nullable', 'string', 'max:40'],
            "{$prefix}.heading" => [$forSubmission ? 'required' : 'nullable', 'string', 'max:90'],
            "{$prefix}.text" => ['nullable', 'string', 'max:300'],
            ...$this->contentLink->rules("{$prefix}.primary_link", required: true, forSubmission: $forSubmission),
            ...$this->contentLink->rules("{$prefix}.secondary_link", required: false, forSubmission: $forSubmission),
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
            'text' => $data['text'] ?? null,
            'links' => array_values(array_filter([
                $this->contentLink->present($data['primary_link'] ?? null),
                $this->contentLink->present($data['secondary_link'] ?? null),
            ])),
        ];
    }

    public function component(): string
    {
        return 'blocks.cta';
    }
}
