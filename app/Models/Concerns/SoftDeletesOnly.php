<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * Soft-delete domain records. Hard deletes are not permitted in the admin CRM.
 */
trait SoftDeletesOnly
{
    use SoftDeletes;

    /**
     * @return never
     */
    public function forceDelete()
    {
        throw new LogicException('Hard deletes are not allowed. Use soft delete instead.');
    }
}
