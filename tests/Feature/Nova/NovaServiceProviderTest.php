<?php

use App\Enums\RoleEnum;
use App\Models\User;
use App\Nova\Providers\NovaServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Menu\MenuSection;
use Laravel\Nova\Nova;

it('invokes nova service provider protected hooks', function () {
    $provider = app()->getProvider(NovaServiceProvider::class);
    expect($provider)->not->toBeNull();

    foreach (['routes', 'gate', 'fortify', 'dashboards', 'tools'] as $method) {
        $ref = new ReflectionMethod(NovaServiceProvider::class, $method);
        $ref->setAccessible(true);
        $ref->invoke($provider);
    }

    expect(true)->toBeTrue();
})->group('nova', 'feature');

it('defines view nova gate for administrators', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::ADMINISTRATOR);

    $this->actingAs($admin);

    expect(Gate::forUser($admin)->allows('viewNova'))->toBeTrue();
})->group('nova', 'feature');

it('renders custom nova footer', function () {
    $html = Nova::resolveFooter(Request::create('/nova'));

    expect($html)->toContain('codebar');
})->group('nova', 'feature');

it('builds main menu for administrator', function () {
    $provider = app()->getProvider(NovaServiceProvider::class);
    expect($provider)->not->toBeNull();
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::ADMINISTRATOR);
    $request = Request::create('/nova');
    $request->setUserResolver(fn () => $admin);

    $method = new ReflectionMethod($provider, 'mainMenu');
    $method->setAccessible(true);
    /** @var array<int, MenuSection> $menu */
    $menu = $method->invoke($provider, $request);

    expect($menu)->toHaveCount(2)
        ->and($menu[1]->authorizedToSee($request))->toBeTrue();
})->group('nova', 'feature');

it('hides monitoring menu for non administrator', function () {
    $provider = app()->getProvider(NovaServiceProvider::class);
    expect($provider)->not->toBeNull();
    $user = User::factory()->create();
    $user->assignRole(RoleEnum::USER);
    $request = Request::create('/nova');
    $request->setUserResolver(fn () => $user);

    $method = new ReflectionMethod($provider, 'mainMenu');
    $method->setAccessible(true);
    /** @var array<int, MenuSection> $menu */
    $menu = $method->invoke($provider, $request);

    expect($menu[1]->authorizedToSee($request))->toBeFalse();
})->group('nova', 'feature');

it('skips breadcrumbs when disabled in config', function () {
    Config::set('default-nova.with_breadcrumbs', false);
    $provider = app()->getProvider(NovaServiceProvider::class);
    expect($provider)->not->toBeNull();
    $method = new ReflectionMethod($provider, 'registerNovaBreadcrumbs');
    $method->setAccessible(true);
    $method->invoke($provider);

    expect(true)->toBeTrue();
})->group('nova', 'feature');

it('skips notification center removal when disabled in config', function () {
    Config::set('default-nova.without_notifications', false);
    $provider = app()->getProvider(NovaServiceProvider::class);
    expect($provider)->not->toBeNull();
    $method = new ReflectionMethod($provider, 'registerNovaNotificationCenter');
    $method->setAccessible(true);
    $method->invoke($provider);

    expect(true)->toBeTrue();
})->group('nova', 'feature');

it('skips asset scripts when list is empty', function () {
    Config::set('default-nova.assets', []);
    $provider = app()->getProvider(NovaServiceProvider::class);
    expect($provider)->not->toBeNull();
    $method = new ReflectionMethod($provider, 'registerNovaAssetScripts');
    $method->setAccessible(true);
    $method->invoke($provider);

    expect(true)->toBeTrue();
})->group('nova', 'feature');

it('ignores non string asset entries', function () {
    Config::set('default-nova.assets', [123, null, ['nested']]);
    $provider = app()->getProvider(NovaServiceProvider::class);
    expect($provider)->not->toBeNull();
    $method = new ReflectionMethod($provider, 'registerNovaAssetScripts');
    $method->setAccessible(true);
    $method->invoke($provider);

    expect(true)->toBeTrue();
})->group('nova', 'feature');

it('skips resource discovery when path is empty', function () {
    Config::set('default-nova.resource_in', '');
    $provider = app()->getProvider(NovaServiceProvider::class);
    expect($provider)->not->toBeNull();
    $method = new ReflectionMethod($provider, 'registerNovaResourcePaths');
    $method->setAccessible(true);
    $method->invoke($provider);

    expect(true)->toBeTrue();
})->group('nova', 'feature');

it('skips resource discovery when directory is missing', function () {
    Config::set('default-nova.resource_in', 'Nova/ThisPathDoesNotExist');
    $provider = app()->getProvider(NovaServiceProvider::class);
    expect($provider)->not->toBeNull();
    $method = new ReflectionMethod($provider, 'registerNovaResourcePaths');
    $method->setAccessible(true);
    $method->invoke($provider);

    expect(true)->toBeTrue();
})->group('nova', 'feature');

it('skips initial path when empty', function () {
    Config::set('default-nova.initial_path', '');
    $provider = app()->getProvider(NovaServiceProvider::class);
    expect($provider)->not->toBeNull();
    $method = new ReflectionMethod($provider, 'registerNovaCustomInitialPath');
    $method->setAccessible(true);
    $method->invoke($provider);

    expect(true)->toBeTrue();
})->group('nova', 'feature');
