# G&G Support Portal — AI Agent Integration Guide

## What was added

| File | Purpose |
|------|---------|
| `ai/main.py` | FastAPI backend — RAG pipeline (Ollama + ChromaDB) |
| `ai/requirements.txt` | Python dependencies |
| `includes/ai_chat.php` | PHP proxy — auth/CSRF guard, forwards to FastAPI |
| `assets/css/ai_chat.css` | Chat widget styles |
| `assets/js/ai_chat.js` | Chat widget JavaScript |
| `includes/footer.php` | **Modified** — widget HTML injected for logged-in users |
| `includes/header.php` | **Modified** — ai_chat.css linked for logged-in users |
| `admin/solutions.php` | **Modified** — "Re-index AI" button added |
| `ai_chat_migration.sql` | Optional: chat logging table |

---

## Step 1 — Install Ollama

Download from https://ollama.com and run:

```bash
ollama serve
ollama pull nomic-embed-text   # embedding model
ollama pull llama3             # LLM (or deepseek-r1, qwen3, mistral, etc.)
```

---

## Step 2 — Start the Python backend

```bash
cd ai/
pip install -r requirements.txt
python main.py
```

The server listens on `http://127.0.0.1:8000` (internal only — never exposed to browser).

---

## Step 3 — Index your solutions

1. Log in as **admin** or **system_admin**.
2. Go to **Solution Management**.
3. Click **🤖 Re-index AI**.
4. Wait for the confirmation alert — it embeds all approved solutions into ChromaDB.

Re-run this button any time you add/update solutions.

---

## Step 4 — Test the chat widget

1. Log in as any user.
2. Click the **"AI Help"** button in the bottom-right corner.
3. Type a question — the AI will search the solution base and answer.

---

## Changing the LLM model

Edit `ai/main.py` line:
```python
LLM_MODEL = "llama3"   # change to: deepseek-r1, qwen3, mistral, phi4, etc.
```

Then restart `main.py`.

---

## Architecture overview

```
Browser
  │  AJAX POST
  ▼
includes/ai_chat.php    (PHP — session auth + CSRF check)
  │  cURL POST (internal)
  ▼
ai/main.py :8000        (FastAPI)
  │  embed question
  ▼
Ollama (nomic-embed-text)  →  ChromaDB nearest-neighbour search
                                      │ top-k solution chunks
  │  RAG prompt
  ▼
Ollama (llama3)  →  answer text
  │  JSON
  ▼
Browser renders bubble in chat widget
```
