#!/usr/bin/php
<?php
//
//░▒▓█▓▒░         ░▒▓██████▓▒░  ░▒▓███████▓▒░  ░▒▓███████▓▒░  ░▒▓████████▓▒░ ░▒▓█▓▒░░▒▓█▓▒░
//░▒▓█▓▒░        ░▒▓█▓▒░░▒▓█▓▒░ ░▒▓█▓▒░░▒▓█▓▒░ ░▒▓█▓▒░░▒▓█▓▒░ ░▒▓█▓▒░        ░▒▓█▓▒░░▒▓█▓▒░
//░▒▓█▓▒░        ░▒▓█▓▒░░▒▓█▓▒░ ░▒▓█▓▒░░▒▓█▓▒░ ░▒▓█▓▒░░▒▓█▓▒░ ░▒▓█▓▒░         ░▒▓█▓▒▒▓█▓▒░
//░▒▓█▓▒░        ░▒▓████████▓▒░ ░▒▓█▓▒░░▒▓█▓▒░ ░▒▓█▓▒░░▒▓█▓▒░ ░▒▓██████▓▒░    ░▒▓█▓▒▒▓█▓▒░
//░▒▓█▓▒░        ░▒▓█▓▒░░▒▓█▓▒░ ░▒▓█▓▒░░▒▓█▓▒░ ░▒▓█▓▒░░▒▓█▓▒░ ░▒▓█▓▒░          ░▒▓█▓▓█▓▒░
//░▒▓█▓▒░        ░▒▓█▓▒░░▒▓█▓▒░ ░▒▓█▓▒░░▒▓█▓▒░ ░▒▓█▓▒░░▒▓█▓▒░ ░▒▓█▓▒░          ░▒▓█▓▓█▓▒░
//░▒▓████████▓▒░ ░▒▓█▓▒░░▒▓█▓▒░ ░▒▓█▓▒░░▒▓█▓▒░ ░▒▓███████▓▒░  ░▒▓████████▓▒░    ░▒▓██▓▒░
//
// Автор: LanDev
// https://github.com/landev-ru/Starlabix
// Контакты: fraso1989@gmail.com / Fraso@mail.ru
//
// Скрипт для мониторинга автомобильной сигнализации StarLine в Zabbix.
// Выполняется как External Check: получает телеметрию через официальный API StarLine
// и отдаёт её в формате JSON, который Zabbix разбирает через JSONPATH.
//

//error_reporting(0);     // Отключить вывод всех ошибок (при необходимости раскомментировать)
include 'user_data.php';   // Подключаем файл с настройками пользователя (логин, пароль, AppId, Secret, IP)

/**
 * Автоопределение внешнего IP сервера.
 * Нужно для параметра user_ip при логине в StarLine.. 
 * Если пользователь не задал
 * $user_ip в user_data.php, пробуем определить его автоматически через
 * несколько публичных сервисов. Если ни один не ответил — возвращаем null.
 */
function detectExternalIp() {
    $services = [
        'https://api.ipify.org',
        'https://ifconfig.me/ip',
        'https://icanhazip.com',
        'https://ipinfo.io/ip',
    ];
    foreach ($services as $url) {
        // Контекст HTTP-запроса: GET, таймаут 5 секунд, не падать на HTTP-ошибках
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'ignore_errors' => true,
                'user_agent' => 'Starlabix/1.0',
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $ip = @file_get_contents($url, false, $ctx);
        if ($ip === false) {
            continue;   // Сервис недоступен — пробуем следующий
        }
        $ip = trim($ip);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip; // Получили валидный IP — возвращаем
        }
    }
    return null;        // Ни один сервис не дал валидный IP
}

/**
 * IP-адрес, который отправляется в StarLine при логине (параметр user_ip).
 * Приоритет:
 *   1. $user_ip из user_data.php (если задан вручную);
 *   2. автоопределение внешнего IP через detectExternalIp();
 *   3. для web-режима — REMOTE_ADDR / X-Forwarded-For;
 *   4. крайний случай — 127.0.0.1 с записью предупреждения в error_log.
 */
function getUserIP() {
    global $user_ip;

    // 1. Явно заданный IP в user_data.php
    if (!empty($user_ip)) {
        return $user_ip;
    }

    // 2. Автоопределение (работает и в CLI, и в web)
    $detected = detectExternalIp();
    if ($detected !== null) {
        return $detected;
    }

    // 3. Fallback: для web-режима можно взять IP из заголовков запроса
    if (php_sapi_name() !== 'cli') {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }
    }

    // 4. Не удалось определить IP — предупреждаем в лог и отдаём заглушку
    error_log('Starlabix: не удалось определить внешний IP. Укажите $user_ip в user_data.php.');
    return '127.0.0.1';
}

/**
 * Извлекает значение cookie с именем $cookieName из массива HTTP-заголовков.
 * Используется для получения slnet_token из Set-Cookie при авторизации.
 */
function extractCookie($headers, $cookieName) {
    foreach ($headers as $header) {
        if (stripos($header, 'Set-Cookie:') === 0) {
            if (preg_match('/' . $cookieName . '=([^;]+)/', $header, $matches)) {
                return $matches[1];
            }
        }
    }
    return null;
}

// ---------------------------------------------------------------------------
// Шаг 1. Получение кода приложения (getCode)
// API возвращает code, который затем используется для вычисления токена.
// ---------------------------------------------------------------------------
$getCode1 = file_get_contents("https://id.starline.ru/apiV3/application/getCode?appId=$user_AppId&secret=$user_Secret_md5");
if ($getCode1 === false) {
    die(json_encode(['error' => 'Ошибка получения кода приложения']));
}
$decgetCode = json_decode($getCode1, true);
if (json_last_error() !== JSON_ERROR_NONE || !isset($decgetCode["desc"]["code"])) {
    die(json_encode(['error' => 'Ошибка парсинга ответа getCode', 'raw' => $getCode1]));
}
$getCode = $decgetCode["desc"]["code"];
// Токен приложения = md5(Secret + code) — по документации StarLine API
$codeToken = md5($user_Secret . $getCode);

// ---------------------------------------------------------------------------
// Шаг 2. Получение токена приложения (getToken)
// Токен передаётся в заголовке token при логине пользователя.
// ---------------------------------------------------------------------------
$getToken = file_get_contents("https://id.starline.ru/apiV3/application/getToken?appId=$user_AppId&secret=$codeToken");
if ($getToken === false) {
    die(json_encode(['error' => 'Ошибка получения токена приложения']));
}
$decgetTokenCode = json_decode($getToken, true);
if (json_last_error() !== JSON_ERROR_NONE || !isset($decgetTokenCode["desc"]["token"])) {
    die(json_encode(['error' => 'Ошибка парсинга ответа getToken', 'raw' => $getToken]));
}
$getTokenCode = $decgetTokenCode["desc"]["token"];

// ---------------------------------------------------------------------------
// Шаг 3. Авторизация пользователя (login)
// Отправляем multipart/form-data с логином, паролем (sha1) и user_ip.
// ---------------------------------------------------------------------------
$loginarr = 'https://id.starline.ru/apiV3/user/login';

// Формируем multipart/form-data вручную (file_get_contents не умеет это из коробки)
$params = array(
    'login' => $user_login,
    'pass' => $user_pass,
    'user_ip' => getUserIP()
);

$boundary = uniqid();   // Уникальная граница между частями формы
$content = '';

foreach ($params as $key => $value) {
    $content .= "--$boundary\r\n";
    $content .= "Content-Disposition: form-data; name=\"$key\"\r\n\r\n";
    $content .= "$value\r\n";
}
$content .= "--$boundary--\r\n";   // Закрывающая граница

$headers = [
    "Content-Type: multipart/form-data; boundary=$boundary",
    "token: $getTokenCode"
];

$result = file_get_contents($loginarr, false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => implode("\r\n", $headers) . "\r\n",
        'content' => $content,
        'ignore_errors' => true   // Не падать на HTTP-ошибках — разберём тело сами
    ]
]));

if ($result === false) {
    die(json_encode(['error' => 'Ошибка подключения к API авторизации']));
}

// Пытаемся декодировать JSON без конвертации кодировки
$resultdec = json_decode($result, true);
// Если JSON не валидный, пробуем конвертировать через iconv (если доступно)
if (json_last_error() !== JSON_ERROR_NONE) {
    if (function_exists('iconv')) {
        $result = iconv('ISO-8859-1', 'UTF-8//IGNORE', $result);
        $resultdec = json_decode($result, true);
    }
    if (json_last_error() !== JSON_ERROR_NONE) {
        die(json_encode(['error' => 'Ошибка парсинга JSON ответа', 'raw' => $result]));
    }
}

// Проверяем на ошибки: state == 0 означает ошибку авторизации
if (isset($resultdec['state']) && $resultdec['state'] == 0) {
    die(json_encode(['error' => $resultdec['message'] ?? 'Неизвестная ошибка авторизации']));
}

// Проверяем наличие user_token — он нужен для следующего шага
if (!isset($resultdec["desc"]["user_token"])) {
    die(json_encode(['error' => 'Не удалось получить user_token', 'response' => $resultdec]));
}

// ---------------------------------------------------------------------------
// Шаг 4. Авторизация по StarLineID token (auth.slid)
// Меняем user_token на сессионную cookie slnet для запросов к developer.starline.ru.
// ---------------------------------------------------------------------------
$slid_token_url = 'https://developer.starline.ru/json/v2/auth.slid';
$params1 = json_encode(array('slid_token' => $resultdec["desc"]["user_token"]));

$headers = [
    'Content-Type: application/x-www-form-urlencoded',
    'Content-Length: ' . strlen($params1)
];

$options = [
    'http' => [
        'method' => 'POST',
        'header' => implode("\r\n", $headers) . "\r\n",
        'content' => $params1,
        'ignore_errors' => true
    ]
];
$context = stream_context_create($options);
$response = file_get_contents($slid_token_url, false, $context);

if ($response === false) {
    die(json_encode(['error' => 'Ошибка авторизации в WebAPI']));
}

// Получаем заголовки ответа (file_get_contents складывает их в $http_response_header)
$responseHeaders = $http_response_header ?? [];

// Извлекаем slnet_token из заголовка Set-Cookie
$slnet = extractCookie($responseHeaders, 'slnet');

if (empty($slnet)) {
    die(json_encode(['error' => 'Не удалось получить slnet_token', 'headers' => $responseHeaders]));
}

$resultdec1 = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die(json_encode(['error' => 'Ошибка парсинга JSON ответа авторизации', 'raw' => $response]));
}

if (!isset($resultdec1["user_id"])) {
    die(json_encode(['error' => 'Не удалось получить user_id', 'response' => $resultdec1]));
}

$userID1 = $resultdec1["user_id"];

// ---------------------------------------------------------------------------
// Шаг 5. Запросы к developer.starline.ru с cookie slnet
// ---------------------------------------------------------------------------
// Заголовки для последующих запросов с Cookie
$headers = [
    'cache-control: max-age=0',
    'upgrade-insecure-requests: 1',
    'user-agent: Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.97 Safari/537.36',
    'sec-fetch-user: ?1',
    'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3',
    'x-compress: null',
    'sec-fetch-site: none',
    'sec-fetch-mode: navigate',
    'accept-encoding: deflate, br',
    'accept-language: ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
    'Cookie: slnet=' . $slnet
];

// Создаём контекст для GET-запросов с cookie (переиспользуется ниже)
$options = [
    'http' => [
        'method' => 'GET',
        'header' => implode("\r\n", $headers) . "\r\n",
        'ignore_errors' => true
    ]
];
$context = stream_context_create($options);

// Получаем информацию о пользователе и его устройствах (user_info)
$resalt1 = file_get_contents('https://developer.starline.ru/json/v2/user/' . $userID1 . '/user_info', false, $context);
if ($resalt1 === false) {
    die(json_encode(['error' => 'Ошибка получения user_info']));
}

// Получаем список мобильных устройств (mobile_devices)
$resalt2 = file_get_contents('https://developer.starline.ru/json/v1/user/' . $userID1 . '/mobile_devices', false, $context);
if ($resalt2 === false) {
    die(json_encode(['error' => 'Ошибка получения mobile_devices']));
}

// Из user_info берём device_id первого устройства — он нужен для запросов OBD
$device_id = json_decode($resalt1, true);
if (json_last_error() !== JSON_ERROR_NONE || !isset($device_id["devices"][0]["device_id"])) {
    die(json_encode(['error' => 'Не удалось получить device_id', 'user_info' => $resalt1]));
}
$device_id_end = $device_id["devices"][0]["device_id"];

// Получаем параметры OBD-II (пробег, температура и т.п.)
$resalt3 = file_get_contents('https://developer.starline.ru/json/v1/device/' . $device_id_end . '/obd_params', false, $context);
if ($resalt3 === false) {
    die(json_encode(['error' => 'Ошибка получения obd_params']));
}

// Получаем ошибки OBD-II
$resalt4 = file_get_contents('https://developer.starline.ru/json/v1/device/' . $device_id_end . '/obd_errors', false, $context);
if ($resalt4 === false) {
    die(json_encode(['error' => 'Ошибка получения obd_errors']));
}

// ---------------------------------------------------------------------------
// Шаг 6. Формирование итогового JSON для Zabbix
// Структура: user_info, mobile_devices, obd_params, obd_errors + метаданные.
// ---------------------------------------------------------------------------
echo ("{\"user_info\":");
echo ($resalt1);                // user_info
echo (",");
echo ("\"mobile_devices\":");
echo ($resalt2);                // mobile_devices
echo (",");
echo ("\"obd_params\":");
echo ($resalt3);                // obd_params
echo (",");
echo ("\"obd_errors\":");
echo ($resalt4);                // obd_errors
echo (",");
echo ("\"creator\":\"iFraso-dev\",\"link\":\"https://github.com/landev-ru/Starlabix\",\"contacts\":\"fraso1989@gmail.com\"");  // Метаданные автора
echo ("}")
//
?>
