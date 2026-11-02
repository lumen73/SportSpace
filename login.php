<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';

if (is_authenticated()) {
    header('Location: index.php', true, 303);
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $emailInput = $_POST['email'] ?? '';
    $passwordInput = $_POST['password'] ?? '';
    $email = is_string($emailInput) ? strtolower(trim($emailInput)) : '';
    $password = is_string($passwordInput) ? $passwordInput : '';

    $stmt = $bd->prepare(
        'SELECT id, email, password_hash, role
         FROM users WHERE email = :email LIMIT 1'
    );
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        $error = 'Adresse email ou mot de passe invalide.';
    } else {
        session_regenerate_id(true);
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            if (is_string($newHash)) {
                $rehash = $bd->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
                $rehash->execute(['hash' => $newHash, 'id' => $user['id']]);
            }
        }
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];

        $stmt = $bd->prepare(
            'UPDATE users SET last_login_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $user['id']]);

        $redirect = $_SESSION['auth_redirect'] ?? 'index.php';
        unset($_SESSION['auth_redirect']);
        $redirectUrl = is_string($redirect) ? $redirect : '';
        $redirectPath = parse_url($redirectUrl, PHP_URL_PATH);
        $redirectQuery = parse_url($redirectUrl, PHP_URL_QUERY);
        $targetPage = is_string($redirectPath) ? basename($redirectPath) : '';
        $appDirectory = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        $expectedPath = ($appDirectory === '' ? '' : $appDirectory) . '/' . $targetPage;
        $allowedPages = ['index.php', 'matchdujour.php', 'scorematch.php'];
        if (in_array($user['role'], ['admin', 'agent'], true)) {
            $allowedPages = array_merge($allowedPages, ['creerequipe.php', 'ajoutermatch.php', 'ajouterscore.php']);
        }
        if ($user['role'] === 'admin') {
            $allowedPages[] = 'gestioncomptes.php';
        }

        $redirect = 'index.php';
        if (is_string($redirectPath) && $redirectPath === $expectedPath
            && in_array($targetPage, $allowedPages, true)) {
            $redirect = $targetPage;
            // Keep only validated public filters; never replay arbitrary query strings after login.
            $queryParams = [];
            if (is_string($redirectQuery)) {
                parse_str($redirectQuery, $queryParams);
            }
            if ($targetPage === 'matchdujour.php' && isset($queryParams['ladate'])
                && is_string($queryParams['ladate']) && is_valid_mysql_date($queryParams['ladate'])) {
                $redirect .= '?' . http_build_query(['ladate' => $queryParams['ladate']]);
            } elseif ($targetPage === 'scorematch.php' && isset($queryParams['idequipe'])
                && (is_string($queryParams['idequipe']) || is_int($queryParams['idequipe']))
                && filter_var($queryParams['idequipe'], FILTER_VALIDATE_INT) !== false
                && (int) $queryParams['idequipe'] > 0) {
                $redirect .= '?' . http_build_query(['idequipe' => (int) $queryParams['idequipe']]);
            }
        }
        header('Location: ' . $redirect, true, 303);
        exit;
    }
}

$page_title = 'Connexion | SportSpace';
$page_desc = 'Connexion sécurisée à SportSpace.';
$page_robots = 'noindex,nofollow';
$active_page = '';
require_once __DIR__ . '/includes/header.php';
?>
<main class="container" id="main-content">
    <section class="row justify-content-center" aria-labelledby="titre">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="glass section-card">
                <h1 id="titre" class="page-title h2 mb-4">Connexion</h1>
                <?php if ($flash = get_flash()): ?>
                    <div class="alert alert-success" role="status"><?= e($flash['message']) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
                <?php endif; ?>
                <form method="POST" action="login.php">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="email" class="form-label">Adresse email</label>
                        <input type="email" id="email" name="email" class="form-control" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label">Mot de passe</label>
                        <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
                    </div>
                    <button class="btn btn-neon w-100" type="submit">Se connecter</button>
                </form>
                <p class="mt-3 mb-0 text-muted">Pas encore de compte ? <a href="register.php">S’inscrire</a></p>
            </div>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
