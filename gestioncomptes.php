<?php
/** Administration des comptes, réservée aux administrateurs. */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';
require_role('admin');

$roles = [
    'user' => 'Utilisateur',
    'agent' => 'Agent',
    'admin' => 'Administrateur',
];
$allowedRoles = ['user', 'agent', 'admin'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $accountId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';

    if ($accountId === false || $accountId < 1 || !is_string($action)) {
        set_flash('error', 'Compte ou action invalide.');
        header('Location: gestioncomptes.php', true, 303);
        exit;
    }

    try {
        $bd->beginTransaction();
        $stmt = $bd->prepare('SELECT id, email, role FROM users WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $accountId]);
        $account = $stmt->fetch();
        if (!$account) {
            throw new DomainException('Ce compte est introuvable.');
        }

        if ($action === 'changer_role') {
            $newRole = $_POST['role'] ?? null;
            if (!is_string($newRole) || !in_array($newRole, $allowedRoles, true)) {
                throw new DomainException('Le rôle demandé est invalide.');
            }
            if ($accountId === current_user_id() && $newRole !== 'admin') {
                throw new DomainException('Vous ne pouvez pas retirer votre propre rôle administrateur.');
            }

            if ($account['role'] === 'admin' && $newRole !== 'admin') {
                $admins = $bd->query("SELECT id FROM users WHERE role = 'admin' FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
                if (count($admins) <= 1) {
                    throw new DomainException('Le dernier administrateur ne peut pas être rétrogradé.');
                }
            }

            $stmt = $bd->prepare('UPDATE users SET role = :role WHERE id = :id');
            $stmt->execute(['role' => $newRole, 'id' => $accountId]);
            $bd->commit();
            set_flash('success', 'Rôle de ' . $account['email'] . ' mis à jour : ' . $roles[$newRole] . '.');
        } elseif ($action === 'supprimer_compte') {
            if ($accountId === current_user_id()) {
                throw new DomainException('Vous ne pouvez pas supprimer le compte administrateur connecté.');
            }
            if ($account['role'] === 'admin') {
                $admins = $bd->query("SELECT id FROM users WHERE role = 'admin' FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
                if (count($admins) <= 1) {
                    throw new DomainException('Le dernier compte administrateur ne peut pas être supprimé.');
                }
            }

            $stmt = $bd->prepare('DELETE FROM users WHERE id = :id');
            $stmt->execute(['id' => $accountId]);
            $bd->commit();
            set_flash('success', 'Le compte ' . $account['email'] . ' a été supprimé.');
        } else {
            throw new DomainException('Action inconnue.');
        }
    } catch (DomainException $e) {
        if ($bd->inTransaction()) {
            $bd->rollBack();
        }
        set_flash('error', $e->getMessage());
    } catch (Throwable $e) {
        if ($bd->inTransaction()) {
            $bd->rollBack();
        }
        error_log('Erreur gestion comptes SportSpace : ' . $e->getMessage());
        set_flash('error', 'La gestion de ce compte est temporairement indisponible.');
    }

    header('Location: gestioncomptes.php', true, 303);
    exit;
}

$accounts = $bd->query(
    "SELECT id, email, role, created_at, last_login_at
     FROM users
     ORDER BY FIELD(role, 'admin', 'agent', 'user'), email"
)->fetchAll();

$page_title = 'Gestion des comptes | SportSpace';
$page_desc = 'Gérer les comptes et les rôles de la plateforme SportSpace.';
$page_robots = 'noindex,nofollow';
$active_page = 'gestioncomptes.php';
require_once __DIR__ . '/includes/header.php';
?>

<main class="container" id="main-content">
    <section class="page-hero" aria-labelledby="titre">
        <span class="count-chip mb-2"><span class="status-dot" aria-hidden="true"></span>Administration</span>
        <h1 id="titre" class="page-title mb-1">Gestion des <span class="grad-text">comptes</span></h1>
        <p class="page-subtitle mb-0">Attribuez les rôles et gérez les accès à la plateforme.</p>
    </section>

    <?php if ($flash = get_flash()): ?>
        <div class="alert alert-<?= e($flash['type'] === 'error' ? 'danger' : 'success') ?> mt-4" role="status">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>

    <section class="glass section-card mt-4" aria-labelledby="liste-comptes">
        <h2 id="liste-comptes" class="h5 mb-3">Comptes inscrits <span class="count-chip ms-1"><?= count($accounts) ?></span></h2>
        <div class="table-responsive">
            <table class="table table-modern align-middle mb-0">
                <caption class="visually-hidden">Comptes inscrits, rôles et actions réservées à l'administrateur.</caption>
                <thead>
                    <tr>
                        <th scope="col">Adresse email</th>
                        <th scope="col">Rôle</th>
                        <th scope="col">Inscription</th>
                        <th scope="col">Dernière connexion</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accounts as $account): ?>
                        <?php $isCurrentAccount = (int) $account['id'] === current_user_id(); ?>
                        <tr>
                            <td class="fw-semibold"><?= e($account['email']) ?><?= $isCurrentAccount ? ' <span class="text-muted small">(vous)</span>' : '' ?></td>
                            <td><?= e($roles[$account['role']] ?? 'Inconnu') ?></td>
                            <td><?= e($account['created_at']) ?></td>
                            <td><?= e($account['last_login_at'] ?? 'Jamais') ?></td>
                            <td class="text-end text-nowrap">
                                <?php if ($isCurrentAccount): ?>
                                    <span class="text-muted small">Compte protégé</span>
                                <?php else: ?>
                                    <form method="POST" action="gestioncomptes.php" class="d-inline-flex gap-1 align-items-center">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $account['id'] ?>">
                                        <label class="visually-hidden" for="role-<?= (int) $account['id'] ?>">Rôle de <?= e($account['email']) ?></label>
                                        <select id="role-<?= (int) $account['id'] ?>" name="role" class="form-select form-select-sm">
                                            <?php foreach ($roles as $role => $label): ?>
                                                <option value="<?= e($role) ?>" <?= $account['role'] === $role ? 'selected' : '' ?>><?= e($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="action" value="changer_role" class="btn btn-warn-neon btn-sm">Enregistrer</button>
                                    </form>
                                    <form method="POST" action="gestioncomptes.php" class="d-inline" data-confirm="Supprimer définitivement le compte <?= e($account['email']) ?> ?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $account['id'] ?>">
                                        <button type="submit" name="action" value="supprimer_compte" class="btn btn-danger-neon btn-sm">Supprimer</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($accounts)): ?>
                        <tr><td colspan="5" class="text-center text-muted">Aucun compte inscrit.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
