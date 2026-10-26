<?php

namespace App\Services\Content\Blocks;

use App\Services\Content\RichText;
use Closure;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * A text section: an optional heading and formatted text. The editor offers only emphasis, links,
 * subheadings, lists and quotes; anything else is refused or stripped (RichText).
 */
class TextBlock implements ContentBlock
{
    public function __construct(private RichText $richText) {}

    public function type(): string
    {
        return 'text';
    }

    public function label(): string
    {
        return 'Text section';
    }

    public function editorBlock(): Block
    {
        return Block::make($this->type())
            ->label($this->label())
            ->icon(Heroicon::OutlinedBars3BottomLeft)
            ->schema([
                TextInput::make('heading')->maxLength(120),
                RichEditor::make('body')
                    ->label('Text')
                    ->markAsRequired()
                    ->toolbarButtons([
                        ['bold', 'italic', 'link'],
                        ['h3', 'blockquote'],
                        ['bulletList', 'orderedList'],
                        ['undo', 'redo'],
                    ]),
            ]);
    }

    public function rules(string $prefix, bool $forSubmission): array
    {
        return [
            $prefix => ['array:heading,body'],
            "{$prefix}.heading" => ['nullable', 'string', 'max:120'],
            "{$prefix}.body" => [$forSubmission ? 'required' : 'nullable', 'string', 'max:'.RichText::MAX_LENGTH, $this->safeTextRule($forSubmission)],
        ];
    }

    public function normalize(array $data): array
    {
        return [...$data, 'body' => $this->richText->sanitize($data['body'] ?? null)];
    }

    public function present(array $data): array
    {
        return [
            'heading' => $data['heading'] ?? null,
            'body' => $this->richText->sanitize($data['body'] ?? null),
        ];
    }

    public function component(): string
    {
        return 'blocks.text';
    }

    private function safeTextRule(bool $forSubmission): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($forSubmission): void {
            if (! is_string($value)) {
                return;
            }

            if (($reason = $this->richText->unsafeContentReason($value)) !== null) {
                $fail($reason);
            } elseif ($forSubmission && ! $this->richText->hasText($this->richText->sanitize($value))) {
                $fail('Write some text, or remove this section.');
            }
        };
    }
}
