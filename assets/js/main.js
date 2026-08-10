// ============================================================
// G&G Support Portal — main.js
// ============================================================
'use strict';

// BASE_URL is set inline in each PHP page that needs AJAX.
// Fallback: derive from current path
if (typeof BASE_URL === 'undefined') {
    window.BASE_URL = '/gg-support';
}

/* ── Modal helpers ───────────────────────────────────────── */
function openModal(id) {
    const el = document.getElementById(id);
    if (el) { el.classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
    const el = document.getElementById(id);
    if (el) { el.classList.add('hidden'); document.body.style.overflow = ''; }
}

// Close on overlay click
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.add('hidden');
        document.body.style.overflow = '';
    }
});

// Close on Escape
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay:not(.hidden)').forEach(function (m) {
            m.classList.add('hidden');
        });
        document.body.style.overflow = '';
    }
});

/* ── Mobile sidebar toggle ───────────────────────────────── */
const _toggleBtn = document.getElementById('sidebarToggle');
const _sidebar   = document.getElementById('sidebar');
const _overlay   = document.getElementById('sidebarOverlay');

function closeSidebar() {
    if (_sidebar)  _sidebar.classList.remove('open');
    if (_overlay)  _overlay.classList.remove('active');
    document.body.style.overflow = '';
}

if (_toggleBtn) {
    _toggleBtn.addEventListener('click', function () {
        const isOpen = _sidebar.classList.toggle('open');
        if (_overlay) _overlay.classList.toggle('active', isOpen);
        document.body.style.overflow = isOpen ? 'hidden' : '';
    });
}

/* ── Live search (user_home.php) ─────────────────────────── */
(function () {
    const input   = document.getElementById('searchInput');
    if (!input) return;

    const resultsBox   = document.getElementById('searchResults');
    var noResultsPanel = document.getElementById('noResultsPanel');
    var noResultsTerm  = document.getElementById('noResultsTerm');
    let debounceTimer;
    let lastQuery = '';

    input.addEventListener('input', function () {
        const q = this.value.trim();
        clearTimeout(debounceTimer);

        if (q.length < 2) {
            hide(resultsBox);
            if (noResultsPanel) noResultsPanel.style.display = 'none';
            resultsBox.innerHTML = '';
            lastQuery = '';
            return;
        }

        if (q === lastQuery) return;
        lastQuery = q;

        debounceTimer = setTimeout(function () {
            fetch(BASE_URL + '/includes/search.php?q=' + encodeURIComponent(q), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (data) {
                renderResults(data, q);
            })
            .catch(function (err) {
                console.error('Search error:', err);
                resultsBox.innerHTML = '<div class="search-result-item" style="color:var(--danger)">Search unavailable. Please check your connection.</div>';
                show(resultsBox);
                hide(noResBox);
            });
        }, 300);
    });

    function renderResults(data, q) {
        if (!data || data.length === 0) {
            hide(resultsBox);
            resultsBox.innerHTML = '';
            setVal('flagQuestion',   q);
            setVal('answerQuestion', q);
            if (noResultsTerm)  noResultsTerm.textContent = q;
            if (noResultsPanel) noResultsPanel.style.display = 'block';
            return;
        }

        if (noResultsPanel) noResultsPanel.style.display = 'none';

        resultsBox.innerHTML = '';

        data.forEach(function (item) {
            var div = document.createElement('div');
            div.className = 'search-result-item';
            var preview = stripHtml(item.answer || '').substring(0, 130);
            var meta = '';
            if (item.submitted_by) meta += '<span>✍️ ' + esc(item.submitted_by) + '</span>';
            if (item.verified_by)  meta += '<span>✅ Verified by ' + esc(item.verified_by) + '</span>';
            if (item.created_at)   meta += '<span>📅 ' + esc(item.created_at.substring(0, 10)) + '</span>';
            div.innerHTML =
                '<div class="result-question">' + esc(item.question) + '</div>' +
                (item.category ? '<div class="result-category">📁 ' + esc(item.category) + '</div>' : '') +
                '<div class="result-preview">' + esc(preview) + '…</div>' +
                (meta ? '<div class="result-meta">' + meta + '</div>' : '');
            div.addEventListener('click', function () { openDetail(item); });
            resultsBox.appendChild(div);
        });

        show(resultsBox);
    }

    function openDetail(item) {
        let modal = document.getElementById('solutionDetailModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'solutionDetailModal';
            modal.className = 'modal-overlay';
            modal.innerHTML =
                '<div class="modal-box modal-lg">' +
                  '<div class="modal-header">' +
                    '<h2 id="sdQ" style="font-size:1rem;flex:1;margin-right:.75rem;line-height:1.4"></h2>' +
                    '<button class="modal-close" onclick="closeModal(\'solutionDetailModal\')">&times;</button>' +
                  '</div>' +
                  '<div class="modal-body">' +
                    '<div id="sdCat" style="font-size:.8rem;color:#64748b;margin-bottom:.75rem"></div>' +
                    '<div id="sdAns" class="solution-preview-body"></div>' +
                  '</div>' +
                  '<div class="modal-footer">' +
                    '<button class="btn btn-secondary" onclick="closeModal(\'solutionDetailModal\')">Close</button>' +
                  '</div>' +
                '</div>';
            document.body.appendChild(modal);
        }
        document.getElementById('sdQ').textContent   = item.question;
        document.getElementById('sdCat').textContent = item.category ? '📁 ' + item.category : '';
        document.getElementById('sdAns').innerHTML   = item.answer || '';
        openModal('solutionDetailModal');
    }
})();

/* ── Flag submission ─────────────────────────────────────── */
function submitFlag() {
    const question = getVal('flagQuestion');
    const msgEl    = document.getElementById('flagMsg');
    if (!question) { showMsg(msgEl, 'Please describe your question.', 'error'); return; }
    const fd = new FormData();
    fd.append('question', question);
    fetch(BASE_URL + '/includes/submit_flag.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(function (d) {
            showMsg(msgEl, d.message, d.success ? 'success' : 'error');
            if (d.success) { setVal('flagQuestion', ''); setTimeout(() => closeModal('flagModal'), 2200); }
        })
        .catch(() => showMsg(msgEl, 'Something went wrong.', 'error'));
}

/* ── Answer submission ───────────────────────────────────── */
function submitAnswer() {
    const question    = getVal('answerQuestion');
    const answer      = getVal('answerText');
    const category_id = getVal('answerCategory');
    const msgEl       = document.getElementById('answerMsg');
    if (!question || !answer) { showMsg(msgEl, 'Question and answer are required.', 'error'); return; }
    const fd = new FormData();
    fd.append('question', question);
    fd.append('answer', answer);
    fd.append('category_id', category_id);
    fetch(BASE_URL + '/includes/submit_answer.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(function (d) {
            showMsg(msgEl, d.message, d.success ? 'success' : 'error');
            if (d.success) {
                setVal('answerQuestion', '');
                setVal('answerText', '');
                setTimeout(() => closeModal('answerModal'), 2500);
            }
        })
        .catch(() => showMsg(msgEl, 'Something went wrong.', 'error'));
}

/* ── Utilities ───────────────────────────────────────────── */
function show(el) { if (el) el.classList.remove('hidden'); }
function hide(el) { if (el) el.classList.add('hidden'); }
function getVal(id) { const el = document.getElementById(id); return el ? el.value.trim() : ''; }
function setVal(id, v) { const el = document.getElementById(id); if (el) el.value = v; }

function showMsg(el, text, type) {
    if (!el) return;
    el.textContent = text;
    el.className   = 'form-msg ' + (type || '');
    show(el);
}
function esc(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function stripHtml(html) {
    const d = document.createElement('div');
    d.innerHTML = html;
    return d.textContent || d.innerText || '';
}
