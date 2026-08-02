<?php

declare(strict_types=1);

use App\Support\ToolCalls;

it('reads the tool calls of a completion', function () {
    $calls = ToolCalls::fromCompletion(['choices' => [['message' => ['tool_calls' => [[
        'id' => 'call_1',
        'type' => 'function',
        'function' => ['name' => 'count_invoices', 'arguments' => '{"archive":"main"}'],
    ]]]]]]);

    expect($calls)->toBe([[
        'id' => 'call_1',
        'type' => 'function',
        'function' => ['name' => 'count_invoices', 'arguments' => '{"archive":"main"}'],
    ]]);
});

it('re-encodes arguments an upstream reported as an object', function () {
    $calls = ToolCalls::fromMessage(['tool_calls' => [[
        'function' => ['name' => 'count_invoices', 'arguments' => ['archive' => 'main']],
    ]]]);

    expect($calls[0]['function']['arguments'])->toBe('{"archive":"main"}');
});

it('mints an id for a call the upstream left unnamed', function () {
    $calls = ToolCalls::fromMessage(['tool_calls' => [['function' => ['name' => 'now']]]]);

    expect($calls[0]['id'])->toStartWith('call_')
        ->and($calls[0]['function']['arguments'])->toBe('{}');
});

it('drops a call with no name, since nothing can dispatch it', function () {
    expect(ToolCalls::fromMessage(['tool_calls' => [
        ['id' => 'call_1', 'function' => ['arguments' => '{}']],
        ['id' => 'call_2', 'function' => ['name' => 'ok']],
    ]]))->toHaveCount(1);
});

it('reads no calls from a message that made none', function (mixed $message) {
    expect(ToolCalls::fromMessage($message))->toBe([]);
})->with([
    [[]],
    [['tool_calls' => []]],
    [['content' => 'just text']],
    [null],
]);

it('renders calls as responses output items', function () {
    $items = ToolCalls::outputItems([[
        'id' => 'call_1',
        'type' => 'function',
        'function' => ['name' => 'count_invoices', 'arguments' => '{"archive":"main"}'],
    ]]);

    expect($items[0])->toMatchArray([
        'type' => 'function_call',
        'call_id' => 'call_1',
        'name' => 'count_invoices',
        'arguments' => '{"archive":"main"}',
        'status' => 'completed',
    ])->and($items[0]['id'])->toStartWith('fc_');
});

it('reads a responses function_call item back as a chat tool call', function () {
    expect(ToolCalls::fromInputItem([
        'type' => 'function_call',
        'id' => 'fc_1',
        'call_id' => 'call_1',
        'name' => 'count_invoices',
        'arguments' => '{"archive":"main"}',
    ]))->toBe([
        'id' => 'call_1',
        'type' => 'function',
        'function' => ['name' => 'count_invoices', 'arguments' => '{"archive":"main"}'],
    ]);
});

it('nests a tool the responses api stated flat', function () {
    expect(ToolCalls::tools([[
        'type' => 'function',
        'name' => 'count_invoices',
        'description' => 'How many invoices are archived.',
        'parameters' => ['type' => 'object'],
    ]]))->toBe([[
        'type' => 'function',
        'function' => [
            'name' => 'count_invoices',
            'description' => 'How many invoices are archived.',
            'parameters' => ['type' => 'object'],
        ],
    ]]);
});

it('carries the strict flag rather than dropping the caller constraint', function () {
    expect(ToolCalls::tools([['type' => 'function', 'name' => 'now', 'strict' => true]])[0]['function'])
        ->toBe(['name' => 'now', 'strict' => true]);
});

it('leaves a tool already in chat form alone', function () {
    $tool = ['type' => 'function', 'function' => ['name' => 'now', 'parameters' => ['type' => 'object']]];

    expect(ToolCalls::tools([$tool]))->toBe([$tool]);
});

/*
 | Nothing going up is ever dropped. The upstream is what decides whether it
 | can run a tool, and a `web_search` this app quietly removed would leave the
 | model answering from memory and the caller reading it as a searched answer.
 */

it('forwards a hosted tool for the upstream to judge', function (array $tool) {
    expect(ToolCalls::tools([$tool]))->toBe([$tool]);
})->with([
    [['type' => 'web_search']],
    [['type' => 'file_search', 'vector_store_ids' => ['vs_1']]],
    [['type' => 'web_search_20250305', 'name' => 'web_search', 'max_uses' => 3]],
]);

it('forwards a tool this app cannot read rather than removing it', function (mixed $tool) {
    expect(ToolCalls::tools([$tool]))->toBe([$tool]);
})->with([
    [['type' => 'function', 'function' => ['description' => 'no name']]],
    [['name' => 'no type at all']],
    [['type' => 'function']],
    [[]],
    ['a bare string'],
    [null],
]);

it('restates only the flat functions of a mixed list, in order', function () {
    expect(ToolCalls::tools([
        ['type' => 'web_search'],
        ['type' => 'function', 'name' => 'flat'],
        ['type' => 'function', 'function' => ['name' => 'already nested']],
    ]))->toBe([
        ['type' => 'web_search'],
        ['type' => 'function', 'function' => ['name' => 'flat']],
        ['type' => 'function', 'function' => ['name' => 'already nested']],
    ]);
});

it('takes no tools at all from a payload that declared none', function (mixed $tools) {
    expect(ToolCalls::tools($tools))->toBe([]);
})->with([[null], [[]]]);

it('drops the keys of a tools object stated as a map', function () {
    expect(ToolCalls::tools(['first' => ['type' => 'web_search']]))->toBe([['type' => 'web_search']]);
});

it('passes the tool_choice keywords through and rejects anything else', function () {
    expect(ToolCalls::choice('auto'))->toBe('auto')
        ->and(ToolCalls::choice('none'))->toBe('none')
        ->and(ToolCalls::choice('required'))->toBe('required')
        ->and(ToolCalls::choice('whatever'))->toBeNull()
        ->and(ToolCalls::choice(null))->toBeNull();
});

it('nests a forced tool the responses api named at the top level', function () {
    expect(ToolCalls::choice(['type' => 'function', 'name' => 'count_invoices']))
        ->toBe(['type' => 'function', 'function' => ['name' => 'count_invoices']]);
});

it('states missing arguments as an empty object, never as an empty array', function (mixed $value) {
    expect(ToolCalls::arguments($value))->toBe('{}');
})->with([[null], [[]], [''], ['   ']]);

it('decodes arguments back for the upstreams that want the object', function () {
    expect(ToolCalls::decodedArguments('{"archive":"main"}'))->toBe(['archive' => 'main'])
        ->and(ToolCalls::decodedArguments(['archive' => 'main']))->toBe(['archive' => 'main'])
        ->and(ToolCalls::decodedArguments('not json'))->toBe([]);
});

it('summarises the calls in an output list for the dashboard', function () {
    expect(ToolCalls::summarise([
        ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'one moment']]],
        ['type' => 'function_call', 'name' => 'count_invoices', 'arguments' => '{"archive":"main"}'],
    ]))->toBe('count_invoices({"archive":"main"})');
});

it('summarises nothing when no tool was asked for', function () {
    expect(ToolCalls::summarise([['type' => 'message']]))->toBeNull()
        ->and(ToolCalls::summarise([]))->toBeNull();
});

/*
 | Edge cases. Every value below arrives from a decoded upstream body or a
 | caller's payload, so none of them are hypothetical — they are what the
 | boundary actually has to survive.
 */

it('reads no calls from a tool_calls key that is not a list', function (mixed $toolCalls) {
    expect(ToolCalls::fromMessage(['tool_calls' => $toolCalls]))->toBe([]);
})->with([['a string'], [42], [false]]);

it('skips the entries of a tool_calls list that are not calls', function () {
    expect(ToolCalls::fromMessage(['tool_calls' => [
        'a bare string',
        null,
        ['function' => ['name' => 'kept']],
    ]]))->toHaveCount(1);
});

it('reads a completion whose choices are missing or empty', function (array $completion) {
    expect(ToolCalls::fromCompletion($completion))->toBe([]);
})->with([
    [[]],
    [['choices' => []]],
    [['choices' => [['message' => []]]]],
    [['choices' => 'not a list']],
]);

it('leaves unicode and slashes unescaped when re-encoding arguments', function () {
    expect(ToolCalls::arguments(['path' => 'a/b', 'note' => 'Rechnungsprüfung']))
        ->toBe('{"path":"a/b","note":"Rechnungsprüfung"}');
});

it('trims arguments an upstream padded but keeps them a string', function () {
    expect(ToolCalls::arguments('  {"a":1}  '))->toBe('{"a":1}');
});

/*
 | Arguments are never validated on the way through. A model that emitted
 | broken JSON is a fact the caller has to see, not one to be corrected into
 | an empty object that reads as a call with no arguments.
 */
it('passes invalid json arguments through untouched', function () {
    expect(ToolCalls::arguments('{"archive":'))->toBe('{"archive":');
});

it('decodes a json list of arguments as a list', function () {
    expect(ToolCalls::decodedArguments('[1,2]'))->toBe([1, 2]);
});

it('decodes to nothing what cannot be an argument object', function (mixed $value) {
    expect(ToolCalls::decodedArguments($value))->toBe([]);
})->with([['{"archive":'], [''], [null], ['"just a string"'], ['42']]);

it('renders no output items for a turn that asked for nothing', function () {
    expect(ToolCalls::outputItems([]))->toBe([]);
});

it('gives each output item its own id', function () {
    $items = ToolCalls::outputItems([
        ['id' => 'call_1', 'function' => ['name' => 'a', 'arguments' => '{}']],
        ['id' => 'call_2', 'function' => ['name' => 'b', 'arguments' => '{}']],
    ]);

    expect($items[0]['id'])->not->toBe($items[1]['id'])
        ->and($items[0]['call_id'])->toBe('call_1')
        ->and($items[1]['call_id'])->toBe('call_2');
});

it('reads a function_call item that named only an id', function () {
    expect(ToolCalls::fromInputItem(['type' => 'function_call', 'id' => 'fc_1', 'name' => 'now']))
        ->toBe([
            'id' => 'fc_1',
            'type' => 'function',
            'function' => ['name' => 'now', 'arguments' => '{}'],
        ]);
});

it('reads a function_call item whose arguments came as an object', function () {
    expect(ToolCalls::fromInputItem([
        'call_id' => 'call_1',
        'name' => 'now',
        'arguments' => ['tz' => 'Europe/Zurich'],
    ])['function']['arguments'])->toBe('{"tz":"Europe/Zurich"}');
});

it('rejects a tool_choice naming nothing', function (mixed $choice) {
    expect(ToolCalls::choice($choice))->toBeNull();
})->with([
    [['type' => 'function']],
    [['type' => 'function', 'function' => []]],
    [[]],
    [42],
    [''],
]);

it('accepts a forced tool already nested', function () {
    expect(ToolCalls::choice(['type' => 'function', 'function' => ['name' => 'now']]))
        ->toBe(['type' => 'function', 'function' => ['name' => 'now']]);
});

it('summarises several calls one per line', function () {
    expect(ToolCalls::summarise([
        ['type' => 'function_call', 'name' => 'count_invoices', 'arguments' => '{"archive":"main"}'],
        ['type' => 'function_call', 'name' => 'list_archives', 'arguments' => '{}'],
    ]))->toBe("count_invoices({\"archive\":\"main\"})\nlist_archives({})");
});

it('summarises an output list holding nothing it recognises', function (array $output) {
    expect(ToolCalls::summarise($output))->toBeNull();
})->with([
    [['a bare string']],
    [[['type' => 'reasoning']]],
]);
