<?php

use App\Helpers\Facades\HelperMarkdown;

it('helper markdown: format markdown', function () {
    $markdown = '# Hello World';

    $formattedHtml = HelperMarkdown::formatMarkdown($markdown);
    expect($formattedHtml)
        ->toBeString()
        ->toEqual("<h1>Hello World</h1>\n");
})->group('helper', 'markdown');
