'use strict';

(function () {
    const fab = document.getElementById('ai-chat-fab');
    const widget = document.getElementById('ai-chat-widget');
    if (!fab || !widget) return;

    const closeButton = document.getElementById('ai-chat-close');
    const clearButton = document.getElementById('ai-chat-clear');
    const input = document.getElementById('ai-chat-input');
    const sendButton = document.getElementById('ai-chat-send');
    const messages = document.getElementById('ai-chat-messages');
    const typing = document.getElementById('ai-typing');
    const status = document.getElementById('ai-status-text');
    const csrf = document.getElementById('ai-csrf-token');
    const conversation = [];
    let sending = false;

    function setOpen(open) {
        widget.classList.toggle('ai-chat-hidden', !open);
        fab.setAttribute('aria-expanded', String(open));
        fab.classList.toggle('ai-chat-hidden', open);
        if (open) input.focus();
        else fab.focus();
    }

    function appendMessage(text, type, sources) {
        const row = document.createElement('div');
        row.className = 'ai-msg ai-msg-' + type;
        const bubble = document.createElement('div');
        bubble.className = 'ai-msg-bubble';
        bubble.textContent = text;

        if (Array.isArray(sources) && sources.length) {
            const list = document.createElement('ul');
            list.className = 'ai-msg-sources';
            sources.forEach(function (source) {
                const item = document.createElement('li');
                item.textContent = source.title || 'Knowledge base article';
                list.appendChild(item);
            });
            bubble.appendChild(list);
        }

        row.appendChild(bubble);
        messages.appendChild(row);
        messages.scrollTop = messages.scrollHeight;
        return row;
    }

    async function sendMessage() {
        const question = input.value.trim();
        if (!question || sending) return;
        if (question.length > 1000) {
            appendMessage('Please keep your question under 1,000 characters.', 'error');
            return;
        }

        sending = true;
        input.value = '';
        input.style.height = 'auto';
        appendMessage(question, 'user');
        typing.classList.remove('ai-chat-hidden');
        sendButton.disabled = true;
        input.disabled = true;
        status.textContent = 'Searching the knowledge base…';

        const body = new FormData();
        body.append('csrf_token', csrf.value);
        body.append('action', 'chat');
        body.append('question', question);
        body.append('history', JSON.stringify(conversation.slice(-6)));

        try {
            const response = await fetch(widget.dataset.endpoint, {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await response.json();
            if (!response.ok || !data.success || typeof data.answer !== 'string') {
                throw new Error(data.message || data.detail || 'The assistant could not answer right now.');
            }

            appendMessage(data.answer, 'bot', data.sources);
            conversation.push({ role: 'user', content: question });
            conversation.push({ role: 'assistant', content: data.answer });
            status.textContent = 'Powered by local AI · Private & secure';
        } catch (error) {
            appendMessage(error.message || 'The assistant is unavailable. Please try again later.', 'error');
            status.textContent = 'Assistant unavailable';
        } finally {
            sending = false;
            typing.classList.add('ai-chat-hidden');
            sendButton.disabled = false;
            input.disabled = false;
            input.focus();
        }
    }

    fab.addEventListener('click', function () { setOpen(true); });
    closeButton.addEventListener('click', function () { setOpen(false); });
    clearButton.addEventListener('click', function () {
        conversation.length = 0;
        messages.replaceChildren();
        appendMessage('Conversation cleared. What can I help you with?', 'bot');
        status.textContent = 'Powered by local AI · Private & secure';
        input.focus();
    });
    sendButton.addEventListener('click', sendMessage);
    input.addEventListener('input', function () {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 110) + 'px';
    });
    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage();
        }
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !widget.classList.contains('ai-chat-hidden')) setOpen(false);
    });
})();