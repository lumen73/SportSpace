<?php
/**
 * INDEX - Galerie des equipes.
 * Separation des responsabilites : les donnees d'abord (controleur),
 * l'affichage ensuite (vue). Une seule requete, fetchAll().
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';

$teams = $bd->query('SELECT idequipe, nomequipe, photodrapeau FROM equipe ORDER BY nomequipe')->fetchAll();
$nbTeams = count($teams);

$page_title    = 'Équipes | SportSpace - Suivi de matchs';
$page_desc     = "Découvrez les {$nbTeams} équipes enregistrées : drapeaux et fiches complètes de la plateforme de gestion de matchs.";
$active_page   = 'index.php';
require_once __DIR__ . '/includes/header.php';
?>

<main class="container" id="main-content">
    <!-- Titre + fil d'ariane -->
    <section class="page-hero" aria-labelledby="titre">
        <span class="count-chip mb-2"><span class="status-dot" aria-hidden="true"></span><?= $nbTeams ?> équipes</span>
        <h1 id="titre" class="page-title mb-1">Nos <span class="grad-text">Équipes</span></h1>
        <p class="page-subtitle mb-0">Le vivier de la compétition, présenté en un coup d'œil.</p>
    </section>

    <section class="row g-4 mt-3" aria-label="Liste des équipes">
        <?php foreach ($teams as $team): ?>
            <div class="col-6 col-md-4 col-lg-3 col-xl-auto d-flex">
                <article class="team-card w-100">
                    <img src="<?= e(team_logo($team['photodrapeau'], $team['nomequipe'])) ?>"
                         alt="Drapeau de l'équipe <?= e($team['nomequipe']) ?>"
                         width="84" height="84"
                         loading="lazy" decoding="async"
                         class="team-logo">
                    <h2 class="team-name mb-0"><?= e($team['nomequipe']) ?></h2>
                </article>
            </div>
        <?php endforeach; ?>

        <?php if ($nbTeams === 0): ?>
            <div class="col">
                <div class="glass text-center">
                    <p class="mb-1 text-muted">Aucune équipe pour le moment.</p>
                    <?php if (in_array(current_user_role(), ['admin', 'agent'], true)): ?>
                        <a href="creerequipe.php" class="btn btn-neon">Créer la première équipe</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
