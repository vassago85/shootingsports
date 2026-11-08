<?php

namespace App\Models\Concerns;

use App\Models\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Blocks creates and edits unless the row's club has the subscription
 * feature named by {@see clubSubscriptionFeature()}.
 *
 * @mixin Model
 */
trait GuardsClubSubscription
{
    protected static function bootGuardsClubSubscription(): void
    {
        static::saving(function (Model $model): void {
            $dirty = array_keys($model->getDirty());
            $onlyDeleting = $dirty !== []
                && array_diff($dirty, ['deleted_at', 'updated_at']) === [];

            if ($onlyDeleting) {
                return;
            }

            if (! method_exists($model, 'clubSubscriptionFeature')) {
                return;
            }

            $organisation = $model->organisation;
            if (! $organisation instanceof Organisation && filled($model->getAttribute('organisation_id'))) {
                $organisation = Organisation::query()->find($model->getAttribute('organisation_id'));
            }

            if ($organisation instanceof Organisation && $organisation->allowsClubFeature($model->clubSubscriptionFeature())) {
                return;
            }

            throw ValidationException::withMessages([
                'organisation_id' => 'That tool is part of the club subscription.',
            ]);
        });
    }
}
