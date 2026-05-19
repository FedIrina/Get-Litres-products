<?php
/*
 * Самостоятельный скрипт, который следует запускать напрямую из терминала или из bash-скрипта
 */
set_time_limit(0);
define('WP_USE_THEMES', false);
$path = str_replace('/wp-content/plugins/wc-litres-integration', '', __DIR__);
global $wp, $wp_query, $wp_the_query, $wp_rewrite, $wp_did_header;
require_once($path . '/wp-load.php');

function litres_fail($message)
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$checkpointFile = __DIR__ . '/checkpoint';
$litresApiUrl = get_option('litresApiFreshBookUrl');
$place = get_option('litresPlace');
$secretkey = get_option('litres_secretKey');
$timestamp = time();
$xmlPath = __DIR__ . '/xml/';
$xmlFileName = '';
$fileMaxNumber = 0;

$options = getopt('t:');
if (isset($options['t'])) {
    $productType = (int) $options['t'];
} elseif (isset($_SERVER['argv'][1]) && $_SERVER['argv'][1] !== '' && $_SERVER['argv'][1][0] !== '-') {
    $productType = (int) $_SERVER['argv'][1];
} else {
    $productType = 0;
}

$checkpointPath = $checkpointFile . '_' . $productType . '.txt';
$checkpoint = '';
if (is_readable($checkpointPath)) {
    $checkpoint = trim((string) file_get_contents($checkpointPath));
}

if ($checkpoint) {
    $xmlFileName = 'litres_products_type' . $productType;
} else {
    $checkpoint = '2013-01-01 00:00:00';
    $xmlFileName = 'all_litres_products_type' . $productType;
}

if (!is_dir($xmlPath) && !mkdir($xmlPath, 0755, true) && !is_dir($xmlPath)) {
    litres_fail("Не удалось создать каталог: $xmlPath");
}

foreach (new DirectoryIterator($xmlPath) as $file) {
    if ($file->isDot()) {
        continue;
    }
    $fileName = $file->getBasename('.xml');
    if ($fileName && strpos($fileName, $xmlFileName) !== false) {
        $fileNumber = explode('_', $fileName);

        if ($fileNumber && is_numeric(end($fileNumber))) {
            $fileMaxNumber = max($fileMaxNumber, end($fileNumber));
        }
    }
}

$fileMaxNumber++;

$sha = hash('sha256', $timestamp . ':' . $secretkey . ':' . $checkpoint);
$getdata = http_build_query(array(
    'checkpoint' => $checkpoint,
    'place' => $place,
    'timestamp' => $timestamp,
    'sha' => $sha,
    'type' => $productType,
));
$outputPath = $xmlPath . $xmlFileName . '_' . $fileMaxNumber . '.xml';
$fp = fopen($outputPath, 'w');
if ($fp === false) {
    litres_fail("Не удалось создать файл: $outputPath");
}

$handle = curl_init($litresApiUrl . '?' . $getdata);
if ($handle === false) {
    fclose($fp);
    litres_fail('Не удалось инициализировать cURL');
}

$logData = array(
    'checkpoint' => $checkpoint,
    'place' => $place,
    'timestamp' => $timestamp,
    'sha' => '***',
    'type' => $productType,
);
file_put_contents(__DIR__ . '/LITRES.log', '[' . date('d/m/Y H:i:s') . '] ' . $litresApiUrl . '?' . http_build_query($logData) . PHP_EOL, FILE_APPEND);

curl_setopt($handle, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($handle, CURLOPT_TIMEOUT, 3000);
curl_setopt($handle, CURLOPT_FILE, $fp);
$answer = curl_exec($handle);

if ($answer === false) {
    $error = curl_error($handle);
    curl_close($handle);
    fclose($fp);
    @unlink($outputPath);
    litres_fail("Ошибка загрузки с API Литрес: $error");
}

$httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
curl_close($handle);
fclose($fp);

if ($httpCode < 200 || $httpCode >= 300) {
    @unlink($outputPath);
    litres_fail("API Литрес вернул HTTP $httpCode");
}

$newCheckpoint = date('Y-m-d\ H:i:s', $timestamp);
file_put_contents($checkpointPath, $newCheckpoint);

print($xmlFileName . '_' . $fileMaxNumber);
