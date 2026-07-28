<?php

use App\Security\SecurityPolicyBasic;
use App\Support\CspAllowlist;
use Spatie\Csp\Keyword;
use Spatie\Csp\Nonce\RandomString;

return [

    /*
     * Presets will determine which CSP headers will be set. A valid CSP preset is
     * any class that implements `Spatie\Csp\Preset`
     */
    'presets' => [
        SecurityPolicyBasic::class,
    ],

    /*
     * Per-directive source lists for SecurityPolicyBasic. Merged with config/csp-allowlists.json
     * via CspAllowlist. This is not Spatie's global `directives` array below.
     */
    'directive_sources' => [
        'connect' => array_merge(
            [Keyword::SELF],
            CspAllowlist::sources('connect'),
            [env('APP_URL')],
        ),
        'default' => [
            Keyword::SELF,
        ],
        'form_action' => [
            Keyword::SELF,
        ],
        'img' => array_merge(
            [Keyword::SELF],
            CspAllowlist::sources('img'),
            [env('APP_URL')],
        ),
        'media' => array_merge(
            [Keyword::SELF, env('APP_URL')],
            CspAllowlist::sources('media'),
        ),
        'object' => [
            Keyword::NONE,
        ],
        'font' => array_merge(
            [Keyword::SELF],
            CspAllowlist::sources('font'),
        ),
        'script' => array_merge(
            [Keyword::SELF, Keyword::UNSAFE_INLINE, Keyword::UNSAFE_EVAL],
            CspAllowlist::sources('script'),
        ),
        'style_elem' => array_merge(
            [Keyword::SELF, Keyword::UNSAFE_INLINE],
            CspAllowlist::sources('style_elem'),
        ),
        'style' => array_merge(
            [Keyword::SELF, Keyword::UNSAFE_INLINE],
            CspAllowlist::sources('style'),
        ),
    ],

    /**
     * Register additional global CSP directives here.
     */
    'directives' => [
        //
    ],

    /*
     * These presets which will be put in report-only mode. This is great for testing out
     * a new policy or changes to existing CSP policy without breaking anything.
     */
    'report_only_presets' => [
        //
    ],

    /**
     * Register additional global report-only CSP directives here.
     */
    'report_only_directives' => [
        //
    ],

    /*
     * All violations against the policy will be reported to this url.
     * A great service you could use for this is https://report-uri.com/
     *
     * You can override this setting by calling `reportTo` on your policy.
     */
    'report_uri' => env('CSP_REPORT_URI', ''),

    /*
     * Headers will only be added if this setting is set to true.
     */
    'enabled' => env('CSP_ENABLED', false),

    /**
     * Headers will be added when Vite is hot reloading.
     */
    'enabled_while_hot_reloading' => env('CSP_ENABLED_WHILE_HOT_RELOADING', false),

    /*
     * The class responsible for generating the nonces used in inline tags and headers.
     */
    'nonce_generator' => RandomString::class,

    /*
     * Set false to disable automatic nonce generation and handling.
     * This is useful when you want to use 'unsafe-inline' for scripts/styles
     * and cannot add inline nonces.
     * Note that this will make your CSP policy less secure.
     */
    'nonce_enabled' => env('CSP_NONCE_ENABLED', true),
];
