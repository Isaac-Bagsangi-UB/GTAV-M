<?php

namespace App\Http\Resources;

use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Registration */
class RegistrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_code' => $this->reference_code,
            // The owner needs the token to render their QR code in a mobile app.
            'qr_token' => $this->qr_token,
            'status' => $this->status,
            'amount_paid' => (float) $this->amount_paid,
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'registered_at' => $this->created_at?->toIso8601String(),
            'event' => new EventResource($this->whenLoaded('event')),
        ];
    }
}
