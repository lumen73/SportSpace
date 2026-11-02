<?php
/**
 * MATCHS & SCORES - Liste complete, plus filtre par equipe.
 * Fusion des anciennes pages scorematch.php + matchsequipe.php :
 * ses contenus (tous les matchs, et le calendrier d'une equipe)
 * vivent maintenant ici, sur une SEULE requete parametrable.
 * Filtre en GET : lecture pure, URL partageable (pas de CSRF requis).
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';

$teams = $bd->query('SELECT idequipe, nomequipe FROM equipe ORDER BY nomequipe')->fetchAll();

// Filtre optionnel ?idequipe=123 (GET)
$teamFilter = $_GET['idequipe'] ?? null;
if ($teamFilter !== null && (!is_string($teamFilter) || filter_var($teamFilter, FILTER_VALIDATE_INT) === false
    || (int) $teamFilter < 1)) {
    set_flash('error', 'Filtre equipe invalide.');
    header('Location: scorematch.php');
    exit;
}
$selectedTeamId   = $teamFilter !== null ? (int) $teamFilter : null;
$selectedTeamName = null;

$sql = 'SELECT m.idmatch, m.datematch, m.score, m.statut, m.datereporte,
               e1.nomequipe AS equipe1_nom, e1.photodrapeau AS photo1,
               e2.nomequipe AS equipe2_nom, e2.photodrapeau AS photo2
        FROM matchs AS m
        INNER JOIN equipe AS e1 ON m.idequipe1 = e1.idequipe
        INNER JOIN equipe AS e2 ON m.idequipe2 = e2.idequipe';
$params = [];

if ($selectedTeamId) {
    // Verifier que l'equipe existe reellement (pas d'ID fantomes).
    $stmt = $bd->prepare('SELECT nomequipe FROM equipe WHERE idequipe = :id');
    $stmt->execute(['id' => $selectedTeamId]);
    $team = $stmt->fetch();

    if (!$team) {
        set_flash('error', 'Equipe inconnue.');
        header('Location: scorematch.php');
        exit;
    }
    $selectedTeamName = $team['nomequipe'];

    // Deux placeholders DISTINCTS : une meme variable ne peut pas
    // apparaitre deux fois en mode requetes_preparees natives.
    $sql .= ' WHERE (m.idequipe1 = :id1 OR m.idequipe2 = :id2)';
    $params = ['id1' => $selectedTeamId, 'id2' => $selectedTeamId];
}

$sql .= ' ORDER BY m.datematch DESC, e1.nomequipe';
$stmt = $bd->prepare($sql);
$stmt->execute($params);
$matches = $stmt->fetchAll();

$scoredCount  = count(array_filter($matches, fn($m) => $m['score'] !== null && $m['score'] !== ''));
$headingLabel = $selectedTeamName ?? 'Toutes les rencontres';

$page_title  = 'Matchs & Scores | SportSpace';
$page_desc   = "Tous les matchs et leurs resultats" . ($selectedTeamName ? ' - calendrier de ' . $selectedTeamName : '') .
               " : {$scoredCount}/" . count($matches) . ' scores publiés.';
$active_page = 'scorematch.php';
require_once __DIR__ . '/includes/header.php';
?>

<main class="container" id="main-content">
    <section class="page-hero" aria-labelledby="titre">
        <span class="count-chip mb-2"><span class="status-dot" aria-hidden="true"></span><?= $scoredCount ?> / <?= count($matches) ?> scores publiés</span>
        <h1 id="titre" class="page-title mb-1">Matchs <span class="grad-text">&amp; Scores</span></h1>
        <p class="page-subtitle mb-0">Tout le calendrier, filtre par equipe si besoin.</p>
    </section>

    <!-- Filtre par equipe (GET : lecture pure, l'URL est partageable) -->
    <form method="GET" action="scorematch.php" data-skeleton="#resultats"
          class="glass d-flex flex-column flex-sm-row gap-3 align-items-sm-end my-4">
        <div class="flex-fill">
            <label for="idequipe" class="form-label">&Eacute;quipe</label>
            <select name="idequipe" id="idequipe" class="form-select">
                <option value="">-- Toutes les &eacute;quipes --</option>
                <?php foreach ($teams as $t): ?>
                    <option value="<?= (int) $t['idequipe'] ?>" <?= $selectedTeamId == $t['idequipe'] ? 'selected' : '' ?>>
                        <?= e($t['nomequipe']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-neon px-4">Filtrer</button>
    </form>

    <section id="resultats" aria-live="polite">
        <h2 class="display-font h5 mb-3">
            <span class="grad-text"><?= e($headingLabel) ?></span>
            <span class="count-chip ms-2"><?= count($matches) ?> match<?= count($matches) > 1 ? 's' : '' ?></span>
        </h2>

        <div class="row g-3">
            <?php foreach ($matches as $m): ?>
                <div class="col-md-6 d-flex">
                    <?php require __DIR__ . '/includes/match_card.php'; ?>
                </div>
            <?php endforeach; ?>

            <?php if (empty($matches)): ?>
                <div class="col">
                    <div class="glass text-center text-muted">
                        <?= $selectedTeamId ? 'Cette &eacute;quipe n\'a aucun match pour l\'instant.' : 'Aucune rencontre enregistree.' ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
