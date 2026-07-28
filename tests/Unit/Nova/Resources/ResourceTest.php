<?php

use App\Models\User as UserModel;
use App\Nova\Resources\Resource;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;
use Symfony\Component\HttpFoundation\Request;

it('passes through index detail and relatable queries', function () {
    $concrete = new class extends Resource
    {
        public static string $model = UserModel::class;

        public function fields(NovaRequest $request): array
        {
            return [];
        }
    };

    $request = NovaRequest::createFromBase(
        Request::create('/nova-api/users')
    );

    $query = UserModel::query();

    expect($concrete::indexQuery($request, $query))->toBe($query);

    $detail = $concrete::detailQuery($request, $query);
    expect($detail)->toBeInstanceOf(Builder::class);

    $relatable = $concrete::relatableQuery($request, $query);
    expect($relatable)->toBeInstanceOf(Builder::class);
})->group('unit', 'nova');
