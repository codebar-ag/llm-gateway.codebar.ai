<?php

it('serves the sitemap to guests as xml', function () {
    $response = $this->get(route('sitemap'))
        ->assertOk()
        ->assertSee('<urlset', false)
        ->assertSee(route('start.index'), false);

    expect($response->headers->get('Content-Type'))->toContain('text/xml');
})->group('controllers', 'sitemap');
