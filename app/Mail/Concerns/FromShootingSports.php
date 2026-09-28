<?php

namespace App\Mail\Concerns;

use Illuminate\Mail\Mailables\Address;

trait FromShootingSports
{
    protected function shootingSportsFrom(): Address
    {
        return new Address(
            (string) config('mail.from.address'),
            (string) (config('mail.from.name') ?: 'Shooting Sports'),
        );
    }
}
