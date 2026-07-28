<?php

declare(strict_types=1);

use App\Nova\Resources\MicrosoftSSOIdentity;
use Laravel\Nova\Fields\MorphTo;
use Laravel\Nova\Http\Requests\NovaRequest;
use Symfony\Component\HttpFoundation\Request;

it('exposes nova hook methods', function () {
    $request = NovaRequest::createFromBase(Request::create('/nova'));
    $model = new CodebarAg\MicrosoftEntraSSO\Models\MicrosoftSSOIdentity;
    $resource = new MicrosoftSSOIdentity($model);

    expect($resource->cards($request))->toBeArray();
    expect($resource->filters($request))->toBeArray();
    expect($resource->lenses($request))->toBeArray();
    expect($resource->actions($request))->toBeArray();
})->group('unit', 'nova');

it('includes morph to user on index and detail', function () {
    $request = NovaRequest::createFromBase(Request::create('/nova'));
    $model = new CodebarAg\MicrosoftEntraSSO\Models\MicrosoftSSOIdentity;
    $resource = new MicrosoftSSOIdentity($model);

    $morphTo = collect($resource->fields($request))
        ->first(fn ($f) => $f instanceof MorphTo && $f->attribute === 'authenticatable');

    expect($morphTo)->toBeInstanceOf(MorphTo::class);
})->group('unit', 'nova');
