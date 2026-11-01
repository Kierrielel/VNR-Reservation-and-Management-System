<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;

class ReservationLockService
{
    /**
     * @param  list<string>  $resources
     */
    public function run(array $resources, Closure $callback): mixed
    {
        $resources = array_values(array_unique($resources));
        sort($resources, SORT_STRING);

        return DB::transaction(function () use ($resources, $callback) {
            foreach ($resources as $resource) {
                // An upsert locks even an existing row. Unlike SELECT FOR UPDATE,
                // this also obtains a write lock on SQLite before any availability reads.
                DB::table('reservation_locks')->upsert(
                    [['resource' => $resource]],
                    ['resource'],
                    ['resource'],
                );
            }

            return $callback();
        }, attempts: 5);
    }
}
