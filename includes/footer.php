<?php
/**
 * FOOTER GLOBAL — unique point de maintenance des mentions de pied de page.
 */
$year = date('Y');
?>
<footer class="site-footer mt-5" role="contentinfo">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 py-4">
            <span class="footer-brand">
                Sport<span class="grad-text">Space</span>
                <small class="d-block text-muted">Tableau de bord de suivi sportif</small>
            </span>
            <span class="text-muted small">
                &copy; <?= $year ?> &mdash; Tous droits réservés &middot;
                <span aria-hidden="true">&middot;</span> by Lum3n Tech @2026
            </span>
        </div>
    </div>
</footer>
</body>
</html>
