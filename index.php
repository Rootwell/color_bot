<?php

require_once 'vendor/autoload.php';

use ColorThief\ColorThief;

//константы для удобства
define('TOKEN', '1639862093:AAHTXIlR___Q98_YmZwrE9dad73Squb3jw0');
define('TG_BOT_API_URL', 'https://api.telegram.org/bot');
define('HOST_URL', 'https://d4b1f0bbaee0.ngrok.io');
define('RESULT_CIRCLE_DIAMETR', 80);
define('RESULT_CIRCLE_INTERVAL', 10);

//получаем данные из входного потока в этот файл (в него попадает то, что отправляется боту)
$data = json_decode(file_get_contents('php://input'), TRUE);
file_put_contents('file.txt', print_r($data, true) . "\n", FILE_APPEND);
$chatId = $data['message']['chat']['id'];
if (isset($data['message']['caption'])) {
    $messageCaption = $data['message']['caption'];
}
//если пользователь отправил команду /start
if (isset($messageText) && $messageText === '/start') {
    sendMessage($chatId, 'Отправь мне изображение, а я попробую определить его цветовую палитру, можешь указать диапазон от 4 до 8 цветов');
    die();
}

//если пользователь отправил какой-то непонятный нам текст
if (!isset($data['message']['photo'])) {
    sendMessage($chatId, 'Извините, я умею работать только с фотографиями :(');
    die();
}

$colorsCount = 4;

if (isset($messageCaption) && is_numeric($messageCaption) && $messageCaption > 4 && $messageCaption < 9) {
    $colorsCount = $messageCaption;
}

//получаем отправленную пользователем фотографию
$photoFromUser = getPhoto($data);
//определяем цветовую палитру изображения (ищет 4 цвета с изображения, при желании можно указывать пользователем)
$colors = ColorThief::getPalette($photoFromUser, $colorsCount);
//удаляем файл, который отправил нам пользователь
unlink($photoFromUser);
//создаём файл в котором будет изображена цветовая палитра
$fileName = makeFileForColorPaletteImageForSendingToChat($colors, $chatId);

//собираем строку ответа
$response = "Цветовая палитра изображения в RGB кодировке:\n";

//проходимся по массиву цветов и добавляем строку с кодировкой этого цвета в ответ
foreach ($colors as $color) {
    $response = $response . "rgb({$color[0]},{$color[1]},{$color[2]})\n";
}

//добавляем ссылку на файл в ответ
$response = $response . HOST_URL . "/$fileName";

//отправляем ответ пользователю
sendMessage($chatId, $response);

//функция для создания изображения с цветовой палитрой
function drawImageFromColors(array $colors)
{
    $im = imagecreate(RESULT_CIRCLE_DIAMETR * sizeof($colors) + (sizeof($colors) + 1) * RESULT_CIRCLE_INTERVAL, RESULT_CIRCLE_DIAMETR + 2 * RESULT_CIRCLE_INTERVAL);
    imagecolorallocate($im, 255, 255, 255);
    for ($i = 0; $i < sizeof($colors); $i++) {
        $coords = getCoordsForCircle($i);
        imagefilledellipse($im, $coords['x'], $coords['y'],
            RESULT_CIRCLE_DIAMETR, RESULT_CIRCLE_DIAMETR,
            imagecolorallocate($im, $colors[$i][0], $colors[$i][1], $colors[$i][2]));
    }
    return $im;
}

//создаём файл с изображением цветовой палитры
function makeFileForColorPaletteImageForSendingToChat(array $colors, int $chatId): string
{
    $fileName = "img/responseTo$chatId.jpeg";
    imagejpeg(drawImageFromColors($colors), $fileName);
    return $fileName;
}

//функция для отправки get запроса на бот api телеграмма
function getRequestToTelegram(array $data, string $method): string
{
    $requestString = TG_BOT_API_URL . TOKEN . '/' . $method . '?' . http_build_query($data);
    return file_get_contents($requestString);
}

//функция для отправки текстовых сообщений ботом
function sendMessage(int $chatId, string $message): void
{
    $data['chat_id'] = $chatId;
    $data['text'] = $message;
    getRequestToTelegram($data, 'sendMessage');
}

//функция для отправки фото (в этом случае не работает и не используется тк ngrok не пускает через себя отправку файлов)
function sendPhoto(int $chatId, string $filePath): void
{
    $requestString = TG_BOT_API_URL . TOKEN . '/sendPhoto?' . "chat_id=$chatId&photo=" . HOST_URL . "/$filePath";
    file_get_contents($requestString);
}

//функция загрузки изображения
function getPhoto($data): string
{
    $file_id = $data['message']['photo'][count($data['message']['photo']) - 1]['file_id'];
    $file_path = getPhotoPath($file_id);
    return copyPhoto($file_path);
}

// функция получения местонахождения файла
function getPhotoPath($file_id)
{
    return json_decode(file_get_contents('https://api.telegram.org/bot' . TOKEN . '/getFile?file_id=' . $file_id), TRUE)['result']['file_path'];
}

// копируем фото к себе
function copyPhoto($file_path): string
{
    $fileFromTgrm = "https://api.telegram.org/file/bot" . TOKEN . "/" . $file_path;
    $explodedFileName = explode(".", $file_path);
    $ext = end($explodedFileName);
    $newFileWithDir = 'img/' . time() . "." . $ext;
    copy($fileFromTgrm, $newFileWithDir);
    return $newFileWithDir;
}

//функция для получения координат центра круга по его номеру из массива цветов
function getCoordsForCircle($offsetMultiplier): array
{
    $coordsArray = [];
    $coordsArray['x'] = RESULT_CIRCLE_INTERVAL * ($offsetMultiplier + 1) + ($offsetMultiplier * 2 + 1) * (RESULT_CIRCLE_DIAMETR / 2);
    $coordsArray['y'] = RESULT_CIRCLE_INTERVAL + (RESULT_CIRCLE_DIAMETR / 2);
    return $coordsArray;
}




