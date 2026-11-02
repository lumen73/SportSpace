<?php
/**
 * CREER / MODIFIER / SUPPRIMER une equipe (CRUD complet).
 * Securite :
 *  - CSRF obligatoire sur chaque post (csrf_verify()).
 *  - PDO prepare : aucune concatenation de variable dans le SQL.
 *  - Upload valide par finfo (type MIME reel + extension blanche + taille).
 * UX : messages via set_flash(), plus jamais d'alert() JS.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';
require_role(['admin', 'agent']);

/**
 * Enregistre une image de drapeau uploadee et renvoie son nom unique.
 * Pourquoi un nom genere ? Un nom utilisateur peut contenir des
 * caracteres douteux ou des doublons : nom aleatoire non previsible.
 */
/** Redimensionne les grandes images quand GD a assez de memoire. */
function resize_uploaded_logo_if_needed(
    string $tmpName,
    string $mime,
    int $width,
    int $height,
    string $destinationPath,
    int $sourceSize,
    int $maxEdge = 2000
): bool {
    if ((max($width, $height) <= $maxEdge && $sourceSize <= 1024 * 1024)
        || $mime === 'image/gif' || !extension_loaded('gd')) {
        return false;
    }

    $decoders = [
        'image/png' => 'imagecreatefrompng',
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/webp' => 'imagecreatefromwebp',
        'image/avif' => 'imagecreatefromavif',
    ];
    $decoder = $decoders[$mime] ?? null;
    $encoders = [
        'image/png' => 'imagepng',
        'image/jpeg' => 'imagejpeg',
        'image/webp' => 'imagewebp',
        'image/avif' => 'imageavif',
    ];
    $encoder = $encoders[$mime] ?? null;
    if ($decoder === null || $encoder === null || !function_exists($decoder) || !function_exists($encoder)) {
        return false;
    }

    $scale = min(1, $maxEdge / max($width, $height));
    $targetWidth = max(1, (int) round($width * $scale));
    $targetHeight = max(1, (int) round($height * $scale));
    $memoryLimit = trim((string) ini_get('memory_limit'));
    $memoryLimitBytes = 0;
    if ($memoryLimit !== '' && $memoryLimit !== '-1'
        && preg_match('/^(\d+)\s*([KMG])?$/i', $memoryLimit, $limitMatch)) {
        $memoryLimitBytes = (int) $limitMatch[1];
        $memoryLimitBytes *= match (strtolower($limitMatch[2] ?? '')) {
            'g' => 1024 * 1024 * 1024,
            'm' => 1024 * 1024,
            'k' => 1024,
            default => 1,
        };
    }
    $estimatedMemory = memory_get_usage(true) + ($width * $height * 5)
        + ($targetWidth * $targetHeight * 4) + (8 * 1024 * 1024);
    if ($memoryLimitBytes > 0 && $estimatedMemory >= $memoryLimitBytes) {
        return false;
    }

    $source = @$decoder($tmpName);
    if ($source === false) {
        return false;
    }

    $resized = @imagecreatetruecolor($targetWidth, $targetHeight);
    if ($resized === false) {
        imagedestroy($source);
        return false;
    }
    if (in_array($mime, ['image/png', 'image/webp', 'image/avif'], true)) {
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefill($resized, 0, 0, $transparent);
    }

    $copied = imagecopyresampled(
        $resized,
        $source,
        0,
        0,
        0,
        0,
        $targetWidth,
        $targetHeight,
        imagesx($source),
        imagesy($source)
    );
    $saved = false;
    if ($copied) {
        $saved = match ($mime) {
            'image/png' => @imagepng($resized, $destinationPath, 6),
            'image/jpeg' => @imagejpeg($resized, $destinationPath, 88),
            'image/webp' => @imagewebp($resized, $destinationPath, 85),
            'image/avif' => @imageavif($resized, $destinationPath, 60),
            default => false,
        };
    }
    imagedestroy($resized);
    imagedestroy($source);

    if (!$saved) {
        @unlink($destinationPath);
        throw new RuntimeException('Impossible de preparer cette image pour l\'enregistrement.');
    }

    return true;
}

function save_uploaded_logo(array $file): string
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
            ? 'Image trop lourde (8 Mo maximum).'
            : 'Le televersement de l’image a echoue.');
    }

    $tmpName = $file['tmp_name'] ?? '';
    if (!is_string($tmpName) || $tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('Fichier televerse invalide.');
    }

    $size = filesize($tmpName);
    if ($size === false || $size > 8 * 1024 * 1024) { // 8 Mo max
        throw new RuntimeException('Image trop lourde (8 Mo maximum).');
    }

    // Le type MIME REEL du fichier, pas celui declare par le navigateur.
    $mimes = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/avif' => 'avif',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($tmpName);

    if (!isset($mimes[$mime])) {
        throw new RuntimeException('Format non autorise (PNG, JPG, WEBP, GIF ou AVIF uniquement).');
    }

    $imageInfo = @getimagesize($tmpName);
    if ($imageInfo === false || $imageInfo[0] < 1 || $imageInfo[1] < 1
        || ($imageInfo[0] * $imageInfo[1]) > 40000000) {
        throw new RuntimeException('Image invalide ou trop grande a traiter (40 megapixels maximum).');
    }

    $filename = 'drapeau_' . bin2hex(random_bytes(16)) . '.' . $mimes[$mime];
    $destinationPath = __DIR__ . '/photos/' . $filename;
    $resized = resize_uploaded_logo_if_needed(
        $tmpName,
        $mime,
        (int) $imageInfo[0],
        (int) $imageInfo[1],
        $destinationPath,
        (int) $size
    );

    if (!$resized && !move_uploaded_file($tmpName, $destinationPath)) {
        throw new RuntimeException('Impossible d\'enregistrer l\'image sur le serveur.');
    }

    return $filename;
}

/** Supprime un fichier drapeau du disque si present. */
function delete_logo_file(?string $name): void
{
    if ($name && basename($name) === $name && $name !== '.' && $name !== '..'
        && !str_contains($name, '/') && !str_contains($name, '\\')) {
        $path = __DIR__ . '/photos/' . $name;
        if (is_file($path) && !@unlink($path)) {
            error_log('SportSpace : impossible de supprimer un ancien fichier image.');
        }
    }
}

/** Un upload absent est facultatif ; un upload avec erreur doit etre refuse. */
function logo_upload_requested(): bool
{
    return isset($_FILES['photodrapeau'])
        && is_array($_FILES['photodrapeau'])
        && ($_FILES['photodrapeau']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify(); // Toute ecriture verifie d'abord l'origine de la requete

    // ---------- AJOUT ----------
    if (isset($_POST['ajouter'])) {
        $nameInput = $_POST['nom'] ?? '';
        $name = is_string($nameInput) ? trim($nameInput) : '';
        if ($name === '' || mb_strlen($name, 'UTF-8') > 50) {
            set_flash('error', 'Le nom de l’equipe est obligatoire et doit contenir au plus 50 caracteres.');
        } else {
            $logo = null;
            try {
                if (logo_upload_requested()) {
                    $logo = save_uploaded_logo($_FILES['photodrapeau']);
                }
                $stmt = $bd->prepare('INSERT INTO equipe (nomequipe, photodrapeau) VALUES (:nom, :logo)');
                $stmt->execute(['nom' => $name, 'logo' => $logo]);
                set_flash('success', "L'equipe a ete creee avec succes.");
            } catch (RuntimeException $e) {
                delete_logo_file($logo);
                set_flash('error', $e->getMessage());
            } catch (Throwable $e) {
                delete_logo_file($logo);
                error_log('Erreur creation equipe SportSpace : ' . $e->getMessage());
                set_flash('error', 'Creation de l’equipe temporairement indisponible.');
            }
        }
        header('Location: creerequipe.php');
        exit;
    }

    // ---------- MODIFICATION ----------
    elseif (isset($_POST['appliquermodifier'])) {
        $id   = filter_var($_POST['idequipe'] ?? null, FILTER_VALIDATE_INT);
        $nameInput = $_POST['nom'] ?? '';
        $name = is_string($nameInput) ? trim($nameInput) : '';

        if ($id === false || $id < 1 || $name === '' || mb_strlen($name, 'UTF-8') > 50) {
            set_flash('error', 'Modification refusee : donnees incompletes.');
        } else {
            $logo = null;
            $oldLogo = null;
            try {
                $old = $bd->prepare('SELECT photodrapeau FROM equipe WHERE idequipe = :id');
                $old->execute(['id' => $id]);
                $oldLogo = $old->fetchColumn();
                if ($oldLogo === false) {
                    throw new RuntimeException('Equipe introuvable.');
                }

                if (logo_upload_requested()) {
                    $logo = save_uploaded_logo($_FILES['photodrapeau']);
                    $stmt = $bd->prepare('UPDATE equipe SET nomequipe = :nom, photodrapeau = :logo WHERE idequipe = :id');
                    $stmt->execute(['nom' => $name, 'logo' => $logo, 'id' => $id]);
                    if ($stmt->rowCount() !== 1) {
                        throw new RuntimeException('Mise a jour de l’equipe impossible.');
                    }
                    delete_logo_file(is_string($oldLogo) ? $oldLogo : null);
                } else {
                    $stmt = $bd->prepare('UPDATE equipe SET nomequipe = :nom WHERE idequipe = :id');
                    $stmt->execute(['nom' => $name, 'id' => $id]);
                }
                set_flash('success', 'Equipe mise a jour.');
            } catch (RuntimeException $e) {
                delete_logo_file($logo);
                set_flash('error', $e->getMessage());
            } catch (Throwable $e) {
                delete_logo_file($logo);
                error_log('Erreur modification equipe SportSpace : ' . $e->getMessage());
                set_flash('error', 'Mise a jour de l’equipe temporairement indisponible.');
            }
        }
        header('Location: creerequipe.php');
        exit;
    }

    // ---------- SUPPRESSION ----------
    elseif (isset($_POST['supprimer'])) {
        $id = filter_var($_POST['idequipe'] ?? null, FILTER_VALIDATE_INT);
        if ($id !== false && $id > 0) {
            $logo = null;
            $deletedMatches = 0;
            try {
                $bd->beginTransaction();
                $stmt = $bd->prepare('SELECT photodrapeau FROM equipe WHERE idequipe = :id FOR UPDATE');
                $stmt->execute(['id' => $id]);
                $logo = $stmt->fetchColumn();

                if ($logo === false) {
                    $bd->rollBack();
                    set_flash('error', 'Equipe introuvable.');
                } else {
                    $stmt = $bd->prepare(
                        'DELETE FROM matchs WHERE idequipe1 = :equipe1 OR idequipe2 = :equipe2'
                    );
                    $stmt->execute(['equipe1' => $id, 'equipe2' => $id]);
                    $deletedMatches = $stmt->rowCount();

                    $stmt = $bd->prepare('DELETE FROM equipe WHERE idequipe = :id');
                    $stmt->execute(['id' => $id]);
                    if ($stmt->rowCount() !== 1) {
                        throw new RuntimeException('Suppression de l equipe impossible.');
                    }

                    $bd->commit();
                    delete_logo_file(is_string($logo) ? $logo : null);
                    set_flash('success', "L'equipe et {$deletedMatches} match(s) associe(s) ont ete supprimes.");
                }
            } catch (Throwable $e) {
                if ($bd->inTransaction()) {
                    $bd->rollBack();
                }
                error_log('Erreur suppression equipe SportSpace : ' . $e->getMessage());
                set_flash('error', 'Suppression de l equipe impossible. Aucun changement n a ete conserve.');
            }
        }
        header('Location: creerequipe.php');
        exit;
    }
}

// ---------- PRE-REMPLISSAGE du mode modification ----------
$editTeam = null;
if (isset($_POST['modifier'], $_POST['idequipe'])) {
    $id = filter_var($_POST['idequipe'], FILTER_VALIDATE_INT);
    if ($id !== false) {
        $stmt = $bd->prepare('SELECT idequipe, nomequipe, photodrapeau FROM equipe WHERE idequipe = :id');
        $stmt->execute(['id' => $id]);
        $editTeam = $stmt->fetch() ?: null;
    }
}

$teams = $bd->query(
    'SELECT e.idequipe, e.nomequipe, e.photodrapeau,
            (SELECT COUNT(*) FROM matchs m WHERE m.idequipe1 = e.idequipe OR m.idequipe2 = e.idequipe) AS match_count
     FROM equipe e
     ORDER BY e.nomequipe'
)->fetchAll();

$page_title  = 'Gestion des equipes | SportSpace';
$page_desc   = 'Creer, modifier ou supprimer une equipe : interface d\'administration securisee avec upload de drapeau.';
$page_robots = 'noindex,nofollow';
$active_page = 'creerequipe.php';
require_once __DIR__ . '/includes/header.php';
?>

<main class="container" id="main-content">
    <section class="page-hero" aria-labelledby="titre">
        <span class="count-chip mb-2"><span class="status-dot" aria-hidden="true"></span>Administration</span>
        <h1 id="titre" class="page-title mb-1">Gestion des <span class="grad-text">&eacute;quipes</span></h1>
        <p class="page-subtitle mb-0">Cr&eacute;ez vos &eacute;quipes, chargez leur drapeau, gardez la base propre.</p>
    </section>

    <div class="row g-4 mt-2 crud-layout team-admin-layout">
        <!-- Formulaire : creation OU edition -->
        <div class="col-lg-5">
            <div class="glass section-card crud-card h-100">
                <h2 class="h5 mb-3"><?= $editTeam ? 'Modifier ' . e($editTeam['nomequipe']) : 'Nouvelle &eacute;quipe' ?></h2>

                <form method="POST" action="creerequipe.php" enctype="multipart/form-data" id="form-equipe">
                    <?= csrf_field() ?>
                    <?php if ($editTeam): ?>
                        <input type="hidden" name="idequipe" value="<?= (int) $editTeam['idequipe'] ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="nom" class="form-label">Nom de l'&eacute;quipe</label>
                        <input type="text" id="nom" name="nom" required maxlength="50"
                               class="form-control" placeholder="Ex : Cote d'Ivoire"
                               value="<?= e($editTeam['nomequipe'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="photodrapeau" class="form-label">Drapeau (PNG, JPG, WEBP, GIF, AVIF - 8 Mo max)</label>
                        <div class="form-text mb-2">Les grandes images sont reduites automatiquement pour accelerer l'affichage.</div>

                        <!-- Drapeau en cours : visible pour decider ou non de le remplacer -->
                        <?php if ($editTeam && $editTeam['photodrapeau']): ?>
                            <div class="current-file d-flex align-items-center gap-2 mb-2 p-2 rounded-3" style="background:rgba(67,97,238,.06)">
                                <img src="<?= e(team_logo($editTeam['photodrapeau'], $editTeam['nomequipe'])) ?>"
                                     alt="Drapeau actuel de <?= e($editTeam['nomequipe']) ?>"
                                     width="44" height="44"
                                     style="border-radius:10px;object-fit:contain;background:#fff">
                                <div>
                                    <strong class="d-block small">Drapeau actuel</strong>
                                    <code class="small text-truncate d-inline-block" style="max-width:220px"><?= e($editTeam['photodrapeau']) ?></code>
                                </div>
                            </div>
                            <div class="form-text mb-2">Chargez un fichier pour REMPLACER, ou laissez vide pour CONSERVER.</div>
                        <?php endif; ?>

                        <input type="file" id="photodrapeau" name="photodrapeau"
                               accept="image/png,image/jpeg,image/webp,image/gif,image/avif"
                               class="form-control">
                    </div>

                    <div class="d-flex gap-2">
                        <?php if ($editTeam): ?>
                            <button type="submit" name="appliquermodifier" value="1" class="btn btn-neon flex-fill">Enregistrer</button>
                            <a href="creerequipe.php" class="btn btn-ghost">Annuler</a>
                        <?php else: ?>
                            <button type="submit" name="ajouter" value="1" class="btn btn-neon flex-fill">Cr&eacute;er l'&eacute;quipe</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tableau des equipes -->
        <div class="col-lg-7">
            <div class="glass section-card crud-card team-list-card">
                <h2 class="h5 mb-3">
                    &Eacute;quipes r&eacute;f&eacute;renc&eacute;es
                    <span class="count-chip ms-1"><?= count($teams) ?></span>
                </h2>

                <div class="table-responsive">
                    <table class="table table-modern align-middle mb-0">
                        <caption class="visually-hidden">Liste des equipes avec actions de modification et de suppression.</caption>
                        <thead>
                            <tr>
                                <th scope="col">&Eacute;quipe</th>
                                <th scope="col">Drapeau</th>
                                <th scope="col" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teams as $t): ?>
                                <tr>
                                    <td class="fw-semibold"><?= e($t['nomequipe']) ?></td>
                                    <td>
                                        <img src="<?= e(team_logo($t['photodrapeau'], $t['nomequipe'])) ?>"
                                             alt="<?= e($t['nomequipe']) ?>" width="40" height="40"
                                             loading="lazy" decoding="async"
                                             style="border-radius:10px;object-fit:contain;background:#fff">
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <form method="POST" action="creerequipe.php" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="idequipe" value="<?= (int) $t['idequipe'] ?>">
                                            <button type="submit" name="modifier" value="1" class="btn btn-warn-neon btn-sm">Modifier</button>
                                        </form>
                                        <form method="POST" action="creerequipe.php" class="d-inline"
                                              data-confirm="Supprimer <?= e($t['nomequipe']) ?> et ses <?= (int) $t['match_count'] ?> match(s) associés ? Cette action est irréversible.">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="idequipe" value="<?= (int) $t['idequipe'] ?>">
                                            <button type="submit" name="supprimer" value="1" class="btn btn-danger-neon btn-sm">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (empty($teams)): ?>
                                <tr><td colspan="3" class="text-center text-muted">Aucune equipe enregistree.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
