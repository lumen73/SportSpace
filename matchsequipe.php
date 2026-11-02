<?php
/**
 * STUB DE COMPATIBILITE
 * Cette page a ete fusionnee avec scorematch.php (Matchs & Scores).
 * Pourquoi garder ce fichier ? Les liens de favoris, historiques ou
 * e-mails peuvent pointer ici : rediriger evite le 404 frustrant.
 */
$target = 'scorematch.php';
if ($id = filter_input(INPUT_GET, 'idequipe', FILTER_VALIDATE_INT)) {
    $target .= '?idequipe=' . (int) $id;
}
header('Location: ' . $target);
exit;
