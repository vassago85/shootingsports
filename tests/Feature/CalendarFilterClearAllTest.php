<?php

use App\Livewire\CalendarFilter;
use Livewire\Livewire;

it('clearAll resets the kind (Training) filter', function () {
    // Regression: kind used to survive Clear all because it was not
    // in the reset list, so shooters that toggled "Training" from
    // an earlier revision could not get back to All events without
    // an extra click on a chip that was no longer on the toolbar.
    Livewire::test(CalendarFilter::class)
        ->set('kind', 'training')
        ->assertSet('kind', 'training')
        ->call('clearAll')
        ->assertSet('kind', null);
});
