<?php
/**
 * PHP proxy for AWC (Aviation Weather Center) METAR data
 * No API key required. Free, official and highly reliable.
 * Polled independently from TAF — see METAR_REFRESH_MINUTES in config.php
 *
 * Senaste lyckade svaret sparas på servern. Om AWC inte svarar visas det
 * i stället för ett fel, så skärmen inte står utan väder i en hel period.
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

$cacheTtl = max(60, $refreshMin * 60);
$cacheKey = preg_replace('/[^A-Z0-9_-]/i', '', $icao);
$cacheFile = sys_get_temp_dir() . "/awc_metar_{$cacheKey}.json";

// Browser-side cache for half the refresh interval to absorb accidental reloads
$maxAge = (int) max(30, ($refreshMin * 60) / 2);

function serve_metar_cache($cacheFile, $maxAge) {
    if (!is_readable($cacheFile)) return false;
    header("Cache-Control: max-age={$maxAge}");
    readfile($cacheFile);
    return true;
}

// Färsk cache: svara direkt utan att fråga AWC
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
    serve_metar_cache($cacheFile, $maxAge);
    exit;
}

// AWC:s officiella JSON API
$url = "https://aviationweather.gov/api/data/metar?ids={$icao}&format=json";

// Det är bra praxis att skicka med en User-Agent när man använder publika, gratis API:er
$context = stream_context_create([
    'http' => [
        'header' => "User-Agent: InfoDisplayApp/1.0\r\n",
        'timeout' => 10,
    ],
]);

// Ett nytt försök om första hämtningen misslyckas
$data = null;
for ($attempt = 0; $attempt < 2 && !(is_array($data) && count($data) > 0); $attempt++) {
    if ($attempt > 0) sleep(2);
    $response = @file_get_contents($url, false, $context);
    $data = $response === false ? null : json_decode($response, true);
}

// AWC returnerar en array med METAR-objekt. Vi tar det första (och enda, om vi bara frågat efter ett).
if (!(is_array($data) && count($data) > 0)) {
    // Visa senaste kända METAR hellre än ett fel
    if (serve_metar_cache($cacheFile, $maxAge)) exit;

    header('Cache-Control: no-store');
    http_response_code(502);
    echo json_encode(['error' => 'Failed to fetch METAR from Aviation Weather Center']);
    exit;
}

$result = [
    'metar' => $data[0],
    // AWC kallar fältet för rå-strängen "rawOb"
    'metarRaw' => $data[0]['rawOb'] ?? null,
    'fetchedAt' => date('c'),
];

$json = json_encode($result);
@file_put_contents($cacheFile, $json, LOCK_EX);
header("Cache-Control: max-age={$maxAge}");
echo $json;
