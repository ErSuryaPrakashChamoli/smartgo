<?php

namespace App\Services\Website;

/**
 * Schema.org data for the page being rendered (FR-SEO-2), written once in the page's head as one JSON-LD
 * graph, so no type appears twice and nothing contradicts. It is collected from what the page shows while
 * it renders: the breadcrumb trail from the breadcrumb itself, the questions and answers from each
 * question shown (x-ui.faq-item), and the property or article from the record the page displays. Every
 * page adds SmartGro as an Organization (the approved name, the approved logo, and only the contact
 * details and social links that are published) and the WebSite. Nothing is written on a page without a
 * canonical address or marked "noindex" (previews, drafts, private and confirmation pages), and nothing
 * that is not on the page: no rating, review, price, availability or other unverified fact.
 */
class StructuredData
{
    private const string ATTRIBUTE = 'smartgro.structured-data';

    /**
     * A node for the page's main subject, such as its LocalBusiness or Article; one per key.
     *
     * @param  array<string, mixed>  $node
     */
    public function add(string $key, array $node): void
    {
        $this->put(fn (array $state): array => [...$state, 'nodes' => [...($state['nodes'] ?? []), $key => $node]]);
    }

    /**
     * The breadcrumb trail as shown: label and address of each step, the last being the page itself.
     * Only the first trail of a page counts.
     *
     * @param  list<array{label: string, href?: string|null}>  $items
     */
    public function breadcrumbs(array $items): void
    {
        $this->put(fn (array $state): array => isset($state['breadcrumbs']) ? $state : [...$state, 'breadcrumbs' => $items]);
    }

    /**
     * A question shown with its answer, as plain text; a question shown twice counts once.
     */
    public function question(string $question, string $answer): void
    {
        $question = trim($question);
        $answer = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($answer), ENT_QUOTES | ENT_HTML5)));

        if ($question !== '' && $answer !== '') {
            $this->put(fn (array $state): array => [...$state, 'questions' => [...($state['questions'] ?? []), $question => $answer]]);
        }
    }

    /**
     * The JSON-LD script for the page's head, or nothing for a page that is not indexed.
     *
     * @param  array<string, mixed>|null  $navigation  the published menus, contact details and social links
     */
    public function script(?string $canonical, bool $indexed, ?array $navigation): string
    {
        $graph = $canonical === null || ! $indexed ? [] : $this->graph($canonical, $navigation ?? []);

        return $graph === [] ? '' : '<script type="application/ld+json">'
            .json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR)
            .'</script>';
    }

    /**
     * @param  array<string, mixed>  $navigation
     * @return list<array<string, mixed>>
     */
    public function graph(string $canonical, array $navigation): array
    {
        $state = request()->attributes->get(self::ATTRIBUTE, []);
        $base = rtrim((string) config('app.url'), '/');
        $contact = collect($navigation['contact'] ?? [])->pluck('text', 'label');

        $graph = [
            array_filter([
                '@type' => 'Organization',
                '@id' => $base.'/#organization',
                'name' => 'SmartGro',
                'url' => $base.'/',
                'logo' => ['@type' => 'ImageObject', 'url' => $base.'/brand/smartgro-logo.webp', 'width' => 388, 'height' => 218],
                'email' => $contact->get('Email'),
                'telephone' => $contact->get('Phone'),
                'sameAs' => collect($navigation['social'] ?? [])->pluck('href')->filter()->values()->all() ?: null,
            ]),
            ['@type' => 'WebSite', '@id' => $base.'/#website', 'name' => 'SmartGro', 'url' => $base.'/', 'publisher' => ['@id' => $base.'/#organization']],
            ...array_values($state['nodes'] ?? []),
        ];

        $items = array_values($state['breadcrumbs'] ?? []);
        $linked = collect($items)->slice(0, -1)->every(fn (array $item): bool => filled($item['href'] ?? null));

        // A step other than the page itself shown without a link cannot be described, so no trail then.
        if ($items !== [] && $linked) {
            $graph[] = [
                '@type' => 'BreadcrumbList',
                'itemListElement' => array_map(fn (array $item, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $item['label'],
                    'item' => $index === count($items) - 1 ? $canonical : (str_starts_with((string) $item['href'], 'http') ? $item['href'] : $base.$item['href']),
                ], $items, array_keys($items)),
            ];
        }

        if (($state['questions'] ?? []) !== []) {
            $graph[] = [
                '@type' => 'FAQPage',
                'url' => $canonical,
                'mainEntity' => collect($state['questions'])->map(fn (string $answer, string $question): array => [
                    '@type' => 'Question',
                    'name' => $question,
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer],
                ])->values()->all(),
            ];
        }

        return $graph;
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $change
     */
    private function put(callable $change): void
    {
        request()->attributes->set(self::ATTRIBUTE, $change(request()->attributes->get(self::ATTRIBUTE, [])));
    }
}
