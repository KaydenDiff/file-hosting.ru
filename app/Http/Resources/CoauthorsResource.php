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
        return [
            'fullname' => $this->user()->first_name. ' ' . $this->user()->last_name,
            'email' => $this->user()->email,
            'type'=>'co-author',
  'code'=> 200


            // Другие поля вашей модели, которые вы хотите включить
        ];
    }
}
