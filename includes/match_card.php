<?php
/**
 * PARTIEL : carte d'un match avec son etat et son score.
 * Attend : $m (idmatch, datematch, score, statut, datereporte, noms + photos).
 * Pourquoi un partiel : evite de dupliquer le markup dans 3 pages ;
 * toute evolution visuelle future se fait a UN endroit.
 */
$state = match_state($m);
?>
<article class="match-card w-100" aria-label="Match <?= e($m['equipe1_nom']) ?> contre <?= e($m['equipe2_nom']) ?>">
    <div class="match-date-wrap">
        <span class="match-date"><?= e(format_date_fr($m['datematch'])) ?></span>
    </div>
    <div class="match-teams">
        <span class="match-team">
            <img src="<?= e(team_logo($m['photo1'] ?? null, $m['equipe1_nom'])) ?>" alt="Drapeau de <?= e($m['equipe1_nom']) ?>" loading="lazy" decoding="async" width="30" height="30">
            <?= e($m['equipe1_nom']) ?>
        </span>
        <span class="match-team">
            <img src="<?= e(team_logo($m['photo2'] ?? null, $m['equipe2_nom'])) ?>" alt="Drapeau de <?= e($m['equipe2_nom']) ?>" loading="lazy" decoding="async" width="30" height="30">
            <?= e($m['equipe2_nom']) ?>
        </span>
    </div>
    <div class="d-flex flex-column align-items-end gap-1">
        <!-- Etat du match (en cours / termine / reporte / annule / deja joue) -->
        <span class="status-chip <?= e($state['class']) ?>">
            <span class="status-dot" aria-hidden="true"></span><?= e($state['label']) ?>
            <?php if (!empty($state['date_report'])): ?> &middot; <?= e($state['date_report']) ?><?php endif; ?>
        </span>
        <!-- Score : affiche des qu'il existe ; sinon pastille d'attente -->
        <?php if (isset($m['score']) && $m['score'] !== null && $m['score'] !== ''): ?>
            <span class="match-score"><?= e($m['score']) ?></span>
        <?php else: ?>
            <span class="match-score pending" title="Score non renseigne">&ndash;</span>
        <?php endif; ?>
    </div>
</article>
