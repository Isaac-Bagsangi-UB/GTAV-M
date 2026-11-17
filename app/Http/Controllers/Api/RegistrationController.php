<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\RegistrationException;
use App\Http\Controllers\Controller;
use App\Http\Resources\RegistrationResource;
use App\Models\Event;
use App\Models\Registration;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RegistrationController extends Controller
{
    /** GET /api/v1/registrations : the signed-in attendee's registrations. */
    public function index(Request $request): AnonymousResourceCollection
    {
        return RegistrationResource::collection(
            $request->user()->registrations()->with('event.chapter')->latest()->get()
        );
    }

    /** POST /api/v1/events/{event}/registrations */
    public function store(Request $request, Event $event, RegistrationService $service): JsonResponse
    {
        try {
            $registration = $service->register($request->user(), $event);
        } catch (RegistrationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return (new RegistrationResource($registration->load('event.chapter')))
            ->response()
            ->setStatusCode(201);
    }

    /** DELETE /api/v1/registrations/{registration} : cancel. */
    public function destroy(Request $request, Registration $registration, RegistrationService $service): JsonResponse
    {
        abort_unless($registration->user_id === $request->user()->id, 403);

        try {
            $service->cancel($registration);
        } catch (RegistrationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Registration cancelled.']);
    }
}
