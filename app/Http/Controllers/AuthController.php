<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function authorization(Request $request)
    {
        // Проверка валидности запроса
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Извлечение данных из JSON запроса
        $data = $request->json()->all();

        // Аутентификация пользователя
        if (Auth::attempt(['email' => $data['email'], 'password' => $data['password']])) {
            // Аутентификация успешна
            $user = Auth::user();
            return response()->json(['message' => 'Authentication successful', 'user' => $user], 200);
        } else {
            // Неправильные учетные данные
            return response()->json(['message' => 'Invalid credentials'], 401);
        }
    }
}
