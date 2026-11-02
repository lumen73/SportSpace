<?php
/**
 * HEADER GLOBAL
 * Reçoit : $page_title, $page_desc, $active_page (fichier concerné).
 * Pourquoi le SEO dynamique : chaque page déclare son propre
 * <title> et meta description, signal unique envoyé aux moteurs.
 */
$page_title    = $page_title    ?? 'Suivi des Matchs & Équipes';
$page_desc     = $page_desc     ?? 'Plateforme de gestion des équipes et des matchs : calendrier, scores et fiches équipes en temps réel.';
$active_page   = $active_page   ?? '';
$page_robots   = $page_robots   ?? 'index,follow';

$canonicalUrl = null;
$appUrl = rtrim((string) env('APP_URL', ''), '/');
$appUrlParts = parse_url($appUrl);
if (is_array($appUrlParts) && isset($appUrlParts['scheme'], $appUrlParts['host'])
    && in_array(strtolower($appUrlParts['scheme']), ['http', 'https'], true)) {
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $pageName = !is_string($requestPath) || $requestPath === '' || str_ends_with($requestPath, '/')
        ? 'index.php'
        : basename($requestPath);
    $canonicalUrl = $appUrl . '/' . rawurlencode($pageName);

    $canonicalParams = [];
    if ($pageName === 'matchdujour.php' && isset($_GET['ladate']) && is_string($_GET['ladate'])
        && is_valid_mysql_date($_GET['ladate'])) {
        $canonicalParams['ladate'] = $_GET['ladate'];
    } elseif ($pageName === 'scorematch.php' && isset($_GET['idequipe'])
        && (is_string($_GET['idequipe']) || is_int($_GET['idequipe']))
        && filter_var($_GET['idequipe'], FILTER_VALIDATE_INT) !== false
        && (int) $_GET['idequipe'] > 0) {
        $canonicalParams['idequipe'] = (int) $_GET['idequipe'];
    }
    if ($canonicalParams !== []) {
        $canonicalUrl .= '?' . http_build_query($canonicalParams);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- ███ SEO dynamique par page ███ -->
    <title><?= e($page_title) ?></title>
    <meta name="description" content="<?= e($page_desc) ?>">
    <meta name="robots" content="<?= e($page_robots) ?>">
    <meta name="theme-color" content="#f4f6f9">
    <?php if ($canonicalUrl !== null): ?>
        <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <?php endif; ?>

    <!-- Open Graph (partage réseaux sociaux / messageries) -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($page_title) ?>">
    <meta property="og:description" content="<?= e($page_desc) ?>">
    <?php if ($canonicalUrl !== null): ?>
        <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <?php endif; ?>

    <link rel="icon" href="data:image/svg+xml,<?= rawurlencode(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><circle cx="16" cy="16" r="14" fill="none" stroke="#4361ee" stroke-width="2.5"/><path d="M16 9v14M9 16h14" stroke="#00e5ff" stroke-width="2.5" stroke-linecap="round"/></svg>'
    ) ?>">

    <!-- Typographies géométriques : display Space Grotesk + corps Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- CSS : Bootstrap local, puis notre design-system par-dessus -->
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">

    <!-- JS chargé en defer : le Parsing HTML ne bloque pas → FCP plus rapide -->
    <script src="assets/js/app.js" defer></script>
</head>
<body>
    <a class="skip-link" href="#main-content">Aller au contenu principal</a>
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <?php if ($flash = get_flash()): ?>
        <div class="container mt-3">
            <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : e($flash['type']) ?> alert-dismissible fade show glass flash-alert" role="alert" aria-live="assertive">
                <?= e($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
            </div>
        </div>
    <?php endif; ?>
