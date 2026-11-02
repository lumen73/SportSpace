<?php
/**
 * NAVBAR GLOBALE
 * Pourquoi aria-current="page" : lecteurs d'écran + SEO (signale
 * la page active, sans recourir à un classement visuel seul).
 */
$items = [
    'index.php'        => 'Équipes',
    'matchdujour.php'  => 'Match du jour',
    'scorematch.php'   => 'Matchs & Scores',
];
require_once __DIR__ . '/auth_check.php';

if (in_array(current_user_role(), ['admin', 'agent'], true)) {
    $items += [
        'creerequipe.php'  => 'Créer une équipe',
        'ajoutermatch.php' => 'Ajouter un match',
        'ajouterscore.php' => 'Saisir un score',
    ];
}
if (current_user_role() === 'admin') {
    $items['gestioncomptes.php'] = 'Gestion des comptes';
}
?>
<nav class="navbar navbar-expand-xl main-nav sticky-top" aria-label="Navigation principale">
    <div class="container nav-shell">
        <a class="navbar-brand brand" href="index.php">
            <span class="brand-mark" aria-hidden="true">SS</span>
            <span class="brand-text">Sport<span class="grad-text">Space</span></span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Ouvrir le menu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse justify-content-end" id="mainNav">
            <ul class="navbar-nav align-items-xl-center">
                <?php foreach ($items as $href => $label): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= $active_page === $href ? ' active' : '' ?>"
                           href="<?= e($href) ?>"
                           <?= $active_page === $href ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
                    </li>
                <?php endforeach; ?>
                <?php if (is_authenticated()): ?>
                    <li class="nav-item">
                        <form method="POST" action="logout.php" class="m-0">
                            <?= csrf_field() ?>
                            <button class="nav-link border-0 bg-transparent" type="submit">Déconnexion</button>
                        </form>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="login.php">Connexion</a></li>
                    <li class="nav-item"><a class="nav-link" href="register.php">Inscription</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
