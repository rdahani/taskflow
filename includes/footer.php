  </main><!-- fin .app-content -->
</div><!-- fin .app-main -->
</div><!-- fin .app-layout -->

<div id="tf-loading-bar" class="tf-loading-bar" aria-hidden="true"></div>
<script>
if ('serviceWorker' in navigator && location.protocol.startsWith('http')) {
  navigator.serviceWorker.register('<?= rtrim(APP_URL, '/') ?>/sw.js').catch(function () {});
}
</script>
</body>
</html>
