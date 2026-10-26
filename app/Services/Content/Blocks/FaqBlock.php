<?php

namespace App\Services\Content\Blocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * Questions and answers written on the page. Every question needs its answer before the version can
 * be submitted, so no placeholder answer is ever published.
 */
class FaqBlock implements ContentBlock
{
    public function type(): string
    {
        return 'faq';
    }

    public function label(): string
    {
        return 'FAQs';
    }

    public function editorBlock(): Block
    {
        return Block::make($this->type())
            ->label($this->label())
            ->icon(Heroicon::OutlinedQuestionMarkCircle)
            ->schema([
                TextInput::make('heading')->placeholder('Frequently asked questions')->maxLength(120),
                Repeater::make('items')
                    ->label('Questions')
                    ->schema([
                        TextInput::make('question')->markAsRequired()->maxLength(150),
                        Textarea::make('answer')->markAsRequired()->rows(3)->maxLength(1500),
                    ])
                    ->maxItems(20)
                    ->addActionLabel('Add question'),
            ]);
    }

    public function rules(string $prefix, bool $forSubmission): array
    {
        $required = $forSubmission ? 'required' : 'nullable';

        return [
            $prefix => ['array:heading,items'],
            "{$prefix}.heading" => ['nullable', 'string', 'max:120'],
            "{$prefix}.items" => [$required, 'array', 'max:20', ...($forSubmission ? ['min:1'] : [])],
            "{$prefix}.items.*" => ['array:question,answer'],
            "{$prefix}.items.*.question" => [$required, 'string', 'max:150'],
            "{$prefix}.items.*.answer" => [$required, 'string', 'max:1500'],
        ];
    }

    public function normalize(array $data): array
    {
        return [...$data, 'items' => array_values($data['items'] ?? [])];
    }

    public function present(array $data): array
    {
        return [
            'heading' => filled($data['heading'] ?? null) ? $data['heading'] : 'Frequently asked questions',
            'items' => array_values(array_map(
                fn (array $item): array => ['question' => (string) ($item['question'] ?? ''), 'answer' => (string) ($item['answer'] ?? '')],
                $data['items'] ?? [],
            )),
        ];
    }

    public function component(): string
    {
        return 'blocks.faq';
    }
}
