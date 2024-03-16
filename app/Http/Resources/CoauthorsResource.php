<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoauthorsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->user()->first(); // Получить первый экземпляр пользователя из отношения BelongsTo
        if ($user) {
            return [
                'fullname' => $user->first_name . ' ' . $user->last_name,
                'email' => $user->email,
                'type' => 'co-author',
                'code' => 200
            ];
        } else {
            // Логика для случая, когда пользователя не найдено
            return [];
        }
    }
}
