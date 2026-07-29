<?php

declare(strict_types=1);

use App\Support\JsonHighlighter;
use Illuminate\Support\HtmlString;

it('returns an htmlable so blade does not escape the markup', function () {
    expect(JsonHighlighter::render(['a' => 1]))->toBeInstanceOf(HtmlString::class);
});

it('highlights keys and string values separately', function () {
    $html = JsonHighlighter::render(['model' => 'qwen3'])->toHtml();

    expect($html)->toContain('<span class="json-key">&quot;model&quot;</span>:')
        ->toContain('<span class="json-string">&quot;qwen3&quot;</span>');
});

it('highlights numbers', function (int|float $number) {
    expect(JsonHighlighter::render(['n' => $number])->toHtml())
        ->toContain('<span class="json-number">'.$number.'</span>');
})->with([42, -7, 3.5]);

it('highlights booleans and null', function () {
    $html = JsonHighlighter::render(['yes' => true, 'no' => false, 'nothing' => null])->toHtml();

    expect($html)->toContain('<span class="json-const">true</span>')
        ->toContain('<span class="json-const">false</span>')
        ->toContain('<span class="json-const">null</span>');
});

it('does not highlight keywords or numbers inside strings', function () {
    $html = JsonHighlighter::render(['text' => 'true 42 null'])->toHtml();

    expect($html)->toContain('<span class="json-string">&quot;true 42 null&quot;</span>')
        ->not->toContain('<span class="json-const">true</span>')
        ->not->toContain('<span class="json-number">42</span>');
});

it('escapes html so payloads cannot inject markup', function () {
    $html = JsonHighlighter::render(['evil' => '<script>alert(1)</script>'])->toHtml();

    expect($html)->toContain('&lt;script&gt;')
        ->not->toContain('<script>');
});

/*
 | Every double quote is an entity, including the ones delimiting the JSON. That
 | is what keeps the markup safe wherever it is rendered rather than only inside
 | a <pre>, where an unescaped quote happens not to matter.
 */

it('escapes quotes so the markup is safe in an attribute too', function () {
    $html = JsonHighlighter::render(['say' => 'he said "hi"'])->toHtml();

    $withoutMarkup = preg_replace('#<span class="json-[a-z]+">|</span>#', '', $html);

    expect($html)->toContain('&quot;')
        ->and($withoutMarkup)->not->toContain('"');
});

it('pretty prints nested structures', function () {
    $html = JsonHighlighter::render(['messages' => [['role' => 'user']]])->toHtml();

    expect($html)->toContain("\n")
        ->toContain('<span class="json-key">&quot;role&quot;</span>');
});

it('keeps slashes and unicode readable', function () {
    $html = JsonHighlighter::render(['url' => 'https://a.test/b', 'text' => 'grüezi'])->toHtml();

    expect($html)->toContain('https://a.test/b')->toContain('grüezi');
});

it('renders null and empty structures', function (mixed $value, string $expected) {
    expect(JsonHighlighter::render($value)->toHtml())->toContain($expected);
})->with([
    [null, '<span class="json-const">null</span>'],
    [[], '[]'],
    [new stdClass, '{}'],
]);

it('returns empty markup when the value cannot be encoded', function () {
    // Malformed UTF-8 makes json_encode fail.
    expect(JsonHighlighter::render(["\xB1\x31"])->toHtml())->toBe('');
});
