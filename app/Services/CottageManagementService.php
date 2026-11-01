<?php

namespace App\Services;

use App\Models\Cottage;
use Illuminate\Validation\ValidationException;

class CottageManagementService
{
    public function __construct(private readonly ReservationLockService $locks) {}

    /** @param array<string, mixed> $attributes */
    public function update(Cottage $cottage, array $attributes): void
    {
        $this->locks->run(['cottage:'.$cottage->id], function () use ($cottage, $attributes): void {
            Cottage::whereKey($cottage->id)->lockForUpdate()->firstOrFail()->update($attributes);
        });
    }

    public function delete(Cottage $cottage): void
    {
        $this->locks->run(['cottage:'.$cottage->id], function () use ($cottage): void {
            $cottage = Cottage::whereKey($cottage->id)->lockForUpdate()->firstOrFail();

            if ($cottage->bookings()->exists()) {
                throw ValidationException::withMessages([
                    'cottage' => 'This room or cottage has booking history and cannot be deleted. Mark it unavailable instead.',
                ]);
            }

            $cottage->delete();
        });
    }
}
