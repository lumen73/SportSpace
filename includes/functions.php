<?php
/**
 * =====================================================
 *  FONCTIONS UTILITAIRES — Échappement, sécurité, dates
 * =====================================================
 */

/**
 * Échappe toute donnée avant affichage (protection XSS).
 * Pourquoi : htmlspecialchars() n'est PAS une option mais un réflexe :
 * toute valeur issue de la BD ou de l'utilisateur passe par e().
 */
function e(mixed $value): string
{
    if (!is_string($value) && !is_int($value) && !is_float($value) && !is_bool($value)) {
        return '';
    }
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Génère le jeton CSRF stocké en session.
 * Pourquoi : bloque les soumissions forgées depuis un site tiers
 * (le formulaire n'accepte qu'un jeton que NOUS avons délivré).
 */
function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])
        || !preg_match('/^[a-f0-9]{64}$/D', $_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Champ invisible à inclure dans TOUT formulaire mutatif. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Vérifie le jeton ; interrompt la requête si invalide. */
function csrf_verify(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(403);
        die('Requête invalide (jeton CSRF manquant ou invalide).');
    }
}

/**
 * Message flash "stocké en session, affiché une fois".
 * Remplace les alert() JavaScript : plus fiable, stylisable, accessible.
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** Lit et consomme le flash en cours. */
function get_flash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Formate une date MySQL (AAAA-MM-JJ) en JJ/MM/AAAA de façon robuste.
 * Pourquoi : explode() échoue silencieusement sur une donnée invalide ;
 * DateTime valide la valeur et centralise le format FR.
 */
function format_date_fr(string $dateMysql): string
{
    $dt = DateTime::createFromFormat('Y-m-d', $dateMysql);
    return $dt ? $dt->format('d/m/Y') : $dateMysql;
}

/** Validation stricte d'une date ISO sans normalisation silencieuse. */
function is_valid_mysql_date(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();

    return $date !== false
        && ($errors === false || (
            $errors['warning_count'] === 0 &&
            $errors['error_count'] === 0
        ))
        && $date->format('Y-m-d') === $value;
}

/**
 * Etat affichable d'un match a partir du statut et du score.
 * Regles metier :
 *  - annule   -> Annule (jamais de saisie de score)
 *  - reporte  -> Reporte, la prochaine date devra etre affichee
 *  - score publie -> Deja joue (jour passe) ou Termine (du jour)
 *  - sinon (programme, pas de score) -> En cours
 * @return array{key:string,label:string,class:string,date_report?:string}
 */
function match_state(array $m): array
{
    $date   = (string) ($m['datematch'] ?? '');
    $today  = date('Y-m-d'); // comparaison lexicographique sur AAAA-MM-JJ = compare chronologiquement
    $scored = isset($m['score']) && $m['score'] !== null && $m['score'] !== '';
    $statut = $m['statut'] ?? 'en_cours';

    if ($statut === 'annule') {
        return ['key' => 'annule', 'label' => 'Annulé', 'class' => 'state-annule'];
    }
    if ($statut === 'reporte') {
        return ['key' => 'reporte', 'label' => 'Reporté', 'class' => 'state-reporte',
                'date_report' => !empty($m['datereporte']) ? format_date_fr($m['datereporte']) : null];
    }
    if ($scored && $date <= $today) {
        return $date === $today
            ? ['key' => 'termine', 'label' => 'Terminé', 'class' => 'state-joue']
            : ['key' => 'joue', 'label' => 'Déjà joué', 'class' => 'state-joue'];
    }
    if ($scored) {
        return ['key' => 'termine', 'label' => 'Terminé', 'class' => 'state-joue'];
    }
    if ($statut === 'termine') {
        return ['key' => 'termine', 'label' => 'Terminé', 'class' => 'state-joue'];
    }
    if ($date > $today) {
        return ['key' => 'avenir', 'label' => 'À venir', 'class' => 'state-avenir'];
    }
    if ($date < $today) {
        return ['key' => 'attente_score', 'label' => 'Score en attente', 'class' => 'state-encours'];
    }
    return ['key' => 'encours', 'label' => 'En cours', 'class' => 'state-encours'];
}

/**
 * Peut-on saisir le score de ce match ?
 * Non si le match est annule, reporte, si sa date est a venir, ou
 * surtout s'il a DEJA un score publie : dans ce cas le bouton Saisir
 * ne s'affiche plus du tout (le resultat est cloture).
 */
function saisie_score_autorisee(array $m): bool
{
    // Match deja joue avec un score : definitivement hors saisie.
    if (isset($m['score']) && $m['score'] !== null && $m['score'] !== '') {
        return false;
    }
    $statut = $m['statut'] ?? 'en_cours';
    if ($statut === 'annule' || $statut === 'reporte') {
        return false;
    }
    return (string) $m['datematch'] <= date('Y-m-d');
}

/**
 * Chemin du logo de l'équipe, avec une image de substitution si absente.
 * Pourquoi : jamais de <img> cassé dans l'interface.
 */
function team_logo(?string $photo, string $alt): string
{
    if ($photo !== null && $photo !== '' && basename($photo) === $photo
        && !str_contains($photo, '/') && !str_contains($photo, '\\')
        && file_exists(__DIR__ . '/../photos/' . $photo)) {
        return 'photos/' . rawurlencode($photo);
    }
    // Pastille SVG de substitution : initiale de l'équipe sur fond dégradé.
    $init = htmlspecialchars(
        mb_strtoupper(mb_substr($alt, 0, 1)),
        ENT_XML1 | ENT_QUOTES,
        'UTF-8'
    );
    $svg  = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64">'
          . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
          . '<stop offset="0" stop-color="#4cc9f0"/><stop offset="1" stop-color="#7c3aed"/>'
          . '</linearGradient></defs>'
          . '<rect width="64" height="64" rx="14" fill="url(#g)"/>'
          . '<text x="32" y="42" font-family="Arial" font-size="30" font-weight="700" '
          . 'text-anchor="middle" fill="#ffffff">' . $init . '</text></svg>';
    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
}
