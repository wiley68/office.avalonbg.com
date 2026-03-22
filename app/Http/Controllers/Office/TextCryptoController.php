<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Http\Requests\CryptoTextRequest;
use App\Services\TextCryptoService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

class TextCryptoController extends Controller
{
    public function encrypt(CryptoTextRequest $request, TextCryptoService $crypto): JsonResponse
    {
        try {
            $text = $crypto->encryptPlainText($request->validated('text'));
        } catch (RuntimeException) {
            return response()->json([
                'message' => 'Криптирането не е налично. Свържете се с администратор.',
            ], 503);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Неуспешно криптиране.',
            ], 500);
        }

        return response()->json(['text' => $text]);
    }

    public function decrypt(CryptoTextRequest $request, TextCryptoService $crypto): JsonResponse
    {
        try {
            $text = $crypto->decryptToPlainText($request->validated('text'));
        } catch (DecryptException) {
            return response()->json([
                'message' => 'Невалиден криптиран текст.',
            ], 422);
        } catch (RuntimeException) {
            return response()->json([
                'message' => 'Криптирането не е налично. Свържете се с администратор.',
            ], 503);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Неуспешно декриптиране.',
            ], 500);
        }

        return response()->json(['text' => $text]);
    }
}
