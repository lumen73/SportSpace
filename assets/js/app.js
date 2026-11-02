/* =========================================================================
   SportSpace — app.js
   Chargé avec l'attribut defer : le DOM est prêt, aucun blocage du rendu.
   Vanilla JS uniquement, pattern IIFE pour ne pas polluer la portée globale.
   ========================================================================= */
(function () {
    'use strict';

    /* ------------------------------------------------------------------
     * 0. Navigation mobile sans dépendance externe
     * ------------------------------------------------------------------ */
    var navToggle = document.querySelector('.navbar-toggler');
    var navMenu = document.querySelector('#mainNav');
    if (navToggle && navMenu) {
        navToggle.addEventListener('click', function () {
            var isOpen = navMenu.classList.toggle('show');
            navToggle.setAttribute('aria-expanded', String(isOpen));
            navToggle.setAttribute('aria-label', isOpen ? 'Fermer le menu' : 'Ouvrir le menu');
        });

        navMenu.querySelectorAll('.nav-link').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.matchMedia('(max-width: 1199.98px)').matches) {
                    navMenu.classList.remove('show');
                    navToggle.setAttribute('aria-expanded', 'false');
                    navToggle.setAttribute('aria-label', 'Ouvrir le menu');
                }
            });
        });
    }

    /* ------------------------------------------------------------------
     * 1. SKELETON SCREENS sur les formulaires de filtrage
     * ------------------------------------------------------------------
     * Pourquoi : pendant l'aller-retour du GET (le serveur regenere
     * la page), on affiche une structure de squelettes plutot qu'un
     * ecran fige. Les vraies donnees remplacent les squelettes au
     * chargement suivant.
     */
    document.querySelectorAll('form[data-skeleton]').forEach(function (form) {
        var target = document.querySelector(form.dataset.skeleton);

        form.addEventListener('submit', function () {
            if (!target) { return; }

            var from = document.querySelectorAll(
                form.dataset.skeletonSource || '.match-card, .team-card'
            );

            // On fige la largeur des elements existants pour un rendu stable
            var ghosts = [];
            from.forEach(function (el) {
                var ghost = document.createElement('div');
                ghost.className = el.className;
                ghost.innerHTML =
                    '<div class="match-teams">' +
                    '<div class="skeleton skeleton-line" style="width:70%"></div>' +
                    '<div class="skeleton skeleton-line" style="width:55%"></div>' +
                    '</div>' +
                    '<div class="skeleton skeleton-badge"></div>';
                el.parentNode.replaceChild(ghost, el);
                ghosts.push([ghost, el]);
            });

            if (!ghosts.length) {
                for (var i = 0; i < 4; i++) {
                    var g = document.createElement('div');
                    g.className = 'glass mb-3 match-card';
                    g.innerHTML =
                        '<div class="skeleton skeleton-line" style="width:90%"></div>' +
                        '<div class="skeleton skeleton-badge"></div>';
                    target.prepend(g);
                    ghosts.push([g, null]);
                }
            }
        });
    });

    /* ------------------------------------------------------------------
     * 2. Anti-doublon : une même équipe ne peut pas jouer contre elle-même
     * ------------------------------------------------------------------
     * Pourquoi : la validation serveur reste la reference (fiable), mais
     * ce script evite a l'utilisateur un aller-retour inutile.
     */
    var formAddMatch = document.querySelector('#form-add-match');
    if (formAddMatch) {
        var team1 = formAddMatch.querySelector('[name="idequipe1"]');
        var team2 = formAddMatch.querySelector('[name="idequipe2"]');

        function syncTeams() {
            var excluded = team1.value;
            team2.querySelectorAll('option').forEach(function (option) {
                var disabled = option.value !== '' && option.value === excluded;
                option.disabled = disabled;
                if (disabled && option.selected) { team2.value = ''; }
            });
        }
        team1.addEventListener('change', syncTeams);
        syncTeams();
    }

    /* ------------------------------------------------------------------
     * 3. Confirmation des actions destructives (suppressions)
     * ------------------------------------------------------------------
     * Pourquoi : on remplace le confirm inline HTML (mélanger logique
     * et markup) par du JS propre, avec messages cohérents a11y.
     */
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('submit', function (e) {
            if (!window.confirm(el.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });

    /* ------------------------------------------------------------------
     * 4. Toggle "date de report" : visible uniquement si statut = reporte
     * ------------------------------------------------------------------
     * Pourquoi : un match reporte DOIT contenir sa prochaine date ;
     * masquer le champ des qu'il n'est pas pertinent evite les erreurs.
     */
    var statutSelect = document.getElementById('statut');
    var reportWrap   = document.getElementById('datereporte-wrap');

    function toggleReport() {
        if (statutSelect && reportWrap) {
            reportWrap.style.display = statutSelect.value === 'reporte' ? '' : 'none';
        }
    }
    if (statutSelect && reportWrap) {
        statutSelect.addEventListener('change', toggleReport);
        toggleReport(); // etat initial (mode edition : reporte pre-selectionne)
    }

    // Un score renseigne doit porter le statut a termine. Si le score est
    // efface pendant l'edition, le statut revient a en_cours.
    var scoreInput = document.getElementById('score');
    if (scoreInput && statutSelect) {
        scoreInput.addEventListener('input', function () {
            if (scoreInput.value.trim() !== '') {
                statutSelect.value = 'termine';
            } else if (statutSelect.value === 'termine') {
                statutSelect.value = 'en_cours';
            }
            toggleReport();
        });
    }

    /* ------------------------------------------------------------------
     * 5. Fermeture auto des flashs après 4,5 s
     * ------------------------------------------------------------------ */
    var alerts = document.querySelectorAll('.flash-alert');
    if (alerts.length) {
        setTimeout(function () {
            alerts.forEach(function (alertNode) {
                alertNode.classList.remove('show');
                setTimeout(function () { alertNode.remove(); }, 180);
            });
        }, 4500);
    }

    document.querySelectorAll('.alert .btn-close').forEach(function (button) {
        button.addEventListener('click', function () {
            var alertNode = button.closest('.alert');
            if (alertNode) { alertNode.remove(); }
        });
    });
})();
