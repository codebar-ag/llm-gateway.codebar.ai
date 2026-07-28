<?php

namespace App\Nova\Resources;

use App\Enums\RoleEnum;
use App\Nova\Traits\NovaCustomOrderTrait;
use App\Nova\Traits\NovaIdentificationPanelTrait;
use App\Nova\Traits\NovaLanguageTrait;
use App\Nova\Traits\NovaTimestampsPanelTrait;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Card;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Laravel\Nova\Panel;

/**
 * @extends Resource<\App\Models\Role>
 */
class Role extends Resource
{
    use NovaCustomOrderTrait;
    use NovaIdentificationPanelTrait;
    use NovaLanguageTrait;
    use NovaTimestampsPanelTrait;

    public const string RESOURCE_KEY = 'role';

    /**
     * The model the resource corresponds to.
     */
    public static string $model = \App\Models\Role::class;

    public function title(): string
    {
        /** @var \App\Models\Role $model */
        $model = $this->resource;

        return RoleEnum::tryFrom($model->name)?->label() ?? 'Unknown Role';
    }

    /**
     * The columns that should be searched.
     *
     * @var list<string>
     */
    public static $search = [
        'id', 'name',
    ];

    /**
     * Build an "index" query for the given resource.
     */
    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query->withCount('users');
    }

    /**
     * Build a "detail" query for the given resource.
     */
    public static function detailQuery(NovaRequest $request, Builder $query): Builder
    {
        return parent::detailQuery($request, $query)->with('users');
    }

    public static function relatableQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query->with('users');
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @return list<Field|Panel>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            $this->identificationPanel(),

            Text::make(__('nova.resources.role.fields.name'))
                ->displayUsing(function () {
                    /** @var \App\Models\Role $model */
                    $model = $this->resource;

                    return RoleEnum::tryFrom($model->name)?->label() ?? 'Unknown Role';
                })
                ->sortable()
                ->rules('required', 'string', 'max:255'),

            BelongsToMany::make(__('nova.resources.role.relations.users'), 'users', User::class),

            $this->timestampsPanel(true, true, false),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @return list<Card>
     */
    public function cards(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @return list<Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @return list<Lens>
     */
    public function lenses(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return list<Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [];
    }
}
