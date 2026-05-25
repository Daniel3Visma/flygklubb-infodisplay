<?php
/**
 * PHP proxy for AWC (Aviation Weather Center) METAR data
 * No API key required. Free, official and highly reliable.
 * Polled independently from TAF — see METAR_REFRESH_MINUTES in config.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$configFile = __DIR__ . '/config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

// AWC API behöver ingen nyckel, så vi plockar bara ut ICAO och uppdateringsfrekvens
$icao = defined('ICAO_CODE') ? ICAO_CODE : 'ESMK';
$refreshMin = defined('METAR_REFRESH_MINUTES') ? max(1, (int) METAR_REFRESH_MINUTES) : 15;

// Browser-side cache for half the refresh interval to absorb accidental reloads
$maxAge = (int) max(30, ($refreshMin * 60) / 2);
header("Cache-Control: max-age={$maxAge}");

// AWC:s officiella JSON API
$url = "https://aviationweather.gov/api/data/metar?ids={$icao}&format=json";

// Det är bra praxis att skicka med en User-Agent när man använder publika, gratis API:er
$context = stream_context_create([
    'http' => [
        'header' => "User-Agent: MinMetarApp/1.0 (Skriv in din mail här om du vill)\r\n",
        'timeout' => 10,
    ],
]);

$response = @file_get_contents($url, false, $context);
$data = $response === false ? null : json_decode($response, true);

$result = [
    'metar' => null,
    'metarRaw' => null,
    'fetchedAt' => date('c'),
];

// AWC returnerar en array med METAR-objekt. Vi tar det första (och enda, om vi bara frågat efter ett).
if (is_array($data) && count($data) > 0) {
    $result['metar'] = $data[0];
    // AWC kallar fältet för rå-strängen "rawOb"
    $result['metarRaw'] = $data[0]['rawOb'] ?? null; 
} else {
    http_response_code(502);
    echo json_encode(['error' => 'Failed to fetch METAR from Aviation Weather Center']);
    exit;
}

echo json_encode($result);