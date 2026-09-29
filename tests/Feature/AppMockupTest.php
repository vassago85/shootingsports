<?php

beforeEach(function () {
    $this->withoutVite();
});

it('opens the app mockup on the Home screen by default', function () {
    $this->get(route('mockups.apps'))
        ->assertOk()
        ->assertSee('Recommended next')
        ->assertSee('Following activity');
});

it('renders each companion-app screen', function (string $screen, string $text) {
    $this->get(route('mockups.apps', ['screen' => $screen]))
        ->assertOk()
        ->assertSee($text);
})->with([
    'onboarding' => ['onboarding', 'Follow sports'],
    'home' => ['home', 'Recommended next'],
    'events' => ['events', 'Filters'],
    'detail' => ['detail', 'Event detail'],
    'find' => ['find', 'Map and list'],
    'sports' => ['sports', 'Discover disciplines'],
    'following' => ['following', 'Your weekend'],
    'activity' => ['activity', 'Notification settings'],
    'profile' => ['profile', 'Account state'],
    'organiser' => ['organiser', 'Submit event'],
]);

it('sends an unknown screen back to Home', function () {
    $this->get(route('mockups.apps', ['screen' => 'not-a-screen']))
        ->assertOk()
        ->assertSee('Recommended next')
        ->assertDontSee('Submit event');
});
