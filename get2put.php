<?php

/** 
 * Пример использования:
 * URL:  https://example.com/script.php?API_key=1234567890abcdef1234567890abcdef&TUNNEL_ID=12345
 * shell:  wget -O- "https://example.com/script.php?API_key=1234567890abcdef1234567890abcdef&TUNNEL_ID=12345"
 * 
 * Метод: GET
 * Параметры:
 *   - API_key: строка из 32 шестнадцатеричных символов
 *   - TUNNEL_ID: числовое значение
 * 
 */

// Указываем базовый домен
define('BASE_URL', 'https://6in4.ru/tunnel');

// Ограничение частоты запросов
session_start();
if (!isset($_SESSION['last_request'])) {
    $_SESSION['last_request'] = time();
} elseif (time() - $_SESSION['last_request'] < 1) { // Ограничение: 1 запрос в секунду
    logMessage("Слишком частые запросы от клиента.");
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Слишком частые запросы. Подождите перед следующим.']);
    exit;
}
$_SESSION['last_request'] = time();

// Функция для вывода сообщений на страницу
function displayMessage($message)
{
    echo "<pre>$message</pre>";
}

// Проверяем, что параметры переданы через GET или в пути
$pathInfo = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : '';
if (isset($_GET['API_key']) && isset($_GET['TUNNEL_ID'])) {
    $API_key = filter_input(INPUT_GET, 'API_key', FILTER_SANITIZE_STRING);
    $TUNNEL_ID = filter_input(INPUT_GET, 'TUNNEL_ID', FILTER_SANITIZE_STRING);
} elseif (preg_match('#^([a-f0-9]{32})/(\d+)$#i', $pathInfo, $matches)) {
    $API_key = $matches[1];
    $TUNNEL_ID = $matches[2];
} else {
    displayMessage("Необходимые параметры не переданы.");
    exit;
}

try {
    // Логируем полученные параметры
    displayMessage("Получены параметры: API_key = $API_key, TUNNEL_ID = $TUNNEL_ID");

    // Проверка API_key на соответствие формату: 32 символа в шестнадцатеричной системе
    if (!preg_match('/^[a-f0-9]{32}$/i', $API_key)) {
        throw new Exception("API_key должен быть строкой из 32 шестнадцатеричных символов.");
    }
    displayMessage("API_key проверен успешно.");

    // Проверка TUNNEL_ID на соответствие только десятичным цифрам
    if (!preg_match('/^\d+$/', $TUNNEL_ID)) {
        throw new Exception("TUNNEL_ID должен содержать только десятичные цифры.");
    }
    displayMessage("TUNNEL_ID проверен успешно.");

    // Формируем URL для запроса
    $url = BASE_URL . "/$API_key/$TUNNEL_ID";
    displayMessage("Формируется URL для запроса: $url");

    // Получаем IP-адрес клиента
    $client_ip = $_SERVER['REMOTE_ADDR'];
    displayMessage("IP-адрес клиента: $client_ip");

    // Приведение IP-адреса к формату IPv4, если это возможно
    if (filter_var($client_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $client_ip = convertIpv6ToIpv4($client_ip);
        displayMessage("IPv6 адрес клиента преобразован в IPv4: $client_ip");
    }

    // Проверяем, что IP является валидным IPv4
    if (!filter_var($client_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        throw new Exception("IP-адрес клиента не является валидным IPv4.");
    }
    displayMessage("IP-адрес клиента является валидным IPv4.");

    // Формируем данные для PUT-запроса
    $data = json_encode(['ipv4remote' => $client_ip]);

    // Выполняем PUT-запрос
    $response = executePutRequest($url, $data);
    displayMessage("Ответ сервера: " . htmlspecialchars($response));
} catch (Exception $e) {
    displayMessage("Ошибка: " . $e->getMessage());
    exit;
}

// Функция для выполнения PUT-запроса
function executePutRequest($url, $data)
{
    $ch = curl_init($url);

    if ($ch === false) {
        throw new Exception("Ошибка инициализации cURL.");
    }

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data)
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $errorMessage = "cURL Error: " . curl_error($ch);
        curl_close($ch);
        throw new Exception($errorMessage);
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode === 404) {
        curl_close($ch);
        throw new Exception("Недействительная пара KEY / ID");
    } elseif ($httpCode !== 200) {
        curl_close($ch);
        throw new Exception("HTTP-код ответа: $httpCode");
    }

    curl_close($ch);
    return $response;
}

// Функция для преобразования IPv6 в IPv4 (если возможно)
function convertIpv6ToIpv4($ipv6)
{
    if (preg_match('/^::ffff:(\d{1,3}\.){3}\d{1,3}$/', $ipv6)) {
        return substr($ipv6, 7);
    }
    return $ipv6;
}
