<?php

namespace App\Services\Content;

use App\Models\Page;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\Rule;

/**
 * A button or link in a block. It points either to a page on this website, chosen from a list so a
 * later change of address never breaks it (FR-CMS-4), or to another website over https. Typed paths,
 * scripts and other schemes are refused.
 */
class ContentLink
{
    public const string TO_PAGE = 'page';

    public const string TO_URL = 'url';

    public function __construct(private RichText $richText) {}

    public function editor(string $name, string $label, bool $required): Fieldset
    {
        return Fieldset::make($label)
            ->statePath($name)
            ->columns(2)
            ->schema([
                TextInput::make('label')
                    ->label('Button text')
                    ->maxLength(40)
                    ->markAsRequired($required),
                Select::make('type')
                    ->label('Goes to')
                    ->options([self::TO_PAGE => 'A page on this website', self::TO_URL => 'Another website'])
                    ->live()
                    ->markAsRequired($required),
                Select::make('page_id')
                    ->label('Page')
                    ->options(fn (): array => Page::query()->whereNotNull('title')->orderBy('title')->pluck('title', 'id')->all())
                    ->searchable()
                    ->helperText('Only published pages are linked; until then the button is hidden.')
                    ->visible(fn (Get $get): bool => $get('type') === self::TO_PAGE),
                TextInput::make('url')
                    ->label('Web address')
                    ->placeholder('https://')
                    ->maxLength(2048)
                    ->helperText('Must start with https://.')
                    ->visible(fn (Get $get): bool => $get('type') === self::TO_URL),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(string $prefix, bool $required, bool $forSubmission): array
    {
        return [
            $prefix => ['nullable', 'array:label,type,page_id,url'],
            "{$prefix}.label" => [$forSubmission && $required ? 'required' : 'nullable', 'string', 'max:40', ...($forSubmission ? ["required_with:{$prefix}.type"] : [])],
            "{$prefix}.type" => [$forSubmission && $required ? 'required' : 'nullable', Rule::in([self::TO_PAGE, self::TO_URL]), ...($forSubmission ? ["required_with:{$prefix}.label"] : [])],
            "{$prefix}.page_id" => ['nullable', 'integer', Rule::exists('pages', 'id'), ...($forSubmission ? ["required_if:{$prefix}.type,".self::TO_PAGE] : [])],
            "{$prefix}.url" => ['nullable', 'string', 'max:2048', $this->safeUrlRule(), ...($forSubmission ? ["required_if:{$prefix}.type,".self::TO_URL] : [])],
        ];
    }

    /**
     * The link as it is shown: the address of a published page, or the https address. Null when the
     * link is incomplete or its page is not published.
     *
     * @param  array<string, mixed>|null  $link
     * @return array{label: string, href: string, external: bool}|null
     */
    public function present(?array $link): ?array
    {
        if (blank($link['label'] ?? null)) {
            return null;
        }

        $href = match ($link['type'] ?? null) {
            self::TO_PAGE => $this->publishedPagePath($link['page_id'] ?? null),
            self::TO_URL => $this->safeExternalUrl($link['url'] ?? null),
            default => null,
        };

        return $href === null ? null : ['label' => (string) $link['label'], 'href' => $href, 'external' => ($link['type'] ?? null) === self::TO_URL];
    }

    public function isSafeExternalUrl(string $url): bool
    {
        return str_starts_with(strtolower(trim($url)), 'https://') && $this->richText->isSafeLink($url);
    }

    private function safeUrlRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (filled($value) && (! is_string($value) || ! $this->isSafeExternalUrl($value))) {
                $fail('The web address must be a full https:// address.');
            }
        };
    }

    private function publishedPagePath(mixed $pageId): ?string
    {
        $slug = is_numeric($pageId) ? Page::query()->whereKey((int) $pageId)->where('status', 'published')->value('slug') : null;

        return $slug === null ? null : '/'.$slug;
    }

    private function safeExternalUrl(mixed $url): ?string
    {
        return is_string($url) && $this->isSafeExternalUrl($url) ? $url : null;
    }
}
