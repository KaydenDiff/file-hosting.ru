<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmailCheckRequest;
use App\Http\Requests\RightRequest;
use App\Http\Resources\CoauthorsResource;
use App\Models\File;
use App\Models\Right;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RightController extends Controller
{
    public function add($file_id, RightRequest $request)
    {
        // Найдем файл по его идентификатору
        $file = File::where('file_id', $file_id)->firstOrFail();

        // Проверяем, имеет ли текущий пользователь право на изменение доступа к файлу
        if ($file->user_id != $request->user()->id) {
            return response()->json([
                'success' => false,
                'code' => 403,
                'message' => 'Forbidden: You do not have permission to modify access rights for this file',
            ])->setStatusCode(403);
        }

        //Ищем пользователя по его email
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'code' => 400,
                'message' => 'User not found with this email',
            ])->setStatusCode(400);
        }

        // Создаем запись о праве доступа в базе данных
        $right = new Right();
        $right->file_id = $file->id;
        $right->user_id = $user->id;
        $right->save();

        // Создаем массив данных для второго пользователя
        $user_data = [
            'fullname' => $user->first_name . ' ' . $user->last_name,
            'email' => $user->email,
            'type' => 'co-author',
            'code' => 200,
        ];

        // Создаем массив данных для текущего пользователя (автора)
        $author_data = [
            'fullname' => $request->user()->first_name . ' ' . $request->user()->last_name,
            'email' => $request->user()->email,
            'type' => 'author',
            'code' => 200,
        ];

        // Возвращаем ответ об успешном добавлении прав доступа с обоими пользователями
        return response()->json([$author_data, $user_data])->setStatusCode(200);
    }

    public function destroy($file_id, RightRequest $request)
    {
        // Найдем файл по его идентификатору
        $file = File::where('file_id', $file_id)->firstOrFail();

        // Проверяем, авторизован ли пользователь и является ли он создателем файла
        if ($file->user_id != $request->user()->id) {
            return response()->json([
                'success' => false,
                'code' => 403,
                'message' => 'Forbidden: You do not have permission to modify access rights for this file',
            ])->setStatusCode(403);
        }

        // Проверяем, существует ли пользователь, которого пытаются удалить из списка соавторов
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'code' => 404,
                'message' => 'User not found with this email',
            ])->setStatusCode(404);
        }

        // Проверяем, пытается ли пользователь удалить самого себя
        if ($user->id == $request->user()->id) {
            return response()->json([
                'success' => false,
                'code' => 403,
                'message' => 'Forbidden: You cannot remove yourself from access rights',
            ])->setStatusCode(403);
        }

        // Проверяем, имеет ли пользователь право удалить пользователя из списка соавторов
        $right = Right::where('file_id', $file->id)->where('user_id', $user->id)->first();
        if (!$right) {
            return response()->json([
                'success' => false,
                'code' => 404,
                'message' => 'User not found in access rights for this file',
            ])->setStatusCode(404);
        }

        // Удаляем запись о праве доступа к файлу для указанного пользователя
        $right->delete();

        // Получаем всех пользователей, имеющих доступ к файлу
        $users_with_access = Right::where('file_id', $file->id)->with('user')->get();


        // Создаем массив данных для текущего пользователя (автора)
        $author_data = [
            'fullname' => $request->user()->first_name . ' ' . $request->user()->last_name,
            'email' => $request->user()->email,
            'type' => 'author',
            'code' => 200,
        ];

        // Формируем ответ с информацией обо всех пользователях, имеющих доступ к файлу
        $response = [];
        foreach ($users_with_access as $right) {
            $response[] = [
                'fullname' => $right->user->first_name . ' ' . $right->user->last_name,
                'email' => $right->user->email,
                'code' => 200,
                'type' => $right->user_id == $file->user_id ? 'author' : 'co-author',
            ];
        }
        // В случае если соавторов больше не будет, то будет в ответе только автор
        if (count($response) == 0) {
            $author_data = [
                'fullname' => $request->user()->first_name . ' ' . $request->user()->last_name,
                'email' => $request->user()->email,
                'code' => 200,
                'type' => 'author',
            ];
            $response[] = $author_data;
        }

        // Возвращаем ответ об успешном удалении прав доступа и информацией обо всех пользователях, имеющих доступ к файлу
        return response()->json($response)->setStatusCode(200);
    }
}
