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
    $confirmationInput = $_POST['password_confirmation'] ?? '';
    $email = is_string($emailInput) ? strtolower(trim($emailInput)) : '';
    $password = is_string($passwordInput) ? $passwordInput : '';
    $confirmation = is_string($confirmationInput) ? $confirmationInput : '';

    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } elseif (mb_strlen($password, 'UTF-8') < 12 || !preg_match('/[A-Z]/', $password) || !preg_match('/\d/', $password)) {
        $error = 'Le mot de passe doit contenir 12 caractères, une majuscule et un chiffre.';
    } elseif (!hash_equals($password, $confirmation)) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        try {
            $stmt = $bd->prepare(
                'INSERT INTO users (email, password_hash, role)
                 VALUES (:email, :password_hash, \'user\')'
            );
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            if (!is_string($passwordHash)) {
                throw new RuntimeException('Impossible de sécuriser le mot de passe.');
            }
            $stmt->execute([
                'email' => $email,
                'password_hash' => $passwordHash,
            ]);
            set_flash('success', 'Compte créé. Vous pouvez maintenant vous connecter.');
            header('Location: login.php', true, 303);
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' && (int) ($e->errorInfo[1] ?? 0) === 1062) {
                $error = 'Cette adresse email est déjà utilisée.';
            } else {
                error_log('Erreur inscription SportSpace : ' . $e->getMessage());
                $error = 'Inscription temporairement indisponible.';
            }
        } catch (Throwable $e) {
            error_log('Erreur inscription SportSpace : ' . $e->getMessage());
            $error = 'Inscription temporairement indisponible.';
        }
    }
}

$page_title = 'Créer un compte | SportSpace';
$page_desc = 'Créer un compte SportSpace.';
$page_robots = 'noindex,nofollow';
$active_page = '';
require_once __DIR__ . '/includes/header.php';
?>
<main class="container" id="main-content">
    <section class="row justify-content-center" aria-labelledby="titre">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="glass section-card">
                <h1 id="titre" class="page-title h2 mb-4">Créer un compte</h1>
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
                <?php endif; ?>
                <form method="POST" action="register.php">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="email" class="form-label">Adresse email</label>
                        <input type="email" id="email" name="email" class="form-control" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Mot de passe</label>
                        <input type="password" id="password" name="password" class="form-control" required minlength="12" autocomplete="new-password">
                    </div>
                    <div class="mb-4">
                        <label for="password_confirmation" class="form-label">Confirmation</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required minlength="12" autocomplete="new-password">
                    </div>
                    <button class="btn btn-neon w-100" type="submit">Créer le compte</button>
                </form>
                <p class="mt-3 mb-0 text-muted">Déjà inscrit ? <a href="login.php">Se connecter</a></p>
            </div>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
