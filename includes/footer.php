        </div><!-- .content-body -->
    </main><!-- .main-content -->
</div><!-- .layout -->

<?php
// ── AI Chat Widget — shown to all logged-in users ────────────
// The widget HTML is injected here so it appears on every page.
// It is hidden by default and opens when the FAB is clicked.
if (!empty($_SESSION['user_id'])): ?>

<!-- ── AI Chat FAB (Floating Action Button) ───────────────── -->
<button id="ai-chat-fab" class="ai-chat-fab" title="Ask the AI Assistant" aria-label="Open AI Assistant">
    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
    </svg>
    <span class="ai-fab-label">AI Help</span>
</button>

<!-- ── Chat Widget Panel ──────────────────────────────────── -->
<div id="ai-chat-widget" class="ai-chat-widget ai-chat-hidden" role="dialog" aria-label="AI Support Assistant">

    <!-- Header -->
    <div class="ai-chat-header">
        <div class="ai-chat-header-info">
            <div class="ai-chat-avatar">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 8v4l3 3"/>
                </svg>
            </div>
            <div>
                <div class="ai-chat-title">G&amp;G AI Assistant</div>
                <div class="ai-chat-subtitle" id="ai-status-text">Powered by local AI · Private &amp; secure</div>
            </div>
        </div>
        <div class="ai-chat-header-actions">
            <button id="ai-chat-clear" class="ai-chat-icon-btn" title="Clear conversation">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/>
                </svg>
            </button>
            <button id="ai-chat-close" class="ai-chat-icon-btn" title="Close">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Messages -->
    <div class="ai-chat-messages" id="ai-chat-messages">
        <div class="ai-msg ai-msg-bot">
            <div class="ai-msg-bubble">
                👋 Hi <strong><?= htmlspecialchars($_SESSION['full_name'] ?? 'there') ?></strong>! I'm your G&amp;G support assistant.<br>
                Ask me anything about common IT issues — I'll search the solution base for you.
            </div>
        </div>
    </div>

    <!-- Typing indicator (hidden by default) -->
    <div class="ai-typing-indicator ai-chat-hidden" id="ai-typing">
        <div class="ai-typing-dot"></div>
        <div class="ai-typing-dot"></div>
        <div class="ai-typing-dot"></div>
    </div>

    <!-- Input area -->
    <div class="ai-chat-input-area">
        <textarea
            id="ai-chat-input"
            class="ai-chat-textarea"
            placeholder="Ask a question…"
            rows="1"
            maxlength="1000"
            aria-label="Your question"></textarea>
        <button id="ai-chat-send" class="ai-chat-send-btn" title="Send (Enter)">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
            </svg>
        </button>
    </div>

    <!-- CSRF hidden field for AJAX posts -->
    <input type="hidden" id="ai-csrf-token" value="<?= htmlspecialchars(csrf_generate()) ?>">
</div>

<?php endif; ?>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<?php if (!empty($_SESSION['user_id'])): ?>
<script src="<?= BASE_URL ?>/assets/js/ai_chat.js"></script>
<?php endif; ?>
</body>
</html>
