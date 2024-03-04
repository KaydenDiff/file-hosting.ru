<?php

namespace App\Http\Controllers;

use App\Http\Requests\FileRenameRequest;
use App\Http\Requests\FileStoreRequest;
use App\Models\File;
use App\Models\Right;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\Validator;


class FileController extends Controller
{

public function upload(Request $request)
{
    // Производим валидацию загружаемых файлов
    $validator = Validator::make($request->all(), [
        'files.*' => 'required|file|mimes:doc,pdf,docx,zip,jpeg,jpg,png,exe|max:2048',
    ]);
    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Ошибка валидаци',
            'errors' => $validator->errors(),
        ])->setStatusCode(422);
    }

    // Получаем массив файлов для загрузки
    $files = $request->file('files');
$responses = [];
    // Обрабатываем каждый файл для загрузки
    foreach ($files as $file) {
        // Проверяем, является ли файл действительным
        if ($file->isValid()) {
            // Генерируем уникальное имя файла на основе текущего времени и его оригинального имени
            $fileName = time() . '_' . $file->getClientOriginalName();

            // Определяем путь для сохранения файла
            $filePath = 'uploads/'. Auth::id();

            // Сохраняем файл
            $file->storeAs($filePath, $fileName);

            // Создаем запись о файле в БД
            $createNote = File::create([
                'user_id' => Auth::id(),
                'name' => $fileName,
                'extension' => $file->extension(), // Добавьте расширение файла
                'path' => $filePath, // Добавьте путь к файлу
                'file_id' => Str::random(10), // Уникальный идентификатор файла, если используется
            ]);
            $url = route('files.get', ['file_id' => $createNote->file_id]);
        }
        $responses[] = [
            "success"=> true,
     "code"=> 200,
     "message"=> "Success",
     "name"=>  $createNote->name,
     "url"=> $url,
     "file_id"=>$createNote->file_id

        ];
    }


    return response()->json($responses);


}

    public function edit(Request $request, $file_id)
    {
        // Проверка аутентификации пользователя
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized user',
            ])->setStatusCode(401);
        }

        // Проверка существования файла
        $file = File::where('file_id', $file_id)->first();
        if (!$file) {
            return response()->json([
                'success' => false,
                'message' => 'File not found',
            ])->setStatusCode(404);
        }

        // Проверка владельца файла
        if ($file->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to edit this file',
            ])->setStatusCode(403);
        }

        // Валидация параметра name
        $validatedData = $request->validate([
            'name' => 'required|unique:files,name',//todo переделать на request
        ]);

        // Получаем старый путь к файлу
        $oldFilePath = 'uploads/' . $file->user_id . '/' . $file->name;

        // Формируем новый путь к файлу
        $newFilePath = 'uploads/' . $file->user_id . '/' . $validatedData['name'];

        // Переименовываем файл
        Storage::move($oldFilePath, $newFilePath);

        // Обновляем имя файла в базе данных
        $file->name = $validatedData['name'];
        $file->save();

        // Возвращаем успешный ответ
        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => 'Renamed',
        ])->setStatusCode(200);
    }

    public function destroy($file_id)
    {
        // Найдем файл по его идентификатору
        $file = File::where('file_id', $file_id)->firstOrFail();

        // Удаляем файл из хранилища
        Storage::delete($file->path);

        // Удаляем запись о файле из базы данных
        $file->delete();

        // Возвращаем ответ об успешном удалении
        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => 'File deleted',
        ])->setStatusCode(200);
    }
    public function download($file_id){
        // Найдем файл по его идентификатору
        $file = File::where('file_id', $file_id)->firstOrFail();

        // Построим путь к файлу в хранилище
        $filePath = 'uploads/' . $file->user_id . '/' . $file->name;

        // Проверяем, существует ли файл
        if (!Storage::exists($filePath)) {
            abort(404);
        }

        // Отправляем файл для скачивания
        return response()->download(storage_path('app/' . $filePath), $file->name);
    }
    public function owned(Request $request)
    {
        // Найти все файлы, которые принадлежат текущему пользователю
        $files = File::where('user_id', $request->user()->id)->get();

        // Сформировать ответ с информацией о найденных файлах
        $response = [];
        foreach ($files as $file) {
            // Получить всех пользователей, имеющих доступ к этому файлу
            $accesses = Right::where('file_id', $file->id)->with('user')->get();

            // Подготовка данных о доступах
            $accesses_data = [];
            foreach ($accesses as $access) {
                $accesses_data[] = [
                    'fullname' => $access->user->first_name . ' ' . $access->user->last_name,
                    'email' => $access->user->email,
                    'type' => $access->user_id == $file->user_id ? 'author' : 'co-author',
                ];
            }

            $response[] = [
                'file_id' => $file->file_id,
                'name' => $file->name,
                'code' => 200,
                'url' => url('/file/' . $file->file_id),
                'accesses' => $accesses_data,
            ];
        }

        // Вернуть ответ с информацией о файлах пользователя
        return response()->json($response);
    }

    //Функция просмотра файлов, к которым имеет доступ пользователь
    public function allowed(Request $request)
    {

        // Получить все записи о доступе пользователя
        $rights = Right::where('user_id', $request->user()->id)
            ->whereHas('file') // Отфильтровать только те записи, где есть связанный файл
            ->with('file')
            ->get();

        // Сформировать ответ с информацией о файлах, к которым пользователь имеет доступ
        $response = [];
        foreach ($rights as $right) {
            $file = $right->file;
            $response[] = [
                'file_id' => $file->file_id,
                'name' => $file->name,
                'code' => 200,
                'url' => url('/file/' . $file->file_id),
            ];
        }

        // Вернуть ответ с информацией о файлах, к которым пользователь имеет доступ
        return response()->json($response);
    }

}
