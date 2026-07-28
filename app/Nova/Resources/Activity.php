<?php

namespace App\Nova\Resources;

use App\Nova\Traits\NovaCustomOrderTrait;
use App\Nova\Traits\NovaIdentificationPanelTrait;
use App\Nova\Traits\NovaLanguageTrait;
use App\Nova\Traits\NovaTimestampsPanelTrait;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\MorphTo;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;
use Laravel\Nova\Query\Search\SearchableMorphToRelation;

use function __;

/**
 * @extends Resource<\App\Models\Activity>
 */
class Activity extends Resource
{
    use NovaCustomOrderTrait;
    use NovaIdentificationPanelTrait;
    use NovaLanguageTrait;
    use NovaTimestampsPanelTrait;

    public const string RESOURCE_KEY = 'activity';

    public static $displayInNavigation = true;

    public static string $model = \App\Models\Activity::class;

    public static $perPageOptions = [25, 50, 100, 200];

    public static $perPageViaRelationship = 25;

    public static bool $withoutActionEvents = true;

    public static $globallySearchable = false;

    public static function usesScout(): bool
    {
        return false;
    }

    public function title(): string
    {
        return 'id';
    }

    /**
     * Build an "index" query for the given resource.
     */
    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query->with(['subject', 'causer']);
    }

    /**
     * Build a "detail" query for the given resource.
     */
    public static function detailQuery(NovaRequest $request, Builder $query): Builder
    {
        return parent::detailQuery($request, $query)->with(['subject', 'causer']);
    }

    /**
     * Get the searchable columns for the resource.
     *
     * @return list<string|SearchableMorphToRelation>
     */
    public static function searchableColumns(): array
    {
        return [
            'log_name',
            new SearchableMorphToRelation('causer', 'name', [
                User::class,
            ]),
            new SearchableMorphToRelation('subject', 'title', [
                User::class,
            ]),

            new SearchableMorphToRelation('subject', 'name', [
                User::class,
                Role::class,
            ]),
        ];
    }

    /**
     * @return list<Field|Panel>
     */
    public function fields(NovaRequest $request): array
    {
        return [

            ID::make()->sortable(),

            Text::make(__('nova.resources.activity.fields.log_name'), 'log_name')
                ->filterable()
                ->readonly(),

            Text::make(__('nova.resources.activity.fields.description'), 'description')
                ->hideFromIndex()
                ->readonly(),

            MorphTo::make(__('nova.resources.activity.relations.subject'), 'subject'),

            Text::make(__('nova.resources.activity.fields.event'), 'event')
                ->filterable()
                ->readonly(),

            MorphTo::make(__('nova.resources.activity.relations.causer'), 'causer'),

            Code::make(__('nova.resources.activity.fields.properties'), 'properties')
                ->json()
                ->readonly(),

            DateTime::make(__('nova.resources.activity.fields.created_at'), 'created_at')
                ->onlyOnIndex(),

            $this->timestampsPanel(true, true, false),
        ];
    }

    /**
     * @return list<Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [

        ];
    }
}
