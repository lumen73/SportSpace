    <?php
/**
 * SAISIE D'UN SCORE.
 * Securite : l'id du match arrive en GET ? Il est filtre par
 * FILTER_VALIDATE_INT, puis re-verifie en base via une requete
 * preparee avant tout affichage. Le score lui-meme est valide par
 * une expression reguliere stricte "0-0" a "999-999".
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';
require_role(['admin', 'agent']);

// ---------- Ecriture du score ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter'])) {
    csrf_verify();

    $matchId = filter_var($_POST['idmatch'] ?? null, FILTER_VALIDATE_INT);
    $scoreInput = $_POST['score'] ?? '';
    $score = is_string($scoreInput) ? trim($scoreInput) : '';

    if (!$matchId || !preg_match('/^\d{1,3}\s*[-:]\s*\d{1,3}$/', $score)) {
        set_flash('error', 'Score invalide : attendu un format comme 2-1.');
        header('Location: ajouterscore.php' . ($matchId ? "?idmatch=$matchId" : ''));
        exit;
    }

    // Garde-fou serveur : on re-verifie l'eligibilite du match AVANT la mise a jour.
    // Regles : pas de score sur un match a venir, annule ou reporte.
    $stmt = $bd->prepare('SELECT datematch, statut, score FROM matchs WHERE idmatch = :id');
    $stmt->execute(['id' => $matchId]);
    $m = $stmt->fetch();

    if (!$m) {
        set_flash('error', 'Match introuvable.');
        header('Location: ajouterscore.php');
        exit;
    }
    if (!saisie_score_autorisee($m)) {
        set_flash('error', 'Saisie refusee : ce match est a venir, annule ou reporte.');
        header('Location: ajouterscore.php?idmatch=' . $matchId);
        exit;
    }

    // Normalisation : "3 : 2" devient "3-2"
    $score = preg_replace('/\s*[-:]\s*/', '-', $score);

    // Une seule requete atomique empeche les doubles saisies concurrentes
    // et revalide la date et le statut au moment precis de l'ecriture.
    $stmt = $bd->prepare(
        "UPDATE matchs
         SET score = :score, statut = 'termine'
         WHERE idmatch = :id
           AND (score IS NULL OR score = '')
           AND datematch <= :today
           AND statut IN ('en_cours', 'termine')"
    );
    $stmt->execute(['score' => $score, 'id' => $matchId, 'today' => date('Y-m-d')]);
    if ($stmt->rowCount() !== 1) {
        set_flash('error', 'Le match a change ou un score existe deja. Rechargez la page avant de reessayer.');
        header('Location: ajouterscore.php?idmatch=' . $matchId);
        exit;
    }

    set_flash('success', "Score [{$score}] enregistre.");
    header('Location: ajouterscore.php?idmatch=' . $matchId); // PRG : evite la resoumission par F5
    exit;
}

// ---------- Selection du match a scorer (parametre GET filtre) ----------
$selectedMatchId = filter_input(INPUT_GET, 'idmatch', FILTER_VALIDATE_INT) ?: null;
$selectedMatch   = null;

if ($selectedMatchId) {
    $stmt = $bd->prepare(
        'SELECT m.idmatch, m.score, m.datematch, m.statut, m.datereporte,
                e1.nomequipe AS equipe1_nom, e1.photodrapeau AS photo1,
                e2.nomequipe AS equipe2_nom, e2.photodrapeau AS photo2
         FROM matchs AS m
         INNER JOIN equipe AS e1 ON m.idequipe1 = e1.idequipe
         INNER JOIN equipe AS e2 ON m.idequipe2 = e2.idequipe
         WHERE m.idmatch = :id'
    );
    $stmt->execute(['id' => $selectedMatchId]);
    $selectedMatch = $stmt->fetch() ?: null;
}

// ---------- Liste completes des matchs ----------
$stmt = $bd->prepare(
    'SELECT m.idmatch, m.datematch, m.score, m.statut, m.datereporte,
            e1.nomequipe AS equipe1_nom, e2.nomequipe AS equipe2_nom
     FROM matchs AS m
     INNER JOIN equipe AS e1 ON m.idequipe1 = e1.idequipe
     INNER JOIN equipe AS e2 ON m.idequipe2 = e2.idequipe
     ORDER BY m.datematch DESC'
);
$stmt->execute();
$matches = $stmt->fetchAll();
$scoredCount = count(array_filter($matches, fn($m) => $m['score'] !== null && $m['score'] !== ''));

$page_title  = 'Saisir un score | SportSpace';
$page_desc   = "Enregistrez les resultats des rencontres : {$scoredCount}/" . count($matches) . ' scores valides';
$page_robots = 'noindex,nofollow';
$active_page = 'ajouterscore.php';
require_once __DIR__ . '/includes/header.php';
?>

<main class="container" id="main-content">
    <section class="page-hero" aria-labelledby="titre">
        <span class="count-chip mb-2"><span class="status-dot" aria-hidden="true"></span><?= $scoredCount ?> / <?= count($matches) ?> scores valides</span>
        <h1 id="titre" class="page-title mb-1">Saisir un <span class="grad-text">score</span></h1>
        <p class="page-subtitle mb-0">Selectionnez une affiche, puis enregistrez le resultat final.</p>
    </section>

    <div class="row g-4 mt-2 crud-layout score-admin-layout">
        <!-- Tableau de selection -->
        <div class="col-12<?= $selectedMatch ? ' col-lg-7' : '' ?>">
            <div class="glass section-card crud-card score-list-card">
                <h2 class="h5 mb-3">Rencontres</h2>
                <div class="table-responsive">
                    <table class="table table-modern align-middle mb-0">
                        <caption class="visually-hidden">Liste des matchs pour la saisie des scores.</caption>
                        <thead>
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col">Match</th>
                                <th scope="col">Statut / Score</th>
                                <th scope="col" class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($matches as $m):
                                $state   = match_state($m);
                                $saisible = saisie_score_autorisee($m);
                            ?>
                                <tr>
                                    <td class="text-nowrap"><?= e(format_date_fr($m['datematch'])) ?></td>
                                    <td><strong><?= e($m['equipe1_nom']) ?></strong> <span class="text-muted">vs</span> <strong><?= e($m['equipe2_nom']) ?></strong></td>
                                    <td class="text-nowrap">
                                        <span class="status-chip <?= e($state['class']) ?>">
                                            <span class="status-dot" aria-hidden="true"></span><?= e($state['label']) ?>
                                            <?php if ($state['key'] === 'reporte' && !empty($state['date_report'])): ?>
                                                &middot; <?= e($state['date_report']) ?>
                                            <?php endif; ?>
                                        </span>
                                        <?php if ($m['score'] !== null && $m['score'] !== ''): ?>
                                            <span class="match-score ms-1"><?= e($m['score']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <?php if ($saisible): ?>
                                            <a href="ajouterscore.php?idmatch=<?= (int) $m['idmatch'] ?>"
                                               class="btn btn-ghost btn-sm<?= $m['idmatch'] == $selectedMatchId ? ' active' : '' ?>">
                                                Saisir
                                            </a>
                                        <?php endif; ?>

                                        <form method="POST" action="ajoutermatch.php" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="idmatch" value="<?= (int) $m['idmatch'] ?>">
                                            <button type="submit" name="modifier" value="1" class="btn btn-warn-neon btn-sm">Modifier</button>
                                        </form>
                                        <form method="POST" action="ajoutermatch.php" class="d-inline" data-confirm="Supprimer definitivement ce match ?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="idmatch" value="<?= (int) $m['idmatch'] ?>">
                                            <button type="submit" name="supprimer" value="1" class="btn btn-danger-neon btn-sm">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (empty($matches)): ?>
                                <tr><td colspan="4" class="text-center text-muted">Aucun match.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Formulaire de saisie : uniquement lorsqu'un match est sélectionné. -->
        <?php if ($selectedMatch): ?>
            <div class="col-lg-5">
                <div class="glass section-card crud-card h-100">
                    <?php
                    $stateSel = match_state($selectedMatch);
                    ?>
                    <h2 class="h5 mb-3">Resultat final</h2>
                    <p class="mb-4">
                        <strong><?= e($selectedMatch['equipe1_nom']) ?></strong>
                        <span class="text-muted">vs</span>
                        <strong><?= e($selectedMatch['equipe2_nom']) ?></strong>
                        <small class="d-block text-muted mt-1"><?= e(format_date_fr($selectedMatch['datematch'])) ?></small>
                        <span class="status-chip <?= e($stateSel['class']) ?> mt-1">
                            <span class="status-dot" aria-hidden="true"></span><?= e($stateSel['label']) ?>
                            <?php if ($stateSel['key'] === 'reporte' && !empty($stateSel['date_report'])): ?>
                                &middot; <?= e($stateSel['date_report']) ?>
                            <?php endif; ?>
                        </span>
                    </p>

                    <?php if (saisie_score_autorisee($selectedMatch)): ?>
                        <form method="POST" action="ajouterscore.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="idmatch" value="<?= (int) $selectedMatch['idmatch'] ?>">

                            <div class="mb-3">
                                <label for="score" class="form-label">Score (format : 2-1)</label>
                                <input type="text" id="score" name="score" required
                                       pattern="^\d{1,3}\s*(?:-|:)\s*\d{1,3}$"
                                       class="form-control form-control-lg text-center"
                                       placeholder="2 - 1"
                                       inputmode="numeric"
                                       value="<?= e($selectedMatch['score'] ?? '') ?>">
                                <div class="form-text">Format libre : le serveur normalise en &laquo; x-y &raquo;.</div>
                            </div>

                            <button type="submit" name="ajouter" value="1" class="btn btn-neon w-100">Enregistrer le score</button>
                        </form>
                    <?php else: ?>
                        <!-- Garde-fou : deja joue / a venir / annule / reporte -> pas de formulaire -->
                        <div class="alert alert-warning mb-0" role="status">
                            La saisie est indisponible :
                            <?= $stateSel['key'] === 'reporte'
                                ? 'le match a ete reporte' . (!empty($stateSel['date_report']) ? ' au ' . e($stateSel['date_report']) : '') . '.'
                                : (isset($selectedMatch['score']) && $selectedMatch['score'] !== null && $selectedMatch['score'] !== ''
                                    ? 'le score de ce match a deja ete enregistre (' . e($selectedMatch['score']) . ').'
                                    : ($stateSel['key'] === 'annule' ? 'le match a ete annule.' : 'le match est a venir.')) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
