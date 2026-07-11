/* China Planet Kanban - board drag & drop */
(function () {
    "use strict";

    document.addEventListener("DOMContentLoaded", function () {
        var wrap = document.querySelector(".cpk-wrap");
        if (!wrap) return;

        var canWrite = wrap.getAttribute("data-canwrite") === "1";
        if (!canWrite) return; // customers: view only, no DnD wiring

        var ajaxUrl = wrap.getAttribute("data-ajax");
        var token = wrap.getAttribute("data-token");
        var dragId = null;

        var cards = wrap.querySelectorAll(".cpk-card[draggable='true']");
        var cols = wrap.querySelectorAll(".cpk-col");

        cards.forEach(function (card) {
            card.addEventListener("dragstart", function (e) {
                dragId = card.getAttribute("data-id");
                e.dataTransfer.effectAllowed = "move";
                setTimeout(function () { card.style.opacity = "0.4"; }, 0);
            });
            card.addEventListener("dragend", function () {
                card.style.opacity = "1";
                cols.forEach(function (c) { c.classList.remove("cpk-drop-hot"); });
            });
        });

        cols.forEach(function (col) {
            col.addEventListener("dragover", function (e) {
                e.preventDefault();
                col.classList.add("cpk-drop-hot");
                e.dataTransfer.dropEffect = "move";
            });
            col.addEventListener("dragleave", function () {
                col.classList.remove("cpk-drop-hot");
            });
            col.addEventListener("drop", function (e) {
                e.preventDefault();
                col.classList.remove("cpk-drop-hot");
                if (!dragId) return;
                var newCol = col.getAttribute("data-col");
                moveCard(dragId, newCol, col);
            });
        });

        function moveCard(cardId, colId, colEl) {
            var body = "card_id=" + encodeURIComponent(cardId) +
                       "&col_id=" + encodeURIComponent(colId) +
                       "&token=" + encodeURIComponent(token);

            fetch(ajaxUrl, {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: body,
                credentials: "same-origin"
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.success) {
                    var cardEl = wrap.querySelector('.cpk-card[data-id="' + cardId + '"]');
                    var target = colEl.querySelector(".cpk-cards");
                    if (cardEl && target) {
                        var empty = target.querySelector(".cpk-empty");
                        if (empty) empty.remove();
                        target.appendChild(cardEl);
                        // update progress number + bar
                        if (typeof data.progress !== "undefined") {
                            var num = cardEl.querySelector(".cpk-prog-num");
                            var bar = cardEl.querySelector(".cpk-progress span");
                            if (num) num.textContent = data.progress + "%";
                            if (bar) bar.style.width = data.progress + "%";
                        }
                    }
                    refreshCounts();
                    toast("✓ تم نقل البطاقة وتحديث التقدّم");
                } else {
                    toast("تعذّر نقل البطاقة: " + (data ? data.message : "خطأ"), true);
                }
            })
            .catch(function () { toast("خطأ في الاتصال", true); });
        }

        function refreshCounts() {
            cols.forEach(function (col) {
                var n = col.querySelectorAll(".cpk-card").length;
                var badge = col.querySelector(".cpk-count");
                if (badge) badge.textContent = n;
                var cardsBox = col.querySelector(".cpk-cards");
                if (n === 0 && cardsBox && !cardsBox.querySelector(".cpk-empty")) {
                    var d = document.createElement("div");
                    d.className = "cpk-empty";
                    d.textContent = "لا توجد بطاقات";
                    cardsBox.appendChild(d);
                }
            });
        }

        var tt;
        function toast(msg, isErr) {
            var t = document.getElementById("cpk-toast");
            if (!t) {
                t = document.createElement("div");
                t.id = "cpk-toast";
                document.body.appendChild(t);
            }
            t.textContent = msg;
            t.className = "show" + (isErr ? " err" : "");
            clearTimeout(tt);
            tt = setTimeout(function () { t.className = ""; }, 2600);
        }
    });
})();
