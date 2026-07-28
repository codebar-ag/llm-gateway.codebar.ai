<?php

namespace App\Nova\Resources;

use App\Enums\LocaleEnum;
use App\Enums\RoleEnum;
use App\Nova\Traits\NovaCustomOrderTrait;
use App\Nova\Traits\NovaIdentificationPanelTrait;
use App\Nova\Traits\NovaLanguageTrait;
use App\Nova\Traits\NovaTimestampsPanelTrait;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;
use Illuminate\Validation\Rules\Enum;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Card;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\MorphOne;
use Laravel\Nova\Fields\Password;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Laravel\Nova\Panel;

/**
 * @extends Resource<\App\Models\User>
 */
class User extends Resource
{
    use NovaCustomOrderTrait;
    use NovaIdentificationPanelTrait;
    use NovaLanguageTrait;
    use NovaTimestampsPanelTrait;

    public const string RESOURCE_KEY = 'user';

    public static $tableStyle = 'tight';

    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\User>
     */
    public static string $model = \App\Models\User::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * The columns that should be searched.
     *
     * @var list<string>
     */
    public static $search = [
        'id', 'name', 'email',
    ];

    /**
     * Build an "index" query for the given resource.
     */
    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query->with(['roles', 'microsoftIdentity']);
    }

    /**
     * Build a "detail" query for the given resource.
     */
    public static function detailQuery(NovaRequest $request, Builder $query): Builder
    {
        return parent::detailQuery($request, $query)->with(['roles', 'microsoftIdentity']);
    }

    public static function editQuery(NovaRequest $request, Builder $query): Builder
    {
        return parent::editQuery($request, $query)->with(['roles', 'microsoftIdentity']);
    }

    public static function relatableQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query->with(['roles', 'microsoftIdentity']);
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

            Text::make(__('nova.resources.user.fields.name'), 'name')
                ->sortable()
                ->rules('required', 'max:255'),

            Text::make(__('nova.resources.user.fields.email'), 'email')
                ->sortable()
                ->rules('required', 'email', 'max:254')
                ->creationRules('unique:users,email')
                ->updateRules('unique:users,email,{{resourceId}}'),

            DateTime::make(__('nova.resources.user.fields.email_verified_at'), 'email_verified_at')
                ->sortable()
                ->creationRules('nullable', 'date'),

            Password::make(__('nova.resources.user.fields.password'), 'password')
                ->onlyOnForms()
                ->creationRules('required', Rules\Password::defaults())
                ->updateRules('nullable', Rules\Password::defaults()),

            Select::make(__('nova.resources.user.fields.locale'), 'locale')
                ->canSee(fn (NovaRequest|Request $request) => $request->user()?->hasRole(RoleEnum::ADMINISTRATOR) ?? false)
                ->options(LocaleEnum::class)
                ->displayUsing(fn (string $value): string => LocaleEnum::from($value)->getLabel())
                ->rules('required', 'max:255', new Enum(LocaleEnum::class)),

            Boolean::make(__('nova.resources.user.fields.microsoft_sso_linked'), function (): bool {
                return $this->microsoftIdentity !== null;
            })
                ->onlyOnIndex()
                ->canSee(fn (NovaRequest|Request $request) => $request->user()?->hasRole(RoleEnum::ADMINISTRATOR) ?? false),

            BelongsToMany::make(__('nova.resources.user.relations.roles'), 'roles', Role::class),

            // MorphOne/HasOne do not support exceptOnIndex(); use hideFromIndex() to omit from the index API/UI.
            MorphOne::make(__('nova.resources.user.relations.microsoft_identity'), 'microsoftIdentity', MicrosoftSSOIdentity::class)
                ->hideFromIndex()
                ->hideWhenCreating()
                ->canSee(fn (NovaRequest|Request $request) => $request->user()?->hasRole(RoleEnum::ADMINISTRATOR) ?? false),

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
