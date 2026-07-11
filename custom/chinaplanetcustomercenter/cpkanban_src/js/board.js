/* China Planet Kanban - board drag & drop */
(function () {
    "use strict";

    document.addEventListener("DOMContentLoaded", function () {
        var wrap = document.querySelector(".cpk-wrap");
        if (!wrap) return;

        var canWrite = wrap.getAttribute("data-canwrite") === "1";
        if (!canWrite) { setupCustomerCardDialog(wrap); return; }

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
    function setupCustomerCardDialog(wrap){var dialog=document.getElementById("cpk-card-dialog"),frame=dialog&&dialog.querySelector(".cpk-dialog-frame"),closeButton=dialog&&dialog.querySelector(".cpk-dialog-close"),lastTrigger=null;if(!dialog||typeof dialog.showModal!=="function")return;function currentId(){return new URL(location.href).searchParams.get("card");}function findLink(id){return Array.prototype.find.call(wrap.querySelectorAll("a.cpk-card-link"),function(a){return a.getAttribute("data-id")===String(id);});}function openCard(link,push){if(!link)return;lastTrigger=link;var modalUrl=new URL(link.href);modalUrl.searchParams.set("modal","1");frame.src=modalUrl.toString();if(!dialog.open)dialog.showModal();closeButton.focus();if(push){var u=new URL(location.href),state=Object.assign({},history.state||{});u.searchParams.set("card",link.getAttribute("data-id"));state.cpkCard=link.getAttribute("data-id");state.cpkModalEntry=true;history.pushState(state,"",u);}}function finishClose(){if(dialog.open)dialog.close();frame.removeAttribute("src");if(lastTrigger)lastTrigger.focus();}function closeCard(fromHistory){if(fromHistory){finishClose();return;}if(history.state&&history.state.cpkModalEntry&&currentId()){history.back();return;}var u=new URL(location.href),state=Object.assign({},history.state||{});u.searchParams.delete("card");delete state.cpkCard;delete state.cpkModalEntry;history.replaceState(state,"",u);finishClose();}wrap.querySelectorAll("a.cpk-card-link").forEach(function(link){link.addEventListener("click",function(e){if(e.button!==0||e.ctrlKey||e.metaKey||e.shiftKey||e.altKey)return;e.preventDefault();openCard(link,true);});});closeButton.addEventListener("click",function(){closeCard(false);});dialog.addEventListener("cancel",function(e){e.preventDefault();closeCard(false);});document.addEventListener("keydown",function(e){if(e.key==="Escape"&&dialog.open){e.preventDefault();closeCard(false);}},true);dialog.addEventListener("click",function(e){if(e.target===dialog)closeCard(false);});addEventListener("popstate",function(){var id=currentId(),link=id&&findLink(id);if(!link){closeCard(true);return;}openCard(link,false);});var initial=currentId(),initialLink=initial&&findLink(initial);if(initialLink){var state=Object.assign({},history.state||{});state.cpkCard=initial;state.cpkModalEntry=false;history.replaceState(state,"",location.href);openCard(initialLink,false);}}
})();
