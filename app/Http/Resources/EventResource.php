<?php

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Event */
class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'description' => $this->description,
            'venue' => $this->venue,
            'city' => $this->city,
            'chapter' => $this->whenLoaded('chapter', fn () => [
                'id' => $this->chapter->id,
                'name' => $this->chapter->name,
                'code' => $this->chapter->code,
            ]),
            'parent_id' => $this->parent_id,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'registration_opens_at' => $this->registration_opens_at->toIso8601String(),
            'registration_closes_at' => $this->registration_closes_at->toIso8601String(),
            'ticket_price' => (float) $this->ticket_price,
            'capacity' => $this->capacity,
            'confirmed' => $this->confirmedCount(),
            'seats_left' => $this->seatsLeft(),
            'registration_state' => $this->registrationState(),
            'sub_events' => EventResource::collection($this->whenLoaded('children')),
        ];
    }
}
