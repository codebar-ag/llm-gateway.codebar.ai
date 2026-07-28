<?php

use Database\Seeders\DatabaseSeeder;

/**
 * Opt-in: same cookie-jar flow as a browser against APP_URL (e.g. Herd).
 *
 * INTEGRATION_HERD_LOGIN=1 ./vendor/bin/pest tests/Feature/Auth/HerdCookieJarLoginTest.php
 *
 * Base URL: INTEGRATION_HERD_ORIGIN, else config('app.url') when host is not 127.0.0.1, else https://laravel-start.test (Pest uses .env.testing which often points at 127.0.0.1 while Herd serves the hostname).
 */
test('cookie jar login reaches dashboard on herd origin', function () {
    if (($_ENV['INTEGRATION_HERD_LOGIN'] ?? getenv('INTEGRATION_HERD_LOGIN')) !== '1') {
        $this->markTestSkipped('Set INTEGRATION_HERD_LOGIN=1 to run curl integration against Herd.');
    }

    if (! function_exists('curl_init')) {
        $this->markTestSkipped('PHP curl extension required.');
    }

    $this->seed(DatabaseSeeder::class);

    $fromEnv = getenv('INTEGRATION_HERD_ORIGIN');
    if (is_string($fromEnv) && $fromEnv !== '') {
        $base = rtrim($fromEnv, '/');
    } else {
        $configured = rtrim((string) config('app.url'), '/');
        $host = parse_url($configured, PHP_URL_HOST);
        $base = ($host !== null && $host !== '' && $host !== '127.0.0.1')
            ? $configured
            : 'https://laravel-start.test';
    }
    $jar = tempnam(sys_get_temp_dir(), 'cj').'.txt';
    @unlink($jar);
    touch($jar);

    $loginHtml = herd_curl_request($base.'/auth/login', $jar, 'GET');
    expect($loginHtml['status'])->toBe(200)
        ->and($loginHtml['body'])->not->toBe('');

    preg_match('/name="_token" value="([^"]+)"/', $loginHtml['body'], $tm);
    preg_match('/name="(my_name_[^"]+)"/', $loginHtml['body'], $hm);
    preg_match('/name="valid_from"\s+type="text"\s+value="([^"]+)"/', $loginHtml['body'], $vm);

    expect($tm[1] ?? null)->not->toBeNull('csrf token')
        ->and($hm[1] ?? null)->not->toBeNull('honeypot field')
        ->and($vm[1] ?? null)->not->toBeNull('valid_from');

    $postBody = http_build_query([
        '_token' => $tm[1],
        $hm[1] => '',
        'valid_from' => $vm[1],
        'email' => 'info@codebar.ch',
        'password' => 'password',
    ]);

    $post = herd_curl_request($base.'/auth/login/store', $jar, 'POST', $postBody, 'application/x-www-form-urlencoded');
    expect($post['status'])->toBe(302, 'login should redirect')
        ->and($post['location'] ?? '')->toContain(parse_url($base, PHP_URL_HOST) ?: '');

    $home = herd_curl_request($base.'/', $jar, 'GET');
    expect($home['status'])->toBe(200)
        ->and($home['body'])->toContain('Dashboard');

    @unlink($jar);
})->group('integration', 'herd');

/**
 * @return array{status: int, body: string, location: string|null}
 */
function herd_curl_request(string $url, string $jar, string $method, ?string $body = null, ?string $contentType = null): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false,
        // CURLINFO_HEADER_SIZE is unreliable with HTTP/2; force 1.1 for deterministic header/body split.
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    ];
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = $body ?? '';
        if ($contentType !== null) {
            $opts[CURLOPT_HTTPHEADER] = ['Content-Type: '.$contentType];
        }
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('curl request failed: '.($curlError !== '' ? $curlError : 'unknown error'));
    }

    if ($headerSize <= 0 || $headerSize > strlen($raw)) {
        throw new RuntimeException('curl response split failed (header_size='.$headerSize.', raw_len='.strlen($raw).')');
    }

    $headerBlock = substr($raw, 0, $headerSize);
    $respBody = substr($raw, $headerSize);
    $location = null;
    foreach (explode("\r\n", $headerBlock) as $line) {
        if (stripos($line, 'Location:') === 0) {
            $location = trim(substr($line, strlen('Location:')));

            break;
        }
    }

    return ['status' => $status, 'body' => $respBody, 'location' => $location];
}
