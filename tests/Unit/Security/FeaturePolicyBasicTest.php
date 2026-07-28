<?php

use App\Security\FeaturePolicyBasic;
use CodebarAg\LaravelFeaturePolicy\Directive;

it('configures geolocation and fullscreen directives', function () {
    $policy = new FeaturePolicyBasic;
    $policy->configure();

    $reflection = new ReflectionClass($policy);
    $prop = $reflection->getProperty('directives');
    $prop->setAccessible(true);
    /** @var array<string, mixed> $directives */
    $directives = $prop->getValue($policy);

    expect($directives)->toHaveKeys([Directive::GEOLOCATION, Directive::FULLSCREEN]);
})->group('unit', 'security');
