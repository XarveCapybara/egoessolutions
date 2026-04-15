<?php
// Shared footer prototype (optional include)
?>
<script>
(function () {
  document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('eg-sidebar-toggle');
    if (!btn) return;

    btn.addEventListener('click', function () {
      var collapsed = document.body.classList.toggle('sidebar-collapsed');
      localStorage.setItem('eg_sidebar_collapsed', collapsed ? '1' : '0');
    });
  });
})();
</script>
