<?php

namespace App\Nova\Resources;

use App\Nova\Resources\User as UserNovaResource;
use App\Nova\Traits\NovaCustomOrderTrait;
use App\Nova\Traits\NovaIdentificationPanelTrait;
use App\Nova\Traits\NovaLanguageTrait;
use App\Nova\Traits\NovaTimestampsPanelTrait;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Card;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\MorphTo;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Laravel\Nova\Panel;

/**
 * @extends Resource<\CodebarAg\MicrosoftEntraSSO\Models\MicrosoftSSOIdentity>
 */
class MicrosoftSSOIdentity extends Resource
{
    use NovaCustomOrderTrait;
    use NovaIdentificationPanelTrait;
    use NovaLanguageTrait;
    use NovaTimestampsPanelTrait;

    public const string RESOURCE_KEY = 'microsoft_sso_identity';

    /**
     * @var class-string<\CodebarAg\MicrosoftEntraSSO\Models\MicrosoftSSOIdentity>
     */
    public static string $model = \CodebarAg\MicrosoftEntraSSO\Models\MicrosoftSSOIdentity::class;

    public static $displayInNavigation = false;

    public static $title = 'microsoft_id';

    /**
     * @var list<string>
     */
    public static $search = [
        'microsoft_id',
    ];

    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query->with('authenticatable');
    }

    public static function detailQuery(NovaRequest $request, Builder $query): Builder
    {
        return parent::detailQuery($request, $query)->with('authenticatable');
    }

    /**
     * @return list<Field|Panel>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            $this->identificationPanel(),

            MorphTo::make(__('nova.resources.microsoft_sso_identity.relations.user'), 'authenticatable')
                ->types([
                    UserNovaResource::class,
                ])
                ->readonly()
                ->exceptOnForms(),

            Text::make(__('nova.resources.microsoft_sso_identity.fields.microsoft_id'), 'microsoft_id')
                ->readonly(),

            DateTime::make(__('nova.resources.microsoft_sso_identity.fields.token_expires_at'), 'token_expires_at')
                ->readonly(),

            DateTime::make(__('nova.resources.microsoft_sso_identity.fields.linked_at'), 'linked_at')
                ->readonly(),

            $this->timestampsPanel(true, true, false),
        ];
    }

    /**
     * @return list<Card>
     */
    public function cards(NovaRequest $request): array
    {
        return [];
    }

    /**
     * @return list<Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [];
    }

    /**
     * @return list<Lens>
     */
    public function lenses(NovaRequest $request): array
    {
        return [];
    }

    /**
     * @return list<Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [];
    }
}
