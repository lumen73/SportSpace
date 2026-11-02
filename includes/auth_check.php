<?php
/** Middleware d'accès aux pages privées. */

function is_authenticated(): bool
{
    return isset($_SESSION['user_id']) && filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT) !== false;
}

function require_authentication(): void
{
    if (is_authenticated()) {
        return;
    }

    $_SESSION['auth_redirect'] = $_SERVER['REQUEST_URI'] ?? '/';
    header('Location: login.php', true, 303);
    exit;
}

function require_role(string|array $roles): void
{
    require_authentication();
    $allowedRoles = is_array($roles) ? array_values(array_unique($roles)) : [$roles];

    $userId = current_user_id();
    $bd = $GLOBALS['bd'] ?? null;
    if ($userId === null || !($bd instanceof PDO)) {
        http_response_code(503);
        exit('Service temporairement indisponible.');
    }

    try {
        $stmt = $bd->prepare('SELECT role FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $storedRole = $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('Erreur de verification du role SportSpace : ' . $e->getMessage());
        http_response_code(503);
        exit('Service temporairement indisponible.');
    }

    if (!is_string($storedRole)) {
        unset($_SESSION['user_id'], $_SESSION['user_email'], $_SESSION['user_role']);
        header('Location: login.php', true, 303);
        exit;
    }

    // Revalide le role depuis la base a chaque route sensible : une
    // retrogradation prend effet sans attendre l'expiration de la session.
    $_SESSION['user_role'] = $storedRole;
    if (!in_array($storedRole, $allowedRoles, true)) {
        http_response_code(403);
        exit('Accès interdit.');
    }
}

function current_user_id(): ?int
{
    $id = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
    return $id === false ? null : (int) $id;
}

function current_user_role(): ?string
{
    return $_SESSION['user_role'] ?? null;
}
