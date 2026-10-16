<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OrganizationScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        // Scope applies only when an authenticated User (tenant user) is present.
        // It does not apply for:
        // 1. Unauthenticated requests / console / queues (unless authenticated)
        // 2. SuperAdmin users (guard 'platform')
        // 3. Explicitly bypassed with withoutGlobalScope(OrganizationScope::class)
        if ($user instanceof User) {
            $builder->where($model->qualifyColumn('organization_id'), $user->organization_id);
        }
    }
}
