#!/usr/bin/env php
<?php
/**
 *  -- Starlabix --
 * - Получает app code -> app token
 * - Логинится пользователем и получает slid_token
 * - Авторизуется в developer API (cookie slnet)
 * - Забирает user_info, mobile_devices, obd_params, obd_errors
 *
 * Требуется файл user_data.php со значениями:
 *   $user_AppId, $user_Secret_md5, $user_Secret, $user_login, $user_pass
 */
declare(strict_types=1);
error_reporting(0); // Отключить сообщения об ошибках
require __DIR__ . '/user_data.php';

/**
 * Безопасно достать значение из массива по пути и вернуть default, если чего-то нет.
 */
function arr_get(array $a, array $path, $default = null) {
    foreach ($path as $k) {
        if (!is_array($a) || !array_key_exists($k, $a)) return $default;
        $a = $a[$k];
    }
    return $a;
}

/**
 * HTTP POST через file_get_contents().
 * ВАЖНО: в исходнике используется Content-type: application/x-www-form-urlencoded даже для JSON.
 */
function http_post(string $url, $body, array $headers = []): string {
    $headers = array_merge(['Content-type: application/x-www-form-urlencoded'], $headers);

    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => implode("\r\n", $headers),
            'content' => is_array($body) ? http_build_query($body) : (string)$body,
        ],
    ]);

    return (string)@file_get_contents($url, false, $context);
}

/**
 * cURL GET с cookie + headers.
 */
function curl_get(string $url, array $headers, string $cookie): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER    => true,
        CURLOPT_ACCEPT_ENCODING   => true,
        CURLOPT_HTTP_CONTENT_DECODING => true,
        CURLOPT_SSL_VERIFYPEER    => false,
        CURLOPT_HEADER            => false,
        CURLOPT_HTTPHEADER        => $headers,
        CURLOPT_COOKIESESSION     => true,
        CURLOPT_COOKIE            => $cookie,
    ]);

    $res = (string)curl_exec($ch);
    curl_close($ch);
    return $res;
}
/**
 * 1) Получаем application code -> token для login
 */
$appCodeJson  = @file_get_contents("https://id.starline.ru/apiV3/application/getCode?appId={$user_AppId}&secret={$user_Secret_md5}");
$appCodeArr   = json_decode((string)$appCodeJson, true) ?: [];
$appCode      = (string)arr_get($appCodeArr, ['desc', 'code'], '');
$codeToken    = md5($user_Secret . $appCode);
$appTokenJson = @file_get_contents("https://id.starline.ru/apiV3/application/getToken?appId={$user_AppId}&secret={$codeToken}");
$appTokenArr  = json_decode((string)$appTokenJson, true) ?: [];
$appToken     = (string)arr_get($appTokenArr, ['desc', 'token'], '');
/**
 * 2) Логин пользователя (получаем user_token = slid_token)
 */
$loginUrl   = 'https://id.starline.ru/apiV3/user/login';
$loginResp  = http_post($loginUrl, [
    'token' => $appToken,
    'login' => $user_login,
    'pass'  => $user_pass,
]);
$loginArr   = json_decode($loginResp, true) ?: [];
$slidToken  = (string)arr_get($loginArr, ['desc', 'user_token'], '');
/**
 * 3) Авторизация в developer API по slid_token -> получаем cookie slnet + user_id
 * В исходнике cookie выдёргивается через get_headers() и фиксированный индекс [7].
 * Здесь делаем то же самое через curl, но устойчиво: ищем Set-Cookie с slnet.
 */
$authUrl    = 'https://developer.starline.ru/json/v2/auth.slid';
$authBody   = json_encode(['slid_token' => $slidToken], JSON_UNESCAPED_UNICODE);

$ch = curl_init($authUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-type: application/x-www-form-urlencoded'],
    CURLOPT_POSTFIELDS     => $authBody,
]);
$authRaw = (string)curl_exec($ch);
curl_close($ch);
// Отделяем заголовки от тела
[$authHeadersRaw, $authJson] = array_pad(preg_split("/\R\R/", $authRaw, 2), 2, '');
$authArr = json_decode((string)$authJson, true) ?: [];
$userId  = (string)arr_get($authArr, ['user_id'], '');
// Ищем slnet в Set-Cookie
$slnet = '';
if ($authHeadersRaw) {
    foreach (preg_split("/\R/", $authHeadersRaw) as $h) {
        if (stripos($h, 'Set-Cookie:') === 0 && stripos($h, 'slnet=') !== false) {
            // Пример: Set-Cookie: slnet=...; Path=/; ...
            if (preg_match('/\bslnet=[^;]+;?/', $h, $m)) {
                $slnet = rtrim($m[0], ';');
                break;
            }
        }
    }
}
/**
 * 4) Заголовки для запросов developer API (как в исходнике, но без лишнего дубля)
 */
$headers = [
    'cache-control: max-age=0',
    'upgrade-insecure-requests: 1',
    'user-agent: Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.97 Safari/537.36',
    'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3',
    'accept-language: ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
];
/**
 * 5) Запросы данных
 */
$userInfoJson     = curl_get("https://developer.starline.ru/json/v2/user/{$userId}/user_info", $headers, $slnet);
$mobileDevicesJson= curl_get("https://developer.starline.ru/json/v1/user/{$userId}/mobile_devices", $headers, $slnet);
// device_id берём из user_info.devices[0].device_id
$userInfoArr   = json_decode($userInfoJson, true) ?: [];
$deviceId      = (string)arr_get($userInfoArr, ['devices', 0, 'device_id'], '');
$obdParamsJson = curl_get("https://developer.starline.ru/json/v1/device/{$deviceId}/obd_params",  $headers, $slnet);
$obdErrorsJson = curl_get("https://developer.starline.ru/json/v1/device/{$deviceId}/obd_errors",  $headers, $slnet);
/**
 * 6) Единый JSON-ответ (собираем через json_encode, а не echo-склейкой строк)
 */
$out = [
    'user_info'       => json_decode($userInfoJson, true),
    'mobile_devices'  => json_decode($mobileDevicesJson, true),
    'obd_params'      => json_decode($obdParamsJson, true),
    'obd_errors'      => json_decode($obdErrorsJson, true),
    'creator'         => 'iFraso-dev',
    'link'            => 'https://github.com/iFraso-dev/ZtarLine',
    'contacts'        => 'fraso1989@gmail.com',
];
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
