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
//Created by LanDev
//https://github.com/landev-ru/Starlabix
//Contacts - fraso1989@gmail.com / Fraso@mail.ru
//
//error_reporting(0);     // Turn off all error reporting
include 'user_data.php';        //We connect the user_data.php file with user settings

// Функция для получения реального IP пользователя (с поддержкой CLI)
function getUserIP() {
    // Для CLI режима (запуск из командной строки)
    if (php_sapi_name() === 'cli') {
        return '127.0.0.1';
    }
    
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        return $_SERVER['REMOTE_ADDR'];
    } else {
        return '0.0.0.0';
    }
}

// Функция для извлечения cookie из заголовков
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

//Getting the application code
$getCode1 = file_get_contents("https://id.starline.ru/apiV3/application/getCode?appId=$user_AppId&secret=$user_Secret_md5");
if ($getCode1 === false) {
    die(json_encode(['error' => 'Ошибка получения кода приложения']));
}
$decgetCode = json_decode($getCode1, true);
if (json_last_error() !== JSON_ERROR_NONE || !isset($decgetCode["desc"]["code"])) {
    die(json_encode(['error' => 'Ошибка парсинга ответа getCode', 'raw' => $getCode1]));
}
$getCode = $decgetCode["desc"]["code"];
$codeToken = md5($user_Secret . $getCode);

$getToken = file_get_contents("https://id.starline.ru/apiV3/application/getToken?appId=$user_AppId&secret=$codeToken");
if ($getToken === false) {
    die(json_encode(['error' => 'Ошибка получения токена приложения']));
}
$decgetTokenCode = json_decode($getToken, true);
if (json_last_error() !== JSON_ERROR_NONE || !isset($decgetTokenCode["desc"]["token"])) {
    die(json_encode(['error' => 'Ошибка парсинга ответа getToken', 'raw' => $getToken]));
}
$getTokenCode = $decgetTokenCode["desc"]["token"];

//
//User authentication
$loginarr = 'https://id.starline.ru/apiV3/user/login';

// Формируем multipart/form-data вручную
$params = array(
    'login' => $user_login,
    'pass' => $user_pass,
    'user_ip' => getUserIP()
);

$boundary = uniqid();
$content = '';

foreach ($params as $key => $value) {
    $content .= "--$boundary\r\n";
    $content .= "Content-Disposition: form-data; name=\"$key\"\r\n\r\n";
    $content .= "$value\r\n";
}
$content .= "--$boundary--\r\n";

$headers = [
    "Content-Type: multipart/form-data; boundary=$boundary",
    "token: $getTokenCode"
];

$result = file_get_contents($loginarr, false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => implode("\r\n", $headers) . "\r\n",
        'content' => $content,
        'ignore_errors' => true
    ]
]));

if ($result === false) {
    die(json_encode(['error' => 'Ошибка подключения к API авторизации']));
}

// Пытаемся декодировать JSON без конвертации кодировки
$resultdec = json_decode($result, true);
// Если JSON не валидный, пробуем конвертировать через iconv (если доступно)
if (json_last_error() !== JSON_ERROR_NONE) {
    // Пробуем iconv если доступно
    if (function_exists('iconv')) {
        $result = iconv('ISO-8859-1', 'UTF-8//IGNORE', $result);
        $resultdec = json_decode($result, true);
    }
    
    // Если все еще ошибка, возвращаем raw ответ
    if (json_last_error() !== JSON_ERROR_NONE) {
        die(json_encode(['error' => 'Ошибка парсинга JSON ответа', 'raw' => $result]));
    }
}

// Проверяем на ошибки
if (isset($resultdec['state']) && $resultdec['state'] == 0) {
    die(json_encode(['error' => $resultdec['message'] ?? 'Неизвестная ошибка авторизации']));
}

// Проверяем наличие user_token
if (!isset($resultdec["desc"]["user_token"])) {
    die(json_encode(['error' => 'Не удалось получить user_token', 'response' => $resultdec]));
}

//
//User authorization by StarLineID token
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

// Получаем заголовки ответа
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

// Создаем контекст для GET запросов с cookie
$options = [
    'http' => [
        'method' => 'GET',
        'header' => implode("\r\n", $headers) . "\r\n",
        'ignore_errors' => true
    ]
];
$context = stream_context_create($options);

//
// Get user_info
$resalt1 = file_get_contents('https://developer.starline.ru/json/v2/user/' . $userID1 . '/user_info', false, $context);
if ($resalt1 === false) {
    die(json_encode(['error' => 'Ошибка получения user_info']));
}

//Get mobile_devices
$resalt2 = file_get_contents('https://developer.starline.ru/json/v1/user/' . $userID1 . '/mobile_devices', false, $context);
if ($resalt2 === false) {
    die(json_encode(['error' => 'Ошибка получения mobile_devices']));
}

//Get Device id
$device_id = json_decode($resalt1, true);
if (json_last_error() !== JSON_ERROR_NONE || !isset($device_id["devices"][0]["device_id"])) {
    die(json_encode(['error' => 'Не удалось получить device_id', 'user_info' => $resalt1]));
}
$device_id_end = $device_id["devices"][0]["device_id"];

// Get obd_params
$resalt3 = file_get_contents('https://developer.starline.ru/json/v1/device/' . $device_id_end . '/obd_params', false, $context);
if ($resalt3 === false) {
    die(json_encode(['error' => 'Ошибка получения obd_params']));
}

//Get obd_errors
$resalt4 = file_get_contents('https://developer.starline.ru/json/v1/device/' . $device_id_end . '/obd_errors', false, $context);
if ($resalt4 === false) {
    die(json_encode(['error' => 'Ошибка получения obd_errors']));
}

//
//JSON output
echo ("{\"user_info\":");
echo ($resalt1);                //Get user_info
echo (",");
echo ("\"mobile_devices\":");
echo ($resalt2);                //Get mobile_devices
echo (",");
echo ("\"obd_params\":");
echo ($resalt3);                // Get obd_params
echo (",");
echo ("\"obd_errors\":");
echo ($resalt4);                //Get obd_errors
echo (",");
echo ("\"creator\":\"iFraso-dev\",\"link\":\"https://github.com/landev-ru/Starlabix\",\"contacts\":\"fraso1989@gmail.com\"");  //Get creator
echo ("}")
//
?>
