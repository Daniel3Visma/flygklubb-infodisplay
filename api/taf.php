<?php
/**
 * PHP proxy for AWC (Aviation Weather Center) TAF data
 * No API key required. Free, official and highly reliable.
 * Polled independently from METAR — see TAF_REFRESH_MINUTES in config.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$configFile = __DIR__ . '/config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

// Ingen API-nyckel behövs längre!
$icao = defined('ICAO_CODE') ? ICAO_CODE : 'ESMK';
$refreshMin = defined('TAF_REFRESH_MINUTES') ? max(1, (int) TAF_REFRESH_MINUTES) : 60;

$maxAge = (int) max(60, ($refreshMin * 60) / 2);
header("Cache-Control: max-age={$maxAge}");

// AWC:s officiella JSON API för TAF
$url = "https://aviationweather.gov/api/data/taf?ids={$icao}&format=json";

$context = stream_context_create([
    'http' => [
        'header' => "User-Agent: InfoDisplayApp/1.0\r\n",
        'timeout' => 10,
    ],
]);

$response = @file_get_contents($url, false, $context);
$data = $response === false ? null : json_decode($response, true);

$result = [
    'taf' => null,
    'tafRaw' => null,
    'fetchedAt' => date('c'),
];

if (is_array($data) && count($data) > 0) {
    $result['taf'] = $data[0];
    // AWC returnerar råsträngen för TAF under namnet "rawTAF"
    $result['tafRaw'] = $data[0]['rawTAF'] ?? null;
} else {
    http_response_code(502);
    echo json_encode(['error' => 'Failed to fetch TAF from Aviation Weather Center']);
    exit;
}

echo json_encode($result);