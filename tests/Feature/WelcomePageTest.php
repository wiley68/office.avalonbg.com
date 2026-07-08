<?php

use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\get;

test('welcome page shares contact email', function () {
    $contactEmail = 'home@avalonbg.com';

    config()->set('app.contact_email', $contactEmail);

    get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('email', $contactEmail));
});
