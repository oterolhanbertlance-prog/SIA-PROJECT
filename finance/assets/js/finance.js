(function () {
    "use strict";
    function applyTheme(theme) {
        var isDark = theme === "dark";
        document.documentElement.dataset.theme = isDark ? "dark" : "light";
        document.querySelectorAll("[data-theme-toggle]").forEach(function (button) {
            var icon = button.querySelector(".bi");
            if (icon) {
                icon.classList.toggle("bi-sun", isDark);
                icon.classList.toggle("bi-moon-stars", !isDark);
            }
            button.setAttribute("aria-pressed", isDark ? "true" : "false");
            button.setAttribute("title", isDark ? "Use light mode" : "Use dark mode");
        });
    }
    var initialTheme = document.documentElement.dataset.theme === "dark" ? "dark" : "light";
    applyTheme(initialTheme);
    document.querySelectorAll("[data-theme-toggle]").forEach(function (button) {
        button.addEventListener("click", function () {
            var nextTheme = document.documentElement.dataset.theme === "dark" ? "light" : "dark";
            document.documentElement.dataset.theme = nextTheme;
            applyTheme(nextTheme);
            try { localStorage.setItem("finance-theme", nextTheme); } catch (error) {}
        });
    });
    document.querySelectorAll("[data-chart]").forEach(function (el) {
        try { el.dataset.ready = "true"; } catch (error) { /* visual-only enhancement */ }
    });
    var resetModal = document.getElementById("resetPasswordModal");
    if (resetModal) {
        resetModal.addEventListener("show.bs.modal", function (event) {
            var button = event.relatedTarget;
            if (!button) return;
            var id = button.getAttribute("data-user-id");
            var name = button.getAttribute("data-user-name");
            var idField = document.getElementById("reset-user-id");
            var nameField = document.getElementById("reset-user-name");
            if (idField) idField.value = id || "";
            if (nameField) nameField.textContent = name || "this user";
        });
    }

    /* Keep sidebar at the same scroll position when opening another page. */
    var financeNav = document.querySelector(".nav-stack");
    if (financeNav) {
        var navKey = "finance-sidebar-scroll";
        try { financeNav.scrollTop = parseInt(sessionStorage.getItem(navKey) || "0",10) || 0; } catch(error) {}
        function saveFinanceNavScroll(){ try { sessionStorage.setItem(navKey,String(financeNav.scrollTop)); } catch(error) {} }
        financeNav.addEventListener("scroll",saveFinanceNavScroll,{passive:true});
        financeNav.querySelectorAll("a.nav-link").forEach(function(link){link.addEventListener("click",saveFinanceNavScroll);});
        window.addEventListener("pagehide",saveFinanceNavScroll);
        var activeNav=financeNav.querySelector(".nav-link.active");
        if(activeNav) requestAnimationFrame(function(){
            var n=financeNav.getBoundingClientRect(),a=activeNav.getBoundingClientRect();
            if(a.top<n.top||a.bottom>n.bottom) activeNav.scrollIntoView({block:"nearest"});
        });
    }

    window.setTimeout(function () {
        document.querySelectorAll(".alert").forEach(function (el) {
            if (el.classList.contains("alert-success")) {
                var alert = bootstrap.Alert.getOrCreateInstance(el);
                window.setTimeout(function () { alert.close(); }, 5000);
            }
        });
    }, 100);
})();