<?php

beforeEach(function () {
    $this->withoutVite();
});

it('opens the app mockup on the login screen', function () {
    $this->get(route('mockups.apps'))
        ->assertOk()
        ->assertSee('Log in')
        ->assertSee('Create an account')
        ->assertSee('Keep me signed in on this device');
});

it('renders each signed-in function', function (string $screen, string $text) {
    $this->get(route('mockups.apps', ['screen' => $screen]))
        ->assertOk()
        ->assertSee($text);
})->with([
    'home' => ['home', 'Your shooting'],
    'matches' => ['matches', 'Matches'],
    'calendar' => ['calendar', 'Calendar'],
    'map' => ['map', 'Map'],
    'my calendar' => ['my-calendar', 'My calendar'],
    'log' => ['log', 'Attendance record'],
    'notifications' => ['notifications', 'Email preferences'],
    'upgrade' => ['upgrade', 'Go Pro'],
    'you' => ['you', 'Sign out'],
    'find' => ['find', 'Clubs & series'],
    'register' => ['register', 'Create your account'],
]);

it('sends an unknown app screen back to login', function () {
    $this->get(route('mockups.apps', ['screen' => 'not-a-screen']))
        ->assertOk()
        ->assertSee('Log in')
        ->assertDontSee('Your shooting');
});
