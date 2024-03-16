<?php

namespace App\Http\Resources;

use App\Models\Right;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */

    public function toArray(Request $request): array
    {
        $rights = Right::where('file_id', $this->id)->get();
        $author = User::find($this->user_id);
        $data[]=[
            'fullname' => $author->first_name. ' ' . $author->last_name,
            'email' => $author->email,
            'type' => 'author',
            'code' => 200,
        ];
        $data2 = array(...CoauthorsResource::collection($rights));


        $accesses = array_merge($data , $data2);
        return[
            'file_id' => $this->file_id ,
            'name' => $this->name ,
            'code' => 200 ,
            'url' =>  route('files.get', ['file_id' => $this->file_id]) ,
            'accesses' => $accesses
        ];
    }

}
