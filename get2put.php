<?php

/** 
 * Пример использования:
 * URL: https://example.com/script.php?API_key=1234567890abcdef1234567890abcdef&TUNNEL_ID=12345
 * Метод: GET
 * Параметры:
 *   - API_key: строка из 32 шестнадцатеричных символов
 *   - TUNNEL_ID: числовое значение
 * 
 */

// Указываем базовый домен
define('BASE_URL', 'https://6in4.ru/tunnel');

// Указываем путь к файлу лога
define('LOG_FILE', 'log.txt');

// Опция для включения/отключения логирования
define('ENABLE_LOGGING', true);

// Проверяем, что параметры переданы через GET
if (isset($_GET['API_key']) && isset($_GET['TUNNEL_ID'])) {
    try {
        // Получаем значения параметров из GET-запроса и фильтруем их
        $API_key = filter_input(INPUT_GET, 'API_key', FILTER_SANITIZE_STRING);
        $TUNNEL_ID = filter_input(INPUT_GET, 'TUNNEL_ID', FILTER_SANITIZE_STRING);

        // Логируем полученные параметры
        logMessage("Получены параметры: API_key = $API_key, TUNNEL_ID = $TUNNEL_ID");

        // Проверка API_key на соответствие формату: 32 символа в шестнадцатеричной системе
        if (!preg_match('/^[a-f0-9]{32}$/i', $API_key)) {
            throw new Exception("API_key должен быть строкой из 32 шестнадцатеричных символов.");
        }

        // Логируем успешную проверку API_key
        logMessage("API_key проверен успешно.");

        // Проверка TUNNEL_ID на соответствие только десятичным цифрам
        if (!preg_match('/^\d+$/', $TUNNEL_ID)) {
            throw new Exception("TUNNEL_ID должен содержать только десятичные цифры.");
        }

        // Логируем успешную проверку TUNNEL_ID
        logMessage("TUNNEL_ID проверен успешно.");

        // Формируем URL для запроса
        $url = BASE_URL . "/$API_key/$TUNNEL_ID";
        logMessage("Формируется URL для запроса: $url");

        // Получаем IP-адрес клиента
        $client_ip = $_SERVER['REMOTE_ADDR'];

        // Логируем IP-адрес клиента
        logMessage("IP-адрес клиента: $client_ip");

        // Приведение IP-адреса к формату IPv4, если это возможно
        if (filter_var($client_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $client_ip = convertIpv6ToIpv4($client_ip);
            logMessage("IPv6 адрес клиента преобразован в IPv4: $client_ip");
        }

        // Проверяем, что IP является валидным IPv4
        if (!filter_var($client_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new Exception("IP-адрес клиента не является валидным IPv4.");
        }

        // Логируем проверку IPv4
        logMessage("IP-адрес клиента является валидным IPv4.");

        // Формируем данные для PUT-запроса
        $data = json_encode(['ipv4remote' => $client_ip]);

        // Выполняем PUT-запрос
        $response = executePutRequest($url, $data);

        // Выводим успешный ответ
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'response' => json_decode($response, true)]);
    } catch (Exception $e) {
        // Логируем ошибку
        logMessage("Ошибка: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    logMessage("Необходимые параметры не переданы.");
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Необходимые параметры не переданы.']);
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

// Функция для записи сообщений в лог-файл
function logMessage($message)
{
    if (!ENABLE_LOGGING) {
        return;
    }
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents(LOG_FILE, $logMessage, FILE_APPEND);
}

