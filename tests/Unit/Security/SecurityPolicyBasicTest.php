<?php

use App\Security\SecurityPolicyBasic;
use Spatie\Csp\Directive;
use Spatie\Csp\Policy as CspPolicy;

it('applies security header directives to csp policy', function () {
    config([
        'csp.directive_sources.connect' => ['self'],
        'csp.directive_sources.default' => ['self'],
        'csp.directive_sources.form_action' => ['self'],
        'csp.directive_sources.img' => ['self'],
        'csp.directive_sources.media' => ['self'],
        'csp.directive_sources.object' => ['none'],
        'csp.directive_sources.font' => ['self'],
        'csp.directive_sources.script' => ['self'],
        'csp.directive_sources.style_elem' => ['self'],
        'csp.directive_sources.style' => ['self'],
    ]);

    $csp = new CspPolicy;
    (new SecurityPolicyBasic)->configure($csp);

    expect($csp->isEmpty())->toBeFalse();
    expect($csp->getContents())->toContain(Directive::BASE->value);
})->group('unit', 'security');

it('csp policy factory create from preset', function () {
    $policy = CspPolicy::create([SecurityPolicyBasic::class]);
    expect($policy)->toBeInstanceOf(CspPolicy::class)
        ->and($policy->isEmpty())->toBeFalse();
})->group('unit', 'security');
