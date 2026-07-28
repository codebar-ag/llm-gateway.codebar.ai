<?php

namespace App\Nova\Providers;

use App\Enums\RoleEnum;
use App\Nova\Resources\Activity;
use App\Nova\Resources\MicrosoftSSOIdentity;
use App\Nova\Resources\PasswordResetToken;
use App\Nova\Resources\Role;
use App\Nova\Resources\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Laravel\Fortify\Features;
use Laravel\Nova\Dashboard;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Menu\MenuSection;
use Laravel\Nova\Nova;
use Laravel\Nova\NovaApplicationServiceProvider;
use Laravel\Nova\Tool;

class NovaServiceProvider extends NovaApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        Nova::mainMenu(fn (Request $request): array => $this->mainMenu($request));

        Nova::serving(function (): void {
            $preventLazyLoading = Config::boolean('default-nova.prevent_lazy_loading');
            Model::preventLazyLoading($preventLazyLoading);
        });

        $this->registerNovaBreadcrumbs();
        $this->registerNovaNotificationCenter();
        $this->registerNovaAssetScripts();

        Nova::footer(function (Request $request) {
            return Blade::render('&copy; '.date('Y').' <a target="_blank" class="link-default text-blue-500" href="https://www.codebar.ch">codebar Solutions AG</a>.');
        });
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        parent::register();

        $this->registerNovaResourcePaths();
        $this->registerNovaCustomInitialPath();
        $this->registerNovaPolicyGuesser();
    }

    protected function registerNovaResourcePaths(): void
    {
        $resourceIn = Config::string('default-nova.resource_in');

        if ($resourceIn === '') {
            return;
        }

        if (! is_dir(app_path($resourceIn))) {
            return;
        }

        Nova::resourcesIn(app_path($resourceIn));
    }

    protected function registerNovaCustomInitialPath(): void
    {
        $initialPath = Config::string('default-nova.initial_path');

        if ($initialPath === '') {
            return;
        }

        Nova::initialPath($initialPath);
    }

    protected function registerNovaPolicyGuesser(): void
    {
        Nova::serving(function (): void {
            Gate::guessPolicyNamesUsing(callback: function (string $class) {
                $namespace = Config::string('default-nova.policies.namespace');

                return $namespace.class_basename($class).'Policy';
            });
        });
    }

    /**
     * @return list<MenuSection>
     */
    protected function mainMenu(Request $request): array
    {
        return [

            MenuSection::make(__('Administration'), [
                MenuItem::resource(User::class),
                MenuItem::resource(Role::class),
                MenuItem::resource(MicrosoftSSOIdentity::class),
                MenuItem::resource(PasswordResetToken::class),
            ])->icon('users')->collapsable(),

            MenuSection::make(__('Monitoring'), [
                MenuItem::resource(Activity::class),
            ])->icon('code')
                ->collapsable()
                ->canSee(function (Request $inner) {
                    return $inner->user()?->hasAnyRole([
                        RoleEnum::ADMINISTRATOR,
                    ]) ?? false;
                }),
        ];
    }

    protected function registerNovaBreadcrumbs(): void
    {
        if (config('default-nova.with_breadcrumbs')) {
            Nova::withBreadcrumbs();
        }
    }

    protected function registerNovaNotificationCenter(): void
    {
        if (config('default-nova.without_notifications')) {
            Nova::withoutNotificationCenter();
        }
    }

    protected function registerNovaAssetScripts(): void
    {
        $assets = Config::array('default-nova.assets');

        if ($assets === []) {
            return;
        }

        foreach ($assets as $asset) {
            if (is_string($asset)) {
                Nova::script('custom', $asset);
            }
        }
    }

    /**
     * Register the Nova routes.
     */
    protected function routes(): void
    {
        Nova::routes()
            ->withoutAuthenticationRoutes(
                login: '/auth/login',
                logout: '/auth/logout',
            );
    }

    /**
     * Register the Nova gate.
     *
     * This gate determines who can access Nova in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewNova', function (\App\Models\User $user) {
            return $user->hasRole(RoleEnum::ADMINISTRATOR);
        });
    }

    /**
     * Register the configurations for Laravel Fortify.
     */
    protected function fortify(): void
    {
        Nova::fortify()
            ->features([
                Features::emailVerification(),
            ])
            ->register();
    }

    /**
     * Get the dashboards that should be listed in the Nova sidebar.
     *
     * @return list<Dashboard>
     */
    protected function dashboards(): array
    {
        return [];
    }

    /**
     * Get the tools that should be listed in the Nova sidebar.
     *
     * @return list<Tool>
     */
    public function tools(): array
    {
        return [];
    }
}
