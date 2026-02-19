        </main>
    </div>

    <script>
        document.getElementById('adminSidebarToggle')?.addEventListener('click', function() {
            document.body.classList.toggle('admin-sidebar-open');
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') document.body.classList.remove('admin-sidebar-open');
        });
    </script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/toasts.js"></script>
    <script src="../assets/js/realtime.js"></script>
    <script>
        (function() {
            if (window.JamesRealtime) window.JamesRealtime.auth(null, '<?= (isset($_SESSION["admin"]) && $_SESSION["admin"] === true) ? "admin" : "staff" ?>');
            if (window.JamesRealtime) {
                window.JamesRealtime.on('notification', function(p) {
                    if (p && p.title && window.JamesToasts) window.JamesToasts.notify(p.title, p.body || '', p.type || 'info');
                });
            }
            // Approve/Reject and all data-confirm buttons: show confirm then submit form
            function bindConfirmButtons() {
                document.querySelectorAll('button[data-confirm], input[data-confirm]').forEach(function(btn) {
                    if (btn._jamesConfirmBound) return;
                    btn._jamesConfirmBound = true;
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        var form = btn.closest('form');
                        if (!form) return;
                        var msg = btn.getAttribute('data-confirm') || 'Continue?';
                        function doSubmit() {
                            if (btn.name && btn.value !== undefined) {
                                var h = document.createElement('input');
                                h.type = 'hidden';
                                h.name = btn.name;
                                h.value = btn.value;
                                form.appendChild(h);
                            }
                            form.submit();
                        }
                        if (window.JamesToasts && typeof window.JamesToasts.confirm === 'function') {
                            window.JamesToasts.confirm(msg).then(function(y) { if (y) doSubmit(); });
                        } else {
                            if (confirm(msg)) doSubmit();
                        }
                    });
                });
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bindConfirmButtons);
            } else {
                bindConfirmButtons();
            }
            setTimeout(bindConfirmButtons, 500);
            // Toast when page loads with success message (e.g. after approve/reject)
            var q = window.location.search || '';
            if (q.indexOf('msg=approved') !== -1 && window.JamesToasts) window.JamesToasts.success('Request approved.');
            if (q.indexOf('msg=rejected') !== -1 && window.JamesToasts) window.JamesToasts.success('Request rejected.');
        })();
    </script>
    <?php if (!empty($adminExtraScript)) echo $adminExtraScript; ?>
</body>
</html>
