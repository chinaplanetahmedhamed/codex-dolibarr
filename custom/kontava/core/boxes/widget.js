if (window.kontavaWidgetLoaded) {
    // console.log("widget.js déjà chargé, on arrête.");
} else {
    window.kontavaWidgetLoaded = true;
    // console.log("Chargement unique du script widget.js !");

    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll(".widget-container").forEach(function (widget) {
            let title = widget.querySelector(".widget-title");
            let arrow = widget.querySelector(".widget-arrow");

            // Trouver le parent commun (ici, <tbody> ou autre contenant les deux)
            let commonAncestor = widget.closest("tbody"); // Ajuste si nécessaire
            if (!commonAncestor) {
                console.warn("⚠️ Impossible de trouver le parent commun !");
                return;
            }

            // Trouver le premier ".widget-content" dans ce parent commun --> SI WIDGET A UNE SEULE LIGNE !!
            let isMultiLine = false;
            let contentRows = Array.from(commonAncestor.querySelectorAll(".widget-content"));

            if (contentRows.length === 0) {
                // Trouver tous les ".widget-tr-content" dans ce parent commun --> SI WIDGET A PLUSIEURS LIGNES !!
                contentRows = Array.from(commonAncestor.querySelectorAll(".widget-tr-content"));
                isMultiLine = true;
            }
            if (contentRows.length === 0 || !arrow) {
                console.warn("⚠️ Contenus ou flèche introuvable !");
                return;
            }
            
            // let contentRows = Array.from(commonAncestor.querySelectorAll(".widget-content"));
            // console.warn("Pour widget "+title+", avec widget-content, taille des results = "+contentRows.length);
            // if (contentRows.length === 0 || !arrow) {
                
            //     let contentRows = Array.from(commonAncestor.querySelectorAll(".widget-tr-content"));
            //     console.warn("Pour widget "+title+", avec widget-tr-content, taille des results = "+contentRows.length);

            // }

            // Vérifier si caché au chargement
            // if (!content.classList.contains("hidden")) {
            //     arrow.style.transform = "rotate3d(1, 0, 0, -180deg) translate(0px, 6px)";
            // }
            // Init flèche si ouvert
            if (!contentRows[0].classList.contains("hidden")) {
                arrow.style.transform = "rotate3d(1, 0, 0, -180deg) translate(0px, 6px)";
            }

            // Ajout de l'événement de clic sur le widget
            widget.addEventListener("click", function () {
                // let isHidden = content.classList.contains("hidden");
                let isHidden = contentRows[0].classList.contains("hidden");
                // let expanding = contents[0].style.maxHeight === "0px" || !contents[0].style.maxHeight;

                // Supprime la classe 'hidden' temporairement pour mesurer la hauteur
                // content.classList.remove("hidden");
                let fullHeight = (isMultiLine?"30px":((isHidden)?"900px":contentRows[0].scrollHeight + "px"));//content.scrollHeight + "px";
                if (isHidden) {
                    // Déplier
                    contentRows.forEach(row => {
                        row.classList.remove("hidden");
                        row.style.maxHeight = "0px"; // ← état de départ
                        // Forcer le reflow
                        row.offsetHeight; // 🔥 Hack magique
                        row.style.maxHeight = fullHeight;
                        // Pour ne redimensionner que les graphiques du widget en cours au moment de son démasquage pour pas qu'il soient tout petits
                        // on cherche les conteneurs de graphique à l'intérieur du widget parent.
                        // On a pris soin d'affecter la classe "echart-instance" aux div qui contiennent des graphique echart.
                        let charts = row.querySelectorAll('.echart-instance'); // classe que tu appliques aux graphiques
                        charts.forEach(chart => {
                            if (charts.length === 0) 
                                {console.warn("je ne trouve pas la classe echart-instance");}
                            else {
                                let id = chart.id;
                                let myChart = window.echartsInstances?.[id];

                                if (myChart) {
                                    console.warn("🎯 Je resize le graph avec id: " + id);
                                    // setTimeout(() => {
                                        myChart.resize();
                                    // }, 10);
                                } else {
                                    console.warn("⚠️ Aucun chart enregistré pour id: " + id);
                                }
                            }
                        });
                    });
                } else {
                    // Enrouler
                    contentRows.forEach(row => {
                        row.style.maxHeight = "0px";
                        setTimeout(() => {//on attend la fin de l'animation pour appliquer la classe sinon ça casse l'animation
                            row.classList.add("hidden");
                        }, (isMultiLine?100:500));
                    });
                }

                // Rotation de la flèche
                arrow.style.transform = isHidden
                    ? "rotate3d(1, 0, 0, -180deg) translate(0px, 6px)"
                    : "rotate(0deg)";
            
                // ---- Envoie des données au backend comme avant ----
                let box_id = widget.dataset.boxId;
                let zone = widget.dataset.zone;
                let unfold = isHidden ? 1 : 0;
                let fk_user = widget.dataset.userId;
                let csrf_token = document.querySelector('meta[name="anti-csrf-newtoken"]').getAttribute("content");
            
                if (!box_id || !fk_user) {
                    console.warn("⚠️ L'ID du widget ou de l'utilisateur est manquant !");
                    return;
                }
            
                // Construire l'URL dynamiquement :
                // 1) préférer l'attribut data-dol-url-root du widget (pas de tooltip)
                // 2) fallback sur window.DOL_URL_ROOT si injecté globalement
                // 3) fallback sur racine ('') -> '/custom/..' fonctionnera
                let dolibarrRoot = widget.dataset.dolUrlRoot || window.DOL_URL_ROOT || '';
                // Retirer les slashs finaux pour éviter les doubles
                dolibarrRoot = dolibarrRoot.replace(/\/+$/g, '');
                const updateWidgetUrl = (dolibarrRoot ? dolibarrRoot : '') + '/custom/kontava/core/class/update_widget.php';
                
                fetch(updateWidgetUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        'box_id': box_id,
                        'zone': zone,
                        'fk_user': fk_user,
                        'unfold': unfold,
                        'token': csrf_token
                    })
                })
                    .then(response => response.text())
                    .then(data => console.log("Sauvegarde état widget :", data))
                    .catch(error => console.error("Erreur lors de la sauvegarde du widget:", error));
            });
        });
    });
}

// console.log("widget.js est bien chargé !");
