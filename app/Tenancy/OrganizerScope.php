<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OrganizerScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (Tenancy::isAcrossOrganizers()) {
            return;
        }

        $id = Tenancy::id();
        if ($id === null) {
            // No organizer: return nothing rather than everything.
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('organizer_id'), $id);
    }
}
