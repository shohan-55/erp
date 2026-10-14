<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AttendanceLocation
{
    /**
     * Return validated browser-reported coordinates only when location consent
     * and an explicit workspace policy are both present. Browser GPS is NOT
     * proof of physical attendance; the office may require manual review.
     */
    public static function fromRequest(Request $request, object $workspace): ?array
    {
        $names = ['location_latitude', 'location_longitude', 'location_accuracy', 'location_consent'];

        if (! $request->hasAny($names)) {
            return null;
        }

        if (! $workspace->attendance_location_enabled) {
            throw ValidationException::withMessages([
                'location_consent' => 'Your workspace has not enabled attendance location collection.',
            ]);
        }

        $values = $request->validate([
            'location_latitude' => ['required', 'numeric', 'between:-90,90'],
            'location_longitude' => ['required', 'numeric', 'between:-180,180'],
            'location_accuracy' => ['required', 'numeric', 'between:0,50000'],
            'location_consent' => ['required', 'accepted'],
        ]);

        return [
            'latitude' => (string) $values['location_latitude'],
            'longitude' => (string) $values['location_longitude'],
            'accuracy' => $values['location_accuracy'],
        ];
    }

    public static function record(object $actor, int $sessionId, string $event, ?array $location): bool
    {
        if ($location === null) {
            return false;
        }

        DB::table('attendance_locations')->insert([
            'attendance_session_id' => $sessionId,
            'company_id' => $actor->company_id,
            'workspace_id' => $actor->workspace_id,
            'membership_id' => $actor->id,
            'event' => $event,
            'latitude_encrypted' => Crypt::encryptString($location['latitude']),
            'longitude_encrypted' => Crypt::encryptString($location['longitude']),
            'accuracy_meters' => $location['accuracy'],
            'captured_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }
}
