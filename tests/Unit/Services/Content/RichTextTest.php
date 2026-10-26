<?php

use App\Services\Content\RichText;

it('keeps the formatting the editor offers', function () {
    $html = '<h3>Opening hours</h3><p>Open <strong>daily</strong>, <em>including</em> holidays. <a href="https://example.com/map">Map</a> or <a href="/contact">contact us</a>.</p><ul><li>Desks</li></ul><ol><li>Visit</li></ol><blockquote>Quiet floors</blockquote>';

    expect((new RichText)->sanitize($html))->toBe($html);
});

it('strips scripts, styles, images, event handlers and unsafe links when sanitising', function () {
    $sanitized = (new RichText)->sanitize(
        '<p style="position:fixed" onclick="steal()">Hello<script>alert(1)</script></p>'
        .'<img src="https://tracker.example/x.gif" onerror="alert(1)">'
        .'<iframe src="https://evil.example"></iframe>'
        .'<a href="javascript:alert(1)">click</a><a href="http://plain.example">plain</a><span class="x">kept text</span>',
    );

    expect($sanitized)
        ->toContain('<p>Hello</p>')
        ->toContain('kept text')
        ->not->toContain('script')
        ->not->toContain('style')
        ->not->toContain('onclick')
        ->not->toContain('onerror')
        ->not->toContain('<img')
        ->not->toContain('iframe')
        ->not->toContain('javascript:')
        ->not->toContain('http://plain.example');
});

it('reports executable or embedded content as unsafe', function (string $html) {
    expect((new RichText)->unsafeContentReason($html))->not->toBeNull();
})->with([
    'script' => ['<p>Hi</p><script>alert(1)</script>'],
    'event handler' => ['<p onmouseover="alert(1)">Hi</p>'],
    'image' => ['<img src="https://example.com/a.png">'],
    'iframe' => ['<iframe src="https://example.com"></iframe>'],
    'svg' => ['<svg onload="alert(1)"></svg>'],
    'form' => ['<form action="https://example.com"><input name="q"></form>'],
    'javascript link' => ['<a href="javascript:alert(1)">x</a>'],
    'encoded javascript link' => ['<a href="java&#x09;script:alert(1)">x</a>'],
    'data link' => ['<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>'],
    'protocol-relative link' => ['<a href="//evil.example">x</a>'],
    'plain http link' => ['<a href="http://example.com">x</a>'],
    'link with credentials' => ['<a href="https://user:pass@example.com">x</a>'],
]);

it('accepts safe formatted text', function () {
    expect((new RichText)->unsafeContentReason('<p><strong>Desks</strong> from <a href="https://example.com">our site</a>, <a href="/about">about</a> or <a href="mailto:hello@example.com">email</a>.</p>'))->toBeNull();
});

it('detects text that is only markup or spaces', function (string $html, bool $hasText) {
    expect((new RichText)->hasText($html))->toBe($hasText);
})->with([
    'empty paragraph' => ['<p></p>', false],
    'non-breaking spaces' => ['<p>&nbsp; </p>', false],
    'words' => ['<p>Hello</p>', true],
]);
