<?php

namespace App\Services\Content;

use DOMDocument;
use DOMElement;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Rich text written in the page editor. Executable or embedded content is refused when a draft is
 * saved, and everything is passed through a strict allowlist before it is stored and again before it
 * is shown, so a payload written by any route can never put script, styles or tracking images on a
 * public page (stored XSS).
 */
class RichText
{
    public const int MAX_LENGTH = 20000;

    /**
     * Elements that can run code, load something from elsewhere or collect input.
     *
     * @var list<string>
     */
    private const UNSAFE_ELEMENTS = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'form', 'input', 'button',
        'textarea', 'select', 'svg', 'math', 'link', 'meta', 'base', 'img', 'picture', 'video', 'audio', 'source',
        'track', 'template', 'noscript', 'canvas',
    ];

    /**
     * Attributes that hold a URL.
     *
     * @var list<string>
     */
    private const URL_ATTRIBUTES = ['href', 'src', 'srcset', 'action', 'formaction', 'xlink:href', 'poster', 'data', 'background'];

    /**
     * Why the HTML may not be saved, or null when it is safe.
     */
    public function unsafeContentReason(string $html): ?string
    {
        foreach ($this->elements($html) as $element) {
            if (in_array(strtolower($element->nodeName), self::UNSAFE_ELEMENTS, true)) {
                return 'Remove scripts, embedded content, images and forms from the text. Images are added with the media library.';
            }

            foreach ($element->attributes ?? [] as $attribute) {
                $name = strtolower($attribute->nodeName);

                if (str_starts_with($name, 'on')) {
                    return 'Remove scripts and event handlers from the text.';
                }

                if (in_array($name, self::URL_ATTRIBUTES, true) && ! $this->isSafeLink($attribute->nodeValue ?? '')) {
                    return 'Links must start with https://, mailto: or / (a page on this website).';
                }
            }
        }

        return null;
    }

    /**
     * Keeps only paragraphs, line breaks, emphasis, level-3 headings, lists, quotes and safe links.
     */
    public function sanitize(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        return trim((new HtmlSanitizer($this->config()))->sanitize($html));
    }

    /**
     * Whether the HTML contains any visible text.
     */
    public function hasText(?string $html): bool
    {
        return filled(trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5), " \t\n\r\0\x0B\u{A0}"));
    }

    /**
     * An https or mailto link, or a path on this website. Protocol-relative URLs (//host) are refused.
     */
    public function isSafeLink(string $url): bool
    {
        $url = trim($url);

        if ($url === '' || preg_match('/[\x00-\x20\x7F\\\\]/', $url)) {
            return false;
        }

        if (str_starts_with($url, '#') || (str_starts_with($url, '/') && ! str_starts_with($url, '//'))) {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return match ($scheme) {
            'https' => filled(parse_url($url, PHP_URL_HOST)) && parse_url($url, PHP_URL_USER) === null,
            'mailto' => true,
            default => false,
        };
    }

    private function config(): HtmlSanitizerConfig
    {
        return (new HtmlSanitizerConfig)
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('strong')
            ->allowElement('b')
            ->allowElement('em')
            ->allowElement('i')
            ->allowElement('u')
            ->allowElement('h3')
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('blockquote')
            ->allowElement('a', ['href'])
            ->blockElement('span')
            ->blockElement('div')
            ->allowLinkSchemes(['https', 'mailto'])
            ->allowRelativeLinks()
            ->withMaxInputLength(self::MAX_LENGTH * 2);
    }

    /**
     * @return iterable<DOMElement>
     */
    private function elements(string $html): iterable
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $document->getElementsByTagName('*');
    }
}
