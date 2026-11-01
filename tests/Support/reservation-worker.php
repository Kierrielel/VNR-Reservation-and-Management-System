<?php

use App\Http\Controllers\AdminRoomsController;
use App\Http\Controllers\ReservationController;
use App\Models\Cottage;
use App\Services\CheckInService;
use App\Services\CottageManagementService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
Auth::onceUsingId((int) $argv[1]);
$payload = json_decode($argv[2], true, flags: JSON_THROW_ON_ERROR);
fwrite(STDOUT, "READY\n");
fgets(STDIN);

try {
    if (($payload['operation'] ?? null) === 'check-in') {
        $booking = $app->make(CheckInService::class)->confirm($payload['token'], Auth::user());
        $response = response()->json(['checked_in_at' => $booking->checked_in_at->toIso8601String()]);
    } elseif (($payload['operation'] ?? null) === 'delete-cottage') {
        $response = $app->make(AdminRoomsController::class)->destroy(
            Cottage::findOrFail($payload['cottage_id']),
            $app->make(CottageManagementService::class),
        );
    } else {
        $response = $app->make(ReservationController::class)->store(Request::create('/reservations', 'POST', $payload));
    }
    echo json_encode(['status' => $response->getStatusCode()], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (ValidationException $exception) {
    echo json_encode(['status' => 422, 'errors' => array_keys($exception->errors())], JSON_THROW_ON_ERROR).PHP_EOL;
}
