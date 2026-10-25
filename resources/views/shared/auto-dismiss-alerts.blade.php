<script>
    document.querySelectorAll('.flash-success, .settings-alert-success').forEach((alert) => {
        window.setTimeout(() => {
            alert.style.transition = 'opacity 0.3s ease';
            alert.style.opacity = '0';

            window.setTimeout(() => alert.remove(), 300);
        }, 2500);
    });
</script>
