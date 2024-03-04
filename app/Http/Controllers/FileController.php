<?php

namespace App\Http\Controllers;

use App\Http\Requests\FileStoreRequest;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;


class FileController extends Controller
{

public function upload(Request $request)
{
    // Проведем валидацию файлов
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

    // Получаем массив файлов
    $files = $request->file('files');
$responses = [];
    // Перебираем каждый файл для загрузки
    foreach ($files as $file) {
        // Проверяем валидность файла
        if ($file->isValid()) {
            // Генерируем уникальное имя файла
            $fileName = time() . '_' . $file->getClientOriginalName();

            // Путь сохранения файла
            $filePath = 'uploads/'. Auth::id();

            // Сохраняем файл
            $file->storeAs($filePath, $fileName);

            // Создаем запись о файле в БД
            $kakashki = File::create([
                'user_id' => Auth::id(),
                'name' => $fileName,
                'extension' => $file->extension(), // Добавьте расширение файла
                'path' => $filePath, // Добавьте путь к файлу
                'file_id' => Str::random(10), // Уникальный идентификатор файла, если используется
            ]);
            $url = route('files.get', ['file_id' => $kakashki->file_id]);
        }
        $responses[] = [
            "success"=> true,
     "code"=> 200,
     "message"=> "Success",
     "name"=>  $kakashki->name,
     "url"=> $url,
     "file_id"=>$kakashki->file_id

        ];
    }


    return response()->json($responses);


}
    public function update(Request $request, $file_id)
    {
        // Находим файл по его идентификатору
        $file = File::find($file_id);

        // Проверяем, существует ли файл
        if (!$file) {
            return response()->json([
                'success' => false,
                'message' => 'File not found'
            ], 404);
        }

        // Проверяем, является ли пользователь владельцем файла
        if ($file->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied'
            ], 403);
        }

        // Валидируем данные запроса для обновления имени файла
        $validatedData = $request->validate([
            'name' => 'required|unique:files,name|max:255'
        ]);

        // Обновляем имя файла
        $file->name = $validatedData['name'];
        $file->save();

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => 'Renamed'
        ]);
    }

}
