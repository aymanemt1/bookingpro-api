<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'duration_minutes' => $this->duration_minutes,
            'duration_label' => $this->duration_minutes >= 60
                ? round($this->duration_minutes / 60, 1) . 'h'
                : $this->duration_minutes . ' min',
            'price' => $this->price,
            'image_url' => $this->image_url,
            'is_active' => $this->is_active,
            'bookings_count' => $this->whenCounted('bookings'),
        ];
    }
}
