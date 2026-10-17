<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Models\Scopes\OrganizationScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait BelongsToOrganization
 *
 * Ensures multi-tenant isolation by:
 * 1. Adding the OrganizationScope global scope so queries automatically filter by organization_id
 *    of the logged-in User.
 * 2. Automatically setting organization_id on model creation if not already specified.
 *
 * Explicitly bypass the scope in super admin areas, artisan commands, or background jobs with:
 *     Model::withoutGlobalScope(OrganizationScope::class)->get();
 *     or:
 *     Model::withoutOrganizationScope()->get();
 */
trait BelongsToOrganization
{
    /**
     * Boot the BelongsToOrganization trait for a model.
     */
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function (Model $model) {
            if (empty($model->getAttribute('organization_id')) && auth()->check() && auth()->user() instanceof User) {
                $model->setAttribute('organization_id', auth()->user()->organization_id);
            }
        });
    }

    /**
     * Define the relationship to the organization.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Shorthand to bypass organization scope explicitly.
     */
    public static function withoutOrganizationScope(): Builder
    {
        return static::withoutGlobalScope(OrganizationScope::class);
    }
}
