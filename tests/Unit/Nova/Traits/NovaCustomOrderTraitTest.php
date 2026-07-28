<?php

use App\Models\User as UserModel;
use App\Nova\Traits\NovaCustomOrderTrait;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource as NovaResource;

it('applies default ordering when request has none', function () {
    $resourceClass = new class extends NovaResource
    {
        use NovaCustomOrderTrait;

        public static string $model = UserModel::class;

        /** @var array<string, string> */
        protected static array $orderBy = ['id' => 'desc'];

        public function fields(NovaRequest $request): array
        {
            return [];
        }
    };

    $query = UserModel::query();
    $method = new ReflectionMethod($resourceClass, 'applyOrderings');
    $method->setAccessible(true);
    /** @var Builder $built */
    $built = $method->invoke(null, $query, []);

    expect($built->toSql())->toContain('order by');
})->group('unit', 'nova', 'traits');
