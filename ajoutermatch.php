<?php
/**
 * AJOUTER / MODIFIER / SUPPRIMER un match (CRUD).
 * Point cle : les listes d'equipes sont generees depuis la table
 * equipe (source de verite), comme les donnees sont re-utilisees
 * pour le formulaire ET le tableau, une seule requete suffit.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';
require_role(['admin', 'agent']);

$teams = $bd->query('SELECT idequipe, nomequipe FROM equipe ORDER BY nomequipe')->fetchAll();

// Statuts disponibles : source unique pour le <select>.
$STATUTS = [
    'en_cours' => 'En cours',
    'termine'  => 'Terminé',
    'reporte'  => 'Reporté',
    'annule'   => 'Annulé',
];

/** Lit et valide le couple statut / date de report envoyes par le formulaire. */
function read_statut(array $POST, array $STATUTS): array
{
    $statut = $POST['statut'] ?? null;
    if (!is_string($statut) || !isset($STATUTS[$statut])) {
        return [null, null];
    }
    $dateReport = null;
    if ($statut === 'reporte') {
        $d = $POST['datereporte'] ?? '';
        // Un match reporte DOIT contenir la prochaine date de la rencontre.
        if (!is_string($d) || $d === '' || !is_valid_mysql_date($d)) {
            return [null, null]; // invalide, signale par l'appelant
        }
        $dateReport = $d;
    }
    return [$statut, $dateReport];
}

/** Vérifie les deux références avant écriture ; les clés étrangères restent le dernier garde-fou. */
function match_teams_exist(PDO $bd, int $team1, int $team2): bool
{
    $stmt = $bd->prepare('SELECT COUNT(*) FROM equipe WHERE idequipe IN (:t1, :t2)');
    $stmt->execute(['t1' => $team1, 't2' => $team2]);
    return (int) $stmt->fetchColumn() === 2;
}

// ---------- Actions d'ecriture ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (isset($_POST['ajouter'])) {
        $team1 = filter_var($_POST['idequipe1'] ?? null, FILTER_VALIDATE_INT);
        $team2 = filter_var($_POST['idequipe2'] ?? null, FILTER_VALIDATE_INT);
        $dateInput = $_POST['date'] ?? '';
        $date  = is_string($dateInput) ? $dateInput : '';
        [$statut, $dateReport] = read_statut($_POST, $STATUTS);

        if (!$team1 || !$team2 || !$date || !is_valid_mysql_date($date)) {
            set_flash('error', 'Formulaire incomplet ou date invalide.');
        } elseif ($team1 === $team2) {
            set_flash('error', 'Une equipe ne peut pas jouer contre elle-meme.');
        } elseif ($statut === null) {
            set_flash('error', 'Le statut est invalide ou la nouvelle date de report manque.');
        } elseif ($statut === 'termine') {
            set_flash('error', 'Un match ne peut etre termine sans score enregistre.');
        } elseif ($statut === 'reporte' && $dateReport <= $date) {
            set_flash('error', 'La nouvelle date doit etre posterieure a la date initiale.');
        } elseif (!match_teams_exist($bd, (int) $team1, (int) $team2)) {
            set_flash('error', 'Une ou plusieurs equipes selectionnees sont introuvables.');
        } else {
            try {
                $stmt = $bd->prepare(
                    'INSERT INTO matchs (idequipe1, idequipe2, datematch, statut, datereporte, score)
                     VALUES (:t1, :t2, :d, :statut, :dr, NULL)'
                );
                $stmt->execute(['t1' => $team1, 't2' => $team2, 'd' => $date,
                                'statut' => $statut, 'dr' => $dateReport]);
                set_flash('success', 'Match enregistre (' . $STATUTS[$statut] . ').');
            } catch (PDOException $e) {
                error_log('Erreur creation match SportSpace : ' . $e->getMessage());
                set_flash('error', 'Enregistrement du match temporairement indisponible.');
            }
        }
        header('Location: ajoutermatch.php');
        exit;
    }

    if (isset($_POST['appliquermodifier'])) {
        $id    = filter_var($_POST['idmatch'] ?? null, FILTER_VALIDATE_INT);
        $team1 = filter_var($_POST['idequipe1'] ?? null, FILTER_VALIDATE_INT);
        $team2 = filter_var($_POST['idequipe2'] ?? null, FILTER_VALIDATE_INT);
        $dateInput = $_POST['date'] ?? '';
        $date  = is_string($dateInput) ? $dateInput : '';
        [$statut, $dateReport] = read_statut($_POST, $STATUTS);
        $scoreInput = $_POST['score'] ?? null;
        $scoreRaw = is_string($scoreInput) ? trim($scoreInput) : null;
        $scoreValid = $scoreRaw !== null && ($scoreRaw === ''
            || preg_match('/^[0-9]{1,3}\s*[-:]\s*[0-9]{1,3}$/D', $scoreRaw) === 1);
        $score = !$scoreValid || $scoreRaw === ''
            ? null
            : preg_replace('/\s*[-:]\s*/', '-', $scoreRaw);

        if (!$id || !$team1 || !$team2 || !is_valid_mysql_date($date) || !$scoreValid) {
            set_flash('error', 'Modification refusee : donnees invalides.');
        } elseif ($team1 === $team2) {
            set_flash('error', 'Une equipe ne peut pas jouer contre elle-meme.');
        } elseif ($statut === null) {
            set_flash('error', 'Le statut est invalide ou la nouvelle date de report manque.');
        } elseif ($statut === 'reporte' && $dateReport <= $date) {
            set_flash('error', 'La nouvelle date doit etre posterieure a la date initiale.');
        } elseif (!match_teams_exist($bd, (int) $team1, (int) $team2)) {
            set_flash('error', 'Une ou plusieurs equipes selectionnees sont introuvables.');
        } else {
            $existingStmt = $bd->prepare('SELECT score FROM matchs WHERE idmatch = :id');
            $existingStmt->execute(['id' => $id]);
            $existingScore = $existingStmt->fetchColumn();
            if ($existingScore === false) {
                set_flash('error', 'Match introuvable.');
            } elseif ($score !== null && $statut !== 'termine') {
                set_flash('error', 'Un score enregistre exige le statut termine.');
            } elseif ($score === null && $statut === 'termine') {
                set_flash('error', 'Un match termine doit avoir un score.');
            } else {
                try {
                    $stmt = $bd->prepare(
                        'UPDATE matchs
                         SET idequipe1 = :t1, idequipe2 = :t2, datematch = :d,
                             statut = :statut, datereporte = :dr, score = :score
                         WHERE idmatch = :id'
                    );
                    $stmt->execute(['t1' => $team1, 't2' => $team2, 'd' => $date,
                                    'statut' => $statut, 'dr' => $dateReport, 'score' => $score, 'id' => $id]);
                    set_flash('success', 'Match mis a jour (' . $STATUTS[$statut] . ').');
                } catch (PDOException $e) {
                    error_log('Erreur modification match SportSpace : ' . $e->getMessage());
                    set_flash('error', 'Mise a jour du match temporairement indisponible.');
                }
            }
        }
        header('Location: ajoutermatch.php');
        exit;
    }

    if (isset($_POST['supprimer'])) {
        $id = filter_var($_POST['idmatch'] ?? null, FILTER_VALIDATE_INT);
        if ($id !== false && $id > 0) {
            $stmt = $bd->prepare('DELETE FROM matchs WHERE idmatch = :id');
            $stmt->execute(['id' => $id]);
            set_flash('success', 'Match supprime.');
        }
        header('Location: ajoutermatch.php');
        exit;
    }
}

// ---------- Mode edition (pre-remplissage) ----------
$editMatch = null;
if (isset($_POST['modifier'], $_POST['idmatch'])) {
    $id = filter_var($_POST['idmatch'], FILTER_VALIDATE_INT);
    if ($id !== false) {
        $stmt = $bd->prepare('SELECT idmatch, idequipe1, idequipe2, datematch, statut, datereporte, score FROM matchs WHERE idmatch = :id');
        $stmt->execute(['id' => $id]);
        $editMatch = $stmt->fetch() ?: null;
    }
}

// ---------- Vue : liste des matchs (une seule requete, reutilisee ensuite) ----------
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
$teamNames = array_column($teams, 'nomequipe', 'idequipe');

$page_title  = 'Gestion des matchs | SportSpace';
$page_desc   = 'Programmer, modifier ou supprimer les rencontres : interface complete de planification des matchs.';
$page_robots = 'noindex,nofollow';
$active_page = 'ajoutermatch.php';
require_once __DIR__ . '/includes/header.php';
?>

<main class="container" id="main-content">
    <section class="page-hero" aria-labelledby="titre">
        <span class="count-chip mb-2"><span class="status-dot" aria-hidden="true"></span>Planification</span>
        <h1 id="titre" class="page-title mb-1">Gestion des <span class="grad-text">matchs</span></h1>
        <p class="page-subtitle mb-0">Programmez les affiches et planifiez le calendrier.</p>
    </section>

    <div class="row g-4 mt-2 crud-layout match-admin-layout">
        <?php $isEditing = $editMatch !== null; ?>
        <div class="col-lg-5">
            <div class="glass section-card crud-card h-100">
                <h2 class="h5 mb-3"><?= $isEditing ? 'Modifier le match #' . (int) $editMatch['idmatch'] : 'Nouveau match' ?></h2>

                <form method="POST" action="ajoutermatch.php" id="form-add-match">
                    <?= csrf_field() ?>
                    <?php if ($isEditing): ?>
                        <input type="hidden" name="idmatch" value="<?= (int) $editMatch['idmatch'] ?>">
                    <?php endif; ?>
                    <?php $formStatuses = $STATUTS; if (!$isEditing) { unset($formStatuses['termine']); } ?>

                    <div class="mb-3">
                        <label for="date" class="form-label">Date du match</label>
                        <input type="date" id="date" name="date" required class="form-control"
                               value="<?= e($editMatch['datematch'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="statut" class="form-label">Statut du match</label>
                        <select name="statut" id="statut" class="form-select">
                            <?php foreach ($formStatuses as $key => $label): ?>
                                <option value="<?= e($key) ?>"
                                    <?= ($editMatch['statut'] ?? 'en_cours') === $key ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Date definie uniquement visible si statut = reporte (toggle JS) -->
                    <div class="mb-3" id="datereporte-wrap" style="display:none">
                        <label for="datereporte" class="form-label">Nouvelle date (report)</label>
                        <input type="date" id="datereporte" name="datereporte" class="form-control"
                               value="<?= e($editMatch['datereporte'] ?? '') ?>">
                        <div class="form-text">Un match report&eacute; doit contenir la prochaine date de la rencontre.</div>
                    </div>

                    <?php foreach (['idequipe1' => 'Equipe n&deg;1', 'idequipe2' => 'Equipe n&deg;2'] as $field => $label): ?>
                        <div class="mb-3">
                            <label for="<?= $field ?>" class="form-label"><?= $label ?></label>
                            <select name="<?= $field ?>" id="<?= $field ?>" required class="form-select">
                                <option value="">-- Choisir --</option>
                                <?php foreach ($teams as $t): ?>
                                    <option value="<?= (int) $t['idequipe'] ?>"
                                        <?= (($editMatch[$field] ?? null) == $t['idequipe']) ? 'selected' : '' ?>>
                                        <?= e($t['nomequipe']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($isEditing): ?>
                        <div class="mb-3">
                            <label for="score" class="form-label">Score du match</label>
                            <input type="text" id="score" name="score" class="form-control"
                                   inputmode="numeric" maxlength="9" placeholder="Ex. : 2-1"
                                   value="<?= e($editMatch['score'] ?? '') ?>">
                            <div class="form-text">Laissez vide pour effacer le score. Un score saisi met le statut à « Terminé ».</div>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex gap-2">
                        <button type="submit" name="<?= $isEditing ? 'appliquermodifier' : 'ajouter' ?>" value="1" class="btn btn-neon flex-fill">
                            <?= $isEditing ? 'Enregistrer' : 'Programmer le match' ?>
                        </button>
                        <?php if ($isEditing): ?>
                            <a href="ajoutermatch.php" class="btn btn-ghost">Annuler</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="glass section-card crud-card match-list-card">
                <h2 class="h5 mb-3">
                    Rencontres
                    <span class="count-chip ms-1"><?= count($matches) ?></span>
                </h2>

                <div class="table-responsive">
                    <table class="table table-modern align-middle mb-0">
                        <caption class="visually-hidden">Liste des matchs programmees avec actions.</caption>
                        <thead>
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col">Affiche</th>
                                <th scope="col">Statut</th>
                                <th scope="col" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($matches as $m):
                                $state = match_state($m);
                            ?>
                                <tr>
                                    <td class="text-nowrap"><?= e(format_date_fr($m['datematch'])) ?></td>
                                    <td>
                                        <strong><?= e($m['equipe1_nom']) ?></strong>
                                        <span class="text-muted">vs</span>
                                        <strong><?= e($m['equipe2_nom']) ?></strong>
                                        <?php if ($m['score'] !== null && $m['score'] !== ''): ?>
                                            <span class="match-score ms-1"><?= e($m['score']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($state['key'] === 'reporte' && !empty($state['date_report'])): ?>
                                            <div class="form-text">Prochaine date : <?= e($state['date_report']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-chip <?= e($state['class']) ?>">
                                            <span class="status-dot" aria-hidden="true"></span><?= e($state['label']) ?>
                                            <?php if ($state['key'] === 'reporte' && !empty($state['date_report'])): ?>
                                                &middot; <?= e($state['date_report']) ?>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td class="text-end text-nowrap">
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
                                <tr><td colspan="3" class="text-center text-muted">Aucun match programme.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
