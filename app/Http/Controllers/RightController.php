<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmailCheckRequest;
use App\Http\Resources\CoauthorsResource;
use App\Models\File;
use App\Models\Right;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RightController extends Controller
{
    public function add(Request $request, $file_id)
    {

        // Проверка аутентификации пользователя
        $file = File::where("file_id",$file_id )->first();

        // Проверяем, является ли текущий пользователь владельцем файла
        if ($file->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied. You are not the owner of this file.'
            ], 403);
        }

        // Валидация данных запроса


        // Находим пользователя по email
        $user = User::where('email', $request->email)->first();

        $right =Right::create(['file_id'=>$file->id,
                'user_id'=>$user->id
                ]
        );
return CoauthorsResource::collection($file->rights());
    }
}
