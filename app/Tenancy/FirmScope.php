<?php

declare(strict_types=1);

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** @implements Scope<Model> */
final class FirmScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $firmId = app(CurrentFirm::class)->id();

        if ($firmId !== null) {
            $builder->where($model->qualifyColumn('firm_id'), $firmId);
        }
    }
}
