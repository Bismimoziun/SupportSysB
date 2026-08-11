# 🛡️ G&G Support Portal

A role-based internal support portal built with PHP, MySQL, and vanilla JavaScript.
Designed for government and public sector organisations.

---

## ✨ Features

- **Role-based access** — System Admin, Admin, and User roles
- **Knowledge base** — searchable solution articles with admin-only and public visibility
- **Ticket management** — raise, assign, escalate, and resolve support tickets
- **SLA tracking** — configurable attendance and resolution deadlines per level
- **Round-robin assignment** — tickets auto-assigned across admins in rotation
- **Time extension** — admins can request extensions with configurable reasons
- **AI assistant** — local RAG-powered chat widget (Ollama + ChromaDB, fully on-premise)
- **Announcement bar** — priority-ordered notices shown to all users
- **Category management** — nested category tree (Category → Sub-category → Sub-title)
- **User management** — system admin creates and manages all users (no public signup)
- **Audit trail** — every ticket action logged with timestamps

---

## 🗂️ Tech Stack

| Layer        | Technology                        |
|--------------|-----------------------------------|
| Backend      | PHP 8.2                           |
| Database     | MySQL 8.0                         |
| Frontend     | HTML5, CSS3, JavaScript           |
| Rich Text    | TinyMCE 6                         |
| Web Server   | Apache (XAMPP)                    |
| AI Backend   | Python 3.11, FastAPI, LangChain   |
| Vector Store | ChromaDB                          |
| Local LLM    | Ollama (llama3 / any GGUF model)  |

---

## 🚀 Quick Setup (XAMPP)

### Prerequisites
- [XAMPP](https://www.apachefriends.org) with PHP 8.0+ and MySQL
- A web browser

### Step 1 — Clone the repository

```bash
git clone https://github.com/YOURUSERNAME/gg-support.git
```

Or download the ZIP and extract it.

### Step 2 — Place in XAMPP

Copy the entire `gg-support` folder into:

```
C:\xampp\htdocs\gg-support\
```

### Step 3 — Configure database connection

Copy the example config file:

```bash
cp includes/db.example.php includes/db.php
```

Open `includes/db.php` and update if needed:

```php
define('BASE_URL', '/gg-support');  // keep this for XAMPP subfolder
define('DB_HOST',  'localhost');
define('DB_NAME',  'knowledgebase');
define('DB_USER',  'root');
define('DB_PASS',  '');             // XAMPP default is empty
```

> **Never commit `includes/db.php`** — it is already listed in `.gitignore`.

### Step 4 — Create the database

1. Start XAMPP — make sure both **Apache** and **MySQL** are green
2. Open [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
3. Click **New** → name it `knowledgebase` → encoding `utf8mb4_unicode_ci` → **Create**
4. Click on `knowledgebase` → **SQL** tab
5. Run `database_setup.sql` (paste contents → **Go**)
6. Run `ticket_schema.sql` (paste contents → **Go**)

### Step 5 — Set correct passwords

The seed data uses a placeholder password hash. Replace it:

1. Create a temporary file at `C:\xampp\htdocs\gg-support\generate_hash.php`:

```php
<?php echo password_hash('YourNewPassword', PASSWORD_BCRYPT);
```

2. Visit [http://localhost/gg-support/generate_hash.php](http://localhost/gg-support/generate_hash.php)
3. Copy the hash shown
4. In phpMyAdmin → SQL tab → run:

```sql
UPDATE users SET password = 'PASTE_YOUR_HASH_HERE';
```

5. **Delete `generate_hash.php` immediately after**

### Step 6 — Open the portal

Visit: [http://localhost/gg-support](http://localhost/gg-support)

---

## 👤 Default Login Credentials

> ⚠️ Change all passwords immediately after first login on any real server.

| Role         | Username   | Password   |
|--------------|------------|------------|
| System Admin | `sysadmin` | `Test@1234` |
| Admin        | `admin`    | `Test@1234` |
| User         | `user1`    | `Test@1234` |

---

## 📁 Project Structure

```
gg-support/
├── admin/                    ← Admin panel pages
│   ├── system_dashboard.php  ← System Admin home
│   ├── dashboard.php         ← Admin home
│   ├── users.php             ← User management
│   ├── categories.php        ← Category management
│   ├── solutions.php         ← Solution management
│   ├── announcements.php     ← Announcement management
│   ├── pending_flags.php     ← Flagged questions
│   ├── tickets.php           ← All tickets view
│   └── ticket_config.php     ← Ticket level & SLA config
├── ai/                       ← AI backend (optional)
│   ├── main.py               ← FastAPI — RAG pipeline (Ollama + ChromaDB)
│   └── requirements.txt      ← Python dependencies
├── assets/
│   ├── css/
│   │   ├── style.css         ← Main stylesheet
│   │   ├── tickets.css       ← Ticket-specific styles
│   │   └── ai_chat.css       ← AI chat widget styles
│   └── js/
│       ├── main.js           ← Main JavaScript
│       └── ai_chat.js        ← AI chat widget JavaScript
├── includes/
│   ├── db.example.php        ← ⬅ Copy to db.php and configure
│   ├── auth.php              ← Login handler
│   ├── auth_guard.php        ← Session + role protection
│   ├── header.php            ← Shared sidebar + HTML head
│   ├── footer.php            ← Closes layout + AI widget
│   ├── ticket_helpers.php    ← Ticket logic (round-robin, SLA)
│   ├── ai_chat.php           ← PHP proxy to FastAPI AI service
│   └── ...
├── uploads/                  ← TinyMCE image uploads (gitignored)
├── database_setup.sql        ← ⬅ Run this first in phpMyAdmin
├── ticket_schema.sql         ← ⬅ Run this second in phpMyAdmin
├── ai_chat_migration.sql     ← Optional: AI chat logging table
├── index.php                 ← Login page
├── dashboard.php             ← Role router
├── user_home.php             ← User search / knowledge base
├── user_tickets.php          ← User's ticket list
└── ticket_detail.php         ← View / manage a single ticket
```

---

## 🔐 Role Permissions

| Feature                    | User | Admin | System Admin |
|----------------------------|:----:|:-----:|:------------:|
| Search knowledge base      | ✅   | ✅    | ✅           |
| Raise tickets              | ✅   | ✅    | ✅           |
| View announcements         | ✅   | ✅    | ✅           |
| Use AI assistant           | ✅   | ✅    | ✅           |
| Attend & resolve tickets   | ❌   | ✅    | ✅           |
| Extend ticket time         | ❌   | ✅    | ✅           |
| Add/manage solutions       | ❌   | ✅    | ✅           |
| Re-index AI knowledge base | ❌   | ✅    | ✅           |
| Manage categories          | ❌   | ✅    | ✅           |
| Manage announcements       | ❌   | ✅    | ✅           |
| Manage users               | ❌   | ❌    | ✅           |
| Configure ticket levels    | ❌   | ❌    | ✅           |
| Configure SLA settings     | ❌   | ❌    | ✅           |
| Manage extension reasons   | ❌   | ❌    | ✅           |

---

## ⚙️ Ticket System Configuration

System Admin can configure from **Admin Panel → Ticket Configuration**:

- **Levels** — define how many admin levels exist (default: 3)
- **Assign admins** — assign specific admins to each level with round-robin order
- **SLA per level** — set attendance and resolution time limits in minutes
- **Extension reasons** — add/remove/disable reasons for the extension dropdown

---

## 🔄 SLA & Escalation Logic

1. User raises ticket → assigned to Level 1 admin via round-robin
2. Admin must attend (click Take Up) within **attendance SLA**
3. After take-up, admin must resolve within **resolution SLA**
4. SLA deadlines are **fixed-time** (based on when ticket was raised, not when attended)
5. If attendance SLA missed → ticket escalates to next admin in level, then next level
6. If all levels exhausted → ticket marked **Unattended**

---

## 🤖 AI Assistant (Optional)

The portal includes an optional on-premise AI chat widget powered by **Ollama + ChromaDB**.
No data leaves your server — all AI runs locally.

### Setup

**1. Install Ollama**

Download from [https://ollama.com](https://ollama.com) and run:

```bash
ollama serve
ollama pull nomic-embed-text   # embedding model
ollama pull llama3             # LLM (or deepseek-r1, mistral, phi4, etc.)
```

**2. Start the Python backend**

```bash
cd ai/
pip install -r requirements.txt
python main.py
```

The service listens on `http://localhost:8000` (internal only — never exposed to the browser).

**3. Index your solutions**

1. Log in as **Admin** or **System Admin**
2. Go to **Solution Management**
3. Click **🤖 Re-index AI**
4. Wait for the confirmation — all approved solutions are embedded into ChromaDB

Re-run this button whenever you add or update solutions.

**4. Use the chat widget**

Any logged-in user will see an **AI Help** button in the bottom-right corner.

### Changing the LLM model

Edit `ai/main.py`:

```python
LLM_MODEL = "llama3"   # change to: deepseek-r1, mistral, phi4, qwen3, etc.
```

Then restart `python main.py`.

### Architecture

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

---

## 🗃️ SQL Files Reference

| File | Purpose | When to run |
|------|---------|-------------|
| `database_setup.sql` | Core tables (users, solutions, categories, announcements) | First — always |
| `ticket_schema.sql` | Ticket system tables + seed data | Second — always |
| `ai_chat_migration.sql` | Optional AI chat logging table | Only if you want chat logs |
| `sla_settings_migration.sql` | SLA throttle table | If you see SLA-related errors |
| `fix_escalation_spam.sql` | One-time fix for duplicate escalation entries | Only if needed |
| `add_time_limit_migration.sql` | Adds time limit column to tickets | Only if upgrading from an older version |

---

## 🤝 Contributing

1. Fork this repository
2. Create a feature branch: `git checkout -b feature/your-feature-name`
3. Make your changes
4. Commit: `git commit -m "Add: your feature description"`
5. Push: `git push origin feature/your-feature-name`
6. Open a Pull Request

See [CONTRIBUTING.md](CONTRIBUTING.md) for code style guidelines.

---

## 📋 Roadmap

- [ ] Asset management module (hardware + software inventory)
- [ ] Network monitoring dashboard
- [ ] Email notifications on ticket assignment
- [ ] PDF export for ticket reports
- [ ] Two-factor authentication
- [ ] Better mobile responsive layout

---

## 📄 License

MIT License — free to use, modify, and distribute.

---

## 📞 Support

If you find a bug or have a feature request, open an issue on GitHub.
