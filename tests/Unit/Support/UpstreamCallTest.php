<?php

declare(strict_types=1);

use App\Support\UpstreamCall;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response;

it('answers a successful upstream call as json', function () {
    $upstream = new Response(new Psr7Response(200, [], '{"object":"list"}'));

    $json = UpstreamCall::respond(
        fn (): Response => $upstream,
        fn (array $body): array => [...$body, 'normalised' => true],
    );

    expect($json->getStatusCode())->toBe(200)
        ->and($json->getData(true))->toBe(['object' => 'list', 'normalised' => true]);
});
