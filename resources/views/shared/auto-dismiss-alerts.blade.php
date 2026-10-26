<script>
    document.querySelectorAll('.flash-success, .settings-alert-success').forEach(function (alert) {
        window.setTimeout(function () {
            alert.style.transition = 'opacity 0.3s ease';
            alert.style.opacity = '0';

            window.setTimeout(function () {
                alert.remove();
            }, 300);
        }, 2500);
    });
</script>
