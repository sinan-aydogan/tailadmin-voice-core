<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'TailAdmin Voice Core API',
    description: 'Metinden sese (TTS), sesten metne (STT), ses klonlama profilleri, AI model yönetimi ve görev takibi için REST API v1.'
)]
#[OA\Server(url: '/', description: 'Uygulama sunucusu')]
#[OA\SecurityScheme(
    securityScheme: 'ApiKeyAuth',
    type: 'apiKey',
    in: 'header',
    name: 'X-API-Key',
    description: "VOICE_CORE_API_KEY .env değişkeni tanımlıysa gereklidir. `X-API-Key` başlığı veya `Authorization: Bearer <key>` ile gönderilebilir."
)]
#[OA\Tag(name: 'System', description: 'Sağlık kontrolü ve sistem istatistikleri')]
#[OA\Tag(name: 'TTS', description: 'Metinden sese (Text-to-Speech) işlemleri')]
#[OA\Tag(name: 'STT', description: 'Sesten metne (Speech-to-Text) işlemleri')]
#[OA\Tag(name: 'Tasks', description: 'Ses işleme görevlerinin listelenmesi ve yönetimi')]
#[OA\Tag(name: 'Models', description: 'AI model listeleme ve indirme')]
#[OA\Tag(name: 'Profiles', description: 'Ses klonlama profilleri')]
abstract class Controller
{
    //
}
