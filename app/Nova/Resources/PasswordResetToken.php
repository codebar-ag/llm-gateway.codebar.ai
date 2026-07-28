<?php

namespace App\Nova\Resources;

use App\Nova\Traits\NovaCustomOrderTrait;
use App\Nova\Traits\NovaLanguageTrait;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Card;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Laravel\Nova\Panel;

/**
 * @extends Resource<\App\Models\PasswordResetToken>
 */
class PasswordResetToken extends Resource
{
    use NovaCustomOrderTrait;
    use NovaLanguageTrait;

    public const string RESOURCE_KEY = 'password_reset_token';

    public static string $model = \App\Models\PasswordResetToken::class;

    public static $globallySearchable = false;

    /**
     * @var string
     */
    public static $title = 'email';

    /**
     * @var list<string>
     */
    public static $search = [
        'email', 'token',
    ];

    /**
     * @return list<Field|Panel>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make(__('nova.resources.password_reset_token.fields.email'), 'email')
                ->sortable()
                ->readonly(),

            Text::make(__('nova.resources.password_reset_token.fields.token'), 'token')
                ->onlyOnDetail()
                ->readonly(),

            DateTime::make(__('nova.resources.password_reset_token.fields.created_at'), 'created_at')
                ->sortable()
                ->readonly(),
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
