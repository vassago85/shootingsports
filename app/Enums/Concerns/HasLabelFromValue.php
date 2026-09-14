<?php

namespace App\Enums\Concerns;

trait HasLabelFromValue
{
    public function getLabel(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}
