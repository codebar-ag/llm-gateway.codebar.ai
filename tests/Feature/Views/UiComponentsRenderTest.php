<?php

use Illuminate\Support\Facades\Blade;

it('renders the badge component with variant styling', function () {
    $html = Blade::render('<x-ui.badge variant="success">Active</x-ui.badge>');

    expect($html)->toContain('Active')
        ->toContain('rounded-full')
        ->toContain('text-green-700');
})->group('frontend', 'ui-components');

it('renders breadcrumbs with links and a current page', function () {
    $html = Blade::render('<x-ui.breadcrumbs :items="$items" />', [
        'items' => [
            ['label' => 'Home', 'href' => '/'],
            ['label' => 'Settings', 'href' => '/settings'],
            ['label' => 'Profile'],
        ],
    ]);

    expect($html)->toContain('aria-label="Breadcrumb"')
        ->toContain('href="/settings"')
        ->toContain('aria-current="page"')
        ->toContain('Home')
        ->toContain('Profile');
})->group('frontend', 'ui-components');

it('renders the empty-state component with title, description, and action slot', function () {
    $html = Blade::render(
        '<x-ui.empty-state title="No results" description="Try adjusting the filters.">Create one</x-ui.empty-state>'
    );

    expect($html)->toContain('No results')
        ->toContain('Try adjusting the filters.')
        ->toContain('Create one')
        ->toContain('border-dashed');
})->group('frontend', 'ui-components');

it('renders the field component with label and help text', function () {
    $html = (string) $this->withViewErrors([])->blade(
        '<x-ui.field label="Email" name="email" help="We never share it."><input id="email" /></x-ui.field>'
    );

    expect($html)->toContain('Email')
        ->toContain('We never share it.')
        ->toContain('<input id="email" />');
})->group('frontend', 'ui-components');

it('renders the field component with an explicit error instead of help text', function () {
    $html = Blade::render(
        '<x-ui.field label="Email" name="email" help="We never share it." error="Required."><input id="email" /></x-ui.field>'
    );

    expect($html)->toContain('Required.')
        ->toContain('id="email-error"')
        ->not->toContain('We never share it.');
})->group('frontend', 'ui-components');

it('renders the select component with placeholder and options', function () {
    $html = (string) $this->withViewErrors([])->blade(
        '<x-ui.select label="Language" name="locale" placeholder="Pick one"><option value="de_CH">Deutsch</option></x-ui.select>'
    );

    expect($html)->toContain('name="locale"')
        ->toContain('id="locale"')
        ->toContain('Pick one')
        ->toContain('Deutsch')
        ->toContain('Language');
})->group('frontend', 'ui-components');

it('renders the textarea component with value and rows', function () {
    $html = (string) $this->withViewErrors([])->blade(
        '<x-ui.textarea label="Notes" name="notes" :rows="6" value="Hello there" />'
    );

    expect($html)->toContain('name="notes"')
        ->toContain('rows="6"')
        ->toContain('Hello there')
        ->toContain('Notes');
})->group('frontend', 'ui-components');

it('renders the table component with head, rows, and cells', function () {
    $html = Blade::render(<<<'BLADE'
        <x-ui.table>
            <x-slot:head>
                <x-ui.table.heading>Name</x-ui.table.heading>
            </x-slot:head>
            <x-ui.table.row>
                <x-ui.table.cell>Jane</x-ui.table.cell>
            </x-ui.table.row>
        </x-ui.table>
        BLADE);

    expect($html)->toContain('<thead')
        ->toContain('<th')
        ->toContain('Name')
        ->toContain('<td')
        ->toContain('Jane');
})->group('frontend', 'ui-components');
