<?php

namespace Tests\Support\Seo;

/**
 * Test-only checks of SmartGro's JSON-LD (FR-SEO-2, AC-11.2): the JSON parses; it is one schema.org graph;
 * each node is one of the types SmartGro writes and carries the properties schema.org and Google's
 * structured-data documentation require or recommend for it; references resolve; no type appears twice;
 * and nothing SmartGro has no verified fact for (ratings, reviews, offers, prices, availability) appears
 * anywhere. Syntax and properties only: passing says nothing about whether a search engine shows a rich
 * result. Written for the project from those rules (no validator package is installed).
 */
final class StructuredDataValidator
{
    private const array TYPES = ['Organization', 'WebSite', 'LocalBusiness', 'BreadcrumbList', 'FAQPage', 'Article'];

    private const array FORBIDDEN = ['aggregateRating', 'review', 'reviews', 'offers', 'price', 'priceRange', 'availability', 'rating'];

    /**
     * The JSON-LD scripts of an HTML page.
     *
     * @return list<string>
     */
    public static function scripts(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

        return $matches[1];
    }

    /**
     * Every problem found in one script; an empty list means it passes.
     *
     * @return list<string>
     */
    public static function errors(string $json, ?string $canonical = null): array
    {
        try {
            $document = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            return ['not valid JSON: '.$exception->getMessage()];
        }

        $errors = [];
        $check = function (bool $condition, string $message) use (&$errors): void {
            if (! $condition) {
                $errors[] = $message;
            }
        };

        $check(($document['@context'] ?? null) === 'https://schema.org', '@context is not https://schema.org');
        $graph = $document['@graph'] ?? null;
        $check(is_array($graph) && array_is_list($graph) && $graph !== [], '@graph is not a non-empty list');

        if (! is_array($graph)) {
            return $errors;
        }

        $types = array_map(fn (array $node): string => (string) ($node['@type'] ?? ''), $graph);
        $ids = array_filter(array_map(fn (array $node): ?string => $node['@id'] ?? null, $graph));
        $check(count($types) === count(array_unique($types)), 'a type appears twice: '.implode(', ', $types));
        $check(count($ids) === count(array_unique($ids)), 'an @id appears twice');

        array_walk_recursive($document, function (mixed $value, string|int $key) use ($check, $ids): void {
            $check(! in_array($key, self::FORBIDDEN, true), "unverified property {$key}");
            $check($key !== '@id' || in_array($value, $ids, true), "reference {$value} does not resolve");
        });

        foreach ($graph as $node) {
            $type = (string) ($node['@type'] ?? '');
            $check(in_array($type, self::TYPES, true), "unexpected type {$type}");
            $url = fn (mixed $value): bool => is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#', $value) === 1;

            match ($type) {
                'Organization' => [$check(filled($node['name'] ?? null), 'Organization: no name'), $check($url($node['url'] ?? null), 'Organization: url'), $check($url($node['logo']['url'] ?? null), 'Organization: logo'),
                    $check(collect($node['sameAs'] ?? [])->every($url), 'Organization: sameAs')],
                'WebSite' => [$check(filled($node['name'] ?? null), 'WebSite: no name'), $check($url($node['url'] ?? null), 'WebSite: url')],
                'LocalBusiness' => [$check(filled($node['name'] ?? null), 'LocalBusiness: no name'), $check($url($node['url'] ?? null), 'LocalBusiness: url'),
                    $check(($node['address']['@type'] ?? null) === 'PostalAddress' && filled($node['address']['streetAddress'] ?? null) && filled($node['address']['addressLocality'] ?? null), 'LocalBusiness: address'),
                    $check(! isset($node['geo']) || (abs((float) ($node['geo']['latitude'] ?? 999)) <= 90 && abs((float) ($node['geo']['longitude'] ?? 999)) <= 180), 'LocalBusiness: geo'),
                    $check(collect($node['openingHoursSpecification'] ?? [])->every(fn (array $hours): bool => str_starts_with((string) ($hours['dayOfWeek'] ?? ''), 'https://schema.org/')
                        && preg_match('/^\d{2}:\d{2}$/', (string) ($hours['opens'] ?? '')) === 1 && preg_match('/^\d{2}:\d{2}$/', (string) ($hours['closes'] ?? '')) === 1), 'LocalBusiness: openingHoursSpecification')],
                'BreadcrumbList' => self::breadcrumbErrors($node, $canonical, $url, $check),
                'FAQPage' => [$check(is_array($node['mainEntity'] ?? null) && $node['mainEntity'] !== [], 'FAQPage: no question'),
                    $check(collect($node['mainEntity'] ?? [])->every(fn (array $question): bool => ($question['@type'] ?? null) === 'Question' && filled($question['name'] ?? null)
                        && ($question['acceptedAnswer']['@type'] ?? null) === 'Answer' && filled($question['acceptedAnswer']['text'] ?? null)), 'FAQPage: a question without its answer'),
                    $check(collect($node['mainEntity'] ?? [])->pluck('name')->duplicates()->isEmpty(), 'FAQPage: a question appears twice')],
                'Article' => [$check(filled($node['headline'] ?? null) && mb_strlen((string) $node['headline']) <= 110, 'Article: headline missing or over 110 characters'),
                    $check(preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', (string) ($node['datePublished'] ?? '')) === 1, 'Article: datePublished'),
                    $check(isset($node['author'], $node['publisher']), 'Article: author or publisher'), $check($url($node['url'] ?? null), 'Article: url'),
                    $check($canonical === null || ($node['url'] ?? null) === $canonical, 'Article: url is not the canonical address')],
                default => null,
            };
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function breadcrumbErrors(array $node, ?string $canonical, \Closure $url, \Closure $check): array
    {
        $items = $node['itemListElement'] ?? [];
        $check(is_array($items) && $items !== [], 'BreadcrumbList: no item');

        foreach (array_values(is_array($items) ? $items : []) as $index => $item) {
            $check(($item['@type'] ?? null) === 'ListItem' && ($item['position'] ?? null) === $index + 1, 'BreadcrumbList: positions are not 1, 2, 3…');
            $check(filled($item['name'] ?? null) && $url($item['item'] ?? null), 'BreadcrumbList: a step without its name or address');
        }

        $check($canonical === null || (end($items)['item'] ?? null) === $canonical, 'BreadcrumbList: the last step is not the canonical address');

        return [];
    }
}
