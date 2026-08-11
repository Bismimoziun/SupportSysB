<?php
require_once __DIR__ . '/includes/auth_guard.php';
guard_require_login();

$token = csrf_generate();
?>
<!DOCTYPE html>
<html>
<body>
<h2>Test Re-index</h2>
<p>CSRF Token: <code><?= htmlspecialchars($token) ?></code></p>
<button onclick="testReindex()">Test Re-index</button>
<pre id="result">Waiting...</pre>

<script>
function testReindex() {
    const body = new URLSearchParams({
        action:     'ingest',
        csrf_token: '<?= htmlspecialchars($token) ?>',
    });

    fetch('/gg-support/includes/ai_chat.php', {
        method:      'POST',
        credentials: 'same-origin',
        headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:        body.toString(),
    })
    .then(r => r.text())
    .then(t => { document.getElementById('result').textContent = t; })
    .catch(e => { document.getElementById('result').textContent = 'Error: ' + e; });
}
</script>
</body>
</html>