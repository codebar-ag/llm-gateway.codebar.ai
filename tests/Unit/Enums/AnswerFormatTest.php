<?php

declare(strict_types=1);

use App\Enums\AnswerFormat;

it('reads a JSON object or array off the answer', function (string $answer) {
    expect(AnswerFormat::of($answer))->toBe(AnswerFormat::Json);
})->with([
    'object' => ['{"total":1284.50}'],
    'array' => ['[{"nr":1},{"nr":2}]'],
    'indented already' => ["{\n  \"total\": 1284.50\n}"],
    'leading whitespace' => ["  \n{\"total\":1}"],
]);

it('treats anything else as markdown', function (?string $answer) {
    expect(AnswerFormat::of($answer))->toBe(AnswerFormat::Markdown);
})->with([
    'prose' => ['The answer is 42.'],
    'heading' => ["# Heading\n\nbody"],
    'empty' => [''],
    'null' => [null],
    // Valid JSON on its own, but a model that answers with a scalar wrote
    // prose — naming that `.json` would misdescribe its shape.
    'bare number' => ['42'],
    'bare string' => ['"42"'],
    // Opens like an object and then is not one.
    'truncated object' => ['{"total": '],
    'brace in prose' => ['{ this is not json'],
]);

it('names the file extension and content type for each format', function () {
    expect(AnswerFormat::Json->extension())->toBe('json')
        ->and(AnswerFormat::Json->contentType())->toBe('application/json')
        ->and(AnswerFormat::Markdown->extension())->toBe('md')
        ->and(AnswerFormat::Markdown->contentType())->toBe('text/markdown; charset=utf-8');
});
