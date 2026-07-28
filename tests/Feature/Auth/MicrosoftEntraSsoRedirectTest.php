<?php

it('redirects to Microsoft authorize URL when Entra is configured', function () {
    config([
        'microsoft-entra-sso.client_id' => 'test-client-id',
        'microsoft-entra-sso.client_secret' => 'test-secret',
        'microsoft-entra-sso.tenant_id' => 'test-tenant-id',
        'microsoft-entra-sso.redirect_uri' => 'https://laravel-start.test/sso/microsoft/web/callback',
    ]);

    $response = $this->withSession([])->get(route('microsoft-entra-sso.redirect', ['guard' => 'web']));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('login.microsoftonline.com');
})->group('controllers', 'auth', 'entra');
