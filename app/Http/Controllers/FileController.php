<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Requests\FileRenameRequest;
use App\Http\Requests\FileStoreRequest;
use App\Http\Resources\FileResource;
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
        // Получаем расширение файла из старого имени
        $extension = pathinfo($file->name, PATHINFO_EXTENSION);

// Формируем новое имя файла с тем же расширением
        $newFileName = $validatedData['name'] . '.' . $extension;

// Формируем новый путь к файлу с новым именем
        $newFilePath = 'uploads/' . $file->user_id . '/' . $newFileName;

// Переименовываем файл
        Storage::move($oldFilePath, $newFilePath);

// Обновляем имя файла в базе данных
        $file->name = $newFileName;
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
    public function owned(Request $request){

        $files = File::where('user_id', $request->user()->id)->get();
        return response(FileResource::collection($files));
    }
    //Функция просмотра файлов, к которым имеет доступ пользователь
    public function allowed(Request $request){

        $allowedRights = Right::where('user_id', $request->user()->id)->get();
        $allowedRightsIds = [];
        if (!$allowedRights) throw new ApiException(404, 'С ВАМИ НЕ ДЕЛИЛИСЬ ФАЙЛАМИ');

        foreach ($allowedRights as $right){
            $allowedRightsIds[] = $right->file_id;

        }



        $allowedFiles = File::whereIn('id', $allowedRightsIds)->get();


        return response(FileResource::collection($allowedFiles));

    }
}
