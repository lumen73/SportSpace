<?php
/** Sitemap XML des pages publiques et des filtres actuellement consultables. */
require_once __DIR__ . '/config/database.php';

$baseUrl = rtrim((string) env('APP_URL', ''), '/');
$baseParts = parse_url($baseUrl);
if (!is_array($baseParts) || !isset($baseParts['scheme'], $baseParts['host'])
    || !in_array(strtolower($baseParts['scheme']), ['http', 'https'], true)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('APP_URL doit contenir une URL absolue HTTP ou HTTPS.');
}

$urls = [
    $baseUrl . '/index.php',
    $baseUrl . '/matchdujour.php',
    $baseUrl . '/scorematch.php',
];

$teamIds = $bd->query('SELECT idequipe FROM equipe ORDER BY idequipe')->fetchAll(PDO::FETCH_COLUMN);
foreach ($teamIds as $teamId) {
    $urls[] = $baseUrl . '/scorematch.php?idequipe=' . rawurlencode((string) $teamId);
}

$matchDates = $bd->query(
    "SELECT DISTINCT datematch FROM matchs WHERE statut <> 'annule' ORDER BY datematch DESC LIMIT 500"
)->fetchAll(PDO::FETCH_COLUMN);
foreach ($matchDates as $matchDate) {
    $urls[] = $baseUrl . '/matchdujour.php?ladate=' . rawurlencode((string) $matchDate);
}

header('Content-Type: application/xml; charset=UTF-8');
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
foreach ($urls as $url) {
    echo '  <url><loc>' . htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc></url>\n";
}
echo "</urlset>\n";
