<?php
/**
 * MATCH DU JOUR - Filtrage par date.
 * Pourquoi une requete preparee ? La date saisie par l'utilisateur
 * est un PLACEHOLDER (:ladate) verifie par MySQL : une longue chaine
 * malveillante ne peut jamais devenir du code SQL.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';

$selectedDate    = null; // date brute AAAA-MM-JJ (pour le champ input)
$selectedDateFr  = null; // date locale JJ/MM/AAAA (pour l'affichage)
$matchesOfDay    = [];

if (array_key_exists('ladate', $_GET)) {
    $selectedDate = $_GET['ladate'];

    // Validation cote serveur : seules les vraies dates passent.
    if (is_string($selectedDate) && $selectedDate !== '' && is_valid_mysql_date($selectedDate)) {
        $selectedDateFr = format_date_fr($selectedDate);

        $stmt = $bd->prepare(
            'SELECT m.idmatch, m.datematch, m.score, m.statut, m.datereporte,
                    e1.nomequipe AS equipe1_nom, e1.photodrapeau AS photo1,
                    e2.nomequipe AS equipe2_nom, e2.photodrapeau AS photo2
             FROM matchs AS m
             INNER JOIN equipe AS e1 ON m.idequipe1 = e1.idequipe
             INNER JOIN equipe AS e2 ON m.idequipe2 = e2.idequipe
             WHERE m.datematch = :ladate
             ORDER BY e1.nomequipe'
        );
        $stmt->execute(['ladate' => $selectedDate]);
        $matchesOfDay = $stmt->fetchAll();
    } else {
        set_flash('error', 'La date saisie est invalide.');
        header('Location: matchdujour.php');
        exit;
    }
}

$page_title  = 'Matchs du jour | SportSpace';
$page_desc   = 'Consultez le calendrier des matchs filtre par date : affrontements prevus, avec logos des equipes.';
$active_page = 'matchdujour.php';
require_once __DIR__ . '/includes/header.php';
?>

<main class="container" id="main-content">
    <section class="page-hero" aria-labelledby="titre">
        <span class="count-chip mb-2"><span class="status-dot" aria-hidden="true"></span>Calendrier</span>
        <h1 id="titre" class="page-title mb-1">Matchs <span class="grad-text">du jour</span></h1>
        <p class="page-subtitle mb-0">Choisissez une date : les affiches du jour apparaissent instantanement.</p>
    </section>

    <form method="GET" action="matchdujour.php" data-skeleton="#resultats"
          class="glass d-flex flex-column flex-sm-row gap-3 align-items-sm-end my-4">
        <div class="flex-fill">
            <label for="ladate" class="form-label">Date du match</label>
            <input type="date" id="ladate" name="ladate" required class="form-control"
                   value="<?= e(is_string($selectedDate) && is_valid_mysql_date($selectedDate) ? $selectedDate : date('Y-m-d')) ?>">
        </div>
        <button type="submit" class="btn btn-neon px-4">Afficher</button>
    </form>

    <section id="resultats" aria-live="polite">
        <?php if ($selectedDateFr !== null): ?>
            <h2 class="display-font h5">
                Affiches du <?= e($selectedDateFr) ?>
                <span class="count-chip ms-2"><?= count($matchesOfDay) ?> match<?= count($matchesOfDay) > 1 ? 's' : '' ?></span>
            </h2>

            <div class="row g-3 mt-3">
                <?php foreach ($matchesOfDay as $m): ?>
                    <div class="col-md-6 d-flex">
                        <?php require __DIR__ . '/includes/match_card.php'; ?>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($matchesOfDay)): ?>
                    <div class="col">
                        <div class="glass text-center text-muted">
                            Aucun match programme pour cette date.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p class="text-muted">Choisissez une date ci-dessus pour explorer le calendrier.</p>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
