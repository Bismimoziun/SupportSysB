<?php
// ============================================================
//  G&G Support Portal— user_home.php
// ============================================================

require_once __DIR__ . '/includes/auth_guard.php';
guard_require_login();

$page_title = 'Search Solutions';

// Fetch top 3 active announcements
$announcements = $pdo->query(
    'SELECT title, content FROM announcements WHERE is_active = 1 ORDER BY priority ASC LIMIT 3'
)->fetchAll();

// Fetch top-level categories for submit answer form
$categories = $pdo->query(
    'SELECT id, name FROM categories WHERE parent_id IS NULL ORDER BY name ASC'
)->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<!-- ── Moving Announcement ticker ───────────────────────── -->
<?php if ($announcements): ?>
<div class="ticker-wrap">
    <div class="ticker-label">📢 Notices</div>
    <div class="ticker-track">
        <div class="ticker-content" id="tickerContent">
            <?php foreach ($announcements as $ann): ?>
            <span class="ticker-item">
                <strong><?= htmlspecialchars($ann['title']) ?>:</strong>
                <?= htmlspecialchars(strip_tags($ann['content'])) ?>
            </span>
            <?php endforeach; ?>
            <!-- Duplicate for seamless loop -->
            <?php foreach ($announcements as $ann): ?>
            <span class="ticker-item">
                <strong><?= htmlspecialchars($ann['title']) ?>:</strong>
                <?= htmlspecialchars(strip_tags($ann['content'])) ?>
            </span>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Greeting + Search box ────────────────────────────── -->
<div class="search-hero">
    <h2 class="search-hero-title">What can we help you with?</h2>
    <p class="search-hero-sub">Search our knowledge base for answers to common questions.</p>
    <div class="search-box-wrap">
        <span class="search-icon">🔍</span>
        <input
            type="text"
            id="searchInput"
            class="search-input"
            placeholder="Type your question here..."
            autocomplete="off"
        >
    </div>
</div>

<!-- ── Always-visible action buttons ────────────────────── -->
<div style="max-width:760px;margin:.75rem auto 0;display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;">
    <button class="btn btn-warning" style="padding:.6rem 1.5rem;" onclick="openModal('raise-ticket-modal')">🚩 Raise a Ticket</button>
    <button class="btn btn-success" style="padding:.6rem 1.5rem;" onclick="openModal('answerModal')">💡 Submit a Solution</button>
</div>

<div id="searchResults" class="search-results hidden"></div>

<!-- ── No Results inline panel ──────────────────────────── -->
<div id="noResultsPanel" style="display:none;max-width:760px;margin:.75rem auto 0;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1.25rem 2rem;text-align:center;box-shadow:0 2px 12px rgba(0,0,0,.07);">
    <div style="font-size:1.75rem;margin-bottom:.35rem;">😔</div>
    <p style="font-weight:600;font-size:1rem;margin:0 0 .25rem;">No results found for "<span id="noResultsTerm"></span>"</p>
    <p style="color:#64748b;font-size:.875rem;margin:0;">Try different keywords or use the buttons above.</p>
</div>

<!-- ── Ticket Modal ────────────────────────────────────────── -->
<div id="raise-ticket-modal" class="modal-overlay hidden">
  <div class="modal-box">
    <div class="modal-header">
      <h3 class="modal-title">Raise a New Ticket</h3>
      <button class="modal-close" onclick="closeModal('raise-ticket-modal')">&times;</button>
    </div>
    <div class="modal-body">
      <form id="raise-ticket-form">
        <?php csrf_field(); ?>
        <div class="form-group">
          <label class="form-label">Title <span class="text-danger">*</span></label>
          <input type="text" name="title" class="form-control" required maxlength="255" placeholder="Briefly describe your issue">
        </div>
        <div class="form-group">
          <label class="form-label">Category</label>
          <select name="category_id" class="form-control">
            <option value="">— Select Category (optional) —</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Description <span class="text-danger">*</span></label>
          <textarea name="description" class="form-control" rows="5" required placeholder="Provide as much detail as possible…"></textarea>
        </div>
        <div id="raise-ticket-msg" class="alert" style="display:none"></div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeModal('raise-ticket-modal')">Cancel</button>
          <button type="submit" class="btn btn-primary" id="raise-btn">Submit Ticket</button>
        </div>
      </form>
    </div>
  </div>
</div>




<!-- ── Answer Modal ──────────────────────────────────────── -->
<!-- Quill.js for rich text answer -->
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>

<div id="answerModal" class="modal-overlay hidden">
    <div class="modal-box">
        <div class="modal-header">
            <h2>💡 Submit an Answer</h2>
            <button class="modal-close" onclick="closeModal('answerModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p style="margin-bottom:1rem;color:var(--text-muted)">Know the answer? Submit it for admin review.</p>
            <div class="form-group">
                <label>Question <span class="text-danger">*</span></label>
                <input type="text" id="answerQuestion" class="form-control" placeholder="The question...">
            </div>
            <div class="form-group">
                <label>Category <span style="color:var(--text-muted);font-weight:400">(optional)</span></label>
                <select id="answerCategory" class="form-control">
                    <option value="">— Select category —</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Your answer <span class="text-danger">*</span></label>
                <!-- Quill rich text editor -->
                <div id="answerQuill" style="height:200px;background:#fff;border:1px solid #ced4da;border-radius:0 0 6px 6px;font-size:14px;"></div>
            </div>
            <div id="answerMsg" class="form-msg hidden" style="margin-top:10px;"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('answerModal')">Cancel</button>
            <button class="btn btn-success" id="answerSubmitBtn" onclick="submitAnswer()">Submit Answer</button>
        </div>
    </div>
</div>

<script>
  document.getElementById('raise-ticket-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('raise-btn');
  const msg = document.getElementById('raise-ticket-msg');
  btn.disabled = true;
  btn.textContent = 'Submitting…';
  msg.style.display = 'none';

  try {
    const res  = await fetch('<?= BASE_URL ?>/includes/raise_ticket.php', { method: 'POST', body: new FormData(this) });
    const data = await res.json();
    msg.style.display = 'block';
    msg.className = 'alert ' + (data.success ? 'alert-success' : 'alert-error');
    msg.textContent = data.message;
    if (data.success) {
      setTimeout(() => location.reload(), 1500);
    } else {
      btn.disabled = false;
      btn.textContent = 'Submit Ticket';
    }
  } catch (err) {
    msg.style.display = 'block';
    msg.className = 'alert alert-error';
    msg.textContent = 'Network error. Please try again.';
    btn.disabled = false;
    btn.textContent = 'Submit Ticket';
  }
});

// ── Quill editor for Submit Answer modal ─────────────────────
const answerQuill = new Quill('#answerQuill', {
    theme: 'snow',
    placeholder: 'Provide the answer…',
    modules: {
        toolbar: [
            [{ 'font': [] }, { 'size': [] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ 'color': [] }, { 'background': [] }],
            [{ 'align': [] }],
            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
            ['link'],
            ['clean']
        ]
    }
});

async function submitAnswer() {
    const question   = document.getElementById('answerQuestion').value.trim();
    const answer     = answerQuill.root.innerHTML.trim();
    const categoryId = document.getElementById('answerCategory').value;
    const msg        = document.getElementById('answerMsg');
    const btn        = document.getElementById('answerSubmitBtn');

    // Basic validation
    if (!question) {
        msg.className = 'form-msg alert alert-error'; msg.style.display = 'block';
        msg.textContent = 'Please enter a question.'; return;
    }
    if (!answer || answer === '<p><br></p>') {
        msg.className = 'form-msg alert alert-error'; msg.style.display = 'block';
        msg.textContent = 'Please write an answer.'; return;
    }

    btn.disabled = true;
    btn.textContent = 'Submitting…';
    msg.style.display = 'none';

    try {
        const fd = new FormData();
        fd.append('question',    question);
        fd.append('answer',      answer);
        fd.append('category_id', categoryId);

        const res  = await fetch('<?= BASE_URL ?>/includes/submit_answer.php', { method: 'POST', body: fd });
        const data = await res.json();

        msg.className    = 'form-msg alert ' + (data.success ? 'alert-success' : 'alert-error');
        msg.style.display = 'block';
        msg.textContent  = data.message;

        if (data.success) {
            // Reset form
            document.getElementById('answerQuestion').value = '';
            document.getElementById('answerCategory').value = '';
            answerQuill.setContents([]);
            setTimeout(() => closeModal('answerModal'), 2000);
        }
    } catch (err) {
        msg.className = 'form-msg alert alert-error';
        msg.style.display = 'block';
        msg.textContent = 'Network error. Please try again.';
    } finally {
        btn.disabled    = false;
        btn.textContent = 'Submit Answer';
    }
}

</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>