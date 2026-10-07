# G&G IT Support Portal — Project Documentation

## 1. Project abstract

The G&G IT Support Portal is a browser-based internal help desk and knowledge
management application. It gives staff one place to search approved support
solutions, submit IT tickets, and follow their progress. Support administrators
can triage and resolve tickets, maintain the knowledge base, and publish
announcements. A system administrator manages accounts and configures ticket
assignment levels and service-level agreement (SLA) targets.

The application is built from server-rendered PHP pages backed by MySQL. Its
optional AI assistant uses a separate local Python service, Ollama language and
embedding models, and a ChromaDB vector store to search approved solutions. The
core ticketing and knowledge-base features do not depend on the AI service.

## 2. Introduction and objectives

Support requests are difficult to track when questions, solutions, and work
updates are scattered across messages. This project centralizes those processes
and provides:

- Self-service search of the approved knowledge base.
- Structured ticket intake with status, category, assignment, and deadlines.
- Administrative ticket queues and a timestamped activity history.
- Escalation rules based on configurable support levels and SLA deadlines.
- Review and maintenance workflows for user-submitted solutions.
- Role-based access for users, administrators, and system administrators.
- An optional locally hosted AI interface to find relevant support articles.

The portal is an internal support workflow application, not a general-purpose
asset inventory or a public account registration service.

## 3. Technology stack

| Layer | Technology | Purpose |
|---|---|---|
| Web server | Apache (commonly provided by XAMPP) | Serves the PHP application |
| Application | PHP 8.x with PDO | Authentication, page rendering, forms, and database operations |
| Database | MySQL / MariaDB | Accounts, categories, solutions, tickets, assignment, and audit data |
| Browser UI | HTML5, CSS3, vanilla JavaScript | Server-rendered pages, styling, responsive layouts, and AJAX interactions |
| Rich text | Quill on selected pages | Editing rich-text solution content |
| Optional AI API | Python 3.12, FastAPI, Uvicorn | Local HTTP API for chat, health checks, and indexing |
| AI orchestration | LangChain integrations | Text chunking, embeddings, vector storage, and chat model calls |
| Optional AI models | Ollama, `nomic-embed-text`, `llama3` | Local text embeddings and language-model responses |
| AI vector search | ChromaDB and scikit-learn | Persistent solution vectors and cosine-distance retrieval |

The AI service is optional. The PHP application calls it through
`includes/ai_chat.php`; the browser does not call Ollama directly.

## 4. Architecture and methodology

### 4.1 Request flow

1. A browser requests a PHP page from Apache.
2. Public/login pages handle authentication; protected pages load the shared
   authentication guard.
3. PHP reads or updates MySQL using the PDO connection in `includes/db.php`.
   User-supplied database values should be passed through prepared statements.
4. The shared header and footer provide navigation, common styles, and the
   logged-in AI widget shell.
5. JavaScript handles interactive actions such as live search, modal forms,
   ticket actions, and AI chat requests.
6. When the optional assistant is used, PHP checks the session and CSRF token,
   then proxies the request to the local FastAPI service on port 8000.

### 4.2 Development approach

The project uses a conventional server-rendered PHP structure rather than a
full-stack framework. Shared page chrome and guard logic are in `includes/`;
role-specific workflows are in root-level user pages and `admin/`; CSS,
JavaScript, and optional AI backend code are kept in their respective folders.
SQL setup and migration scripts define the MySQL schema.

### 4.3 Security concepts in the application

- Login verifies stored password hashes with PHP's password-verification API.
- User pages and administrative pages use session and role checks.
- Protected form submissions use the shared CSRF helper.
- Database queries should use PDO prepared statements.
- The application enforces a 30-minute session inactivity timeout.
- `includes/db.php` contains local database settings, is ignored by Git, and
  must not be published.

For a real deployment, use HTTPS, strong unique passwords, a restricted
database account, regular backups, and production-appropriate PHP error
handling. The example local XAMPP settings are not a production security
configuration.

## 5. Roles and responsibilities

| Capability | User | Admin | System Admin |
|---|:---:|:---:|:---:|
| Search public/approved support solutions | Yes | Yes | Yes |
| Submit a proposed solution for review | Yes | Yes | Yes |
| Raise a ticket and view own tickets | Yes | Yes | Yes |
| View and update tickets assigned to the account | No | Yes | Yes |
| Take up, resolve, mark unresolved, or request an extension for an assigned ticket | No | Yes | Yes |
| Manage solutions and approve/reject submissions | No | Yes | Yes |
| Manage categories and announcements | No | Yes | Yes |
| Re-index approved solutions for the AI assistant | No | Yes | Yes |
| Manage user accounts and roles | No | No | Yes |
| Configure ticket levels, SLAs, and extension reasons | No | No | Yes |

Ticket access is scoped: ordinary admins primarily see tickets assigned to
them; system administrators can see the broader ticket queue. Administrative
actions on a ticket still depend on the ticket assignment and workflow checks.
There is no public sign-up flow; accounts are created through administration.

## 6. Main workflows and how to use the portal

### 6.1 Sign in

1. Open `http://localhost/IT-Support-System/` after starting Apache and MySQL.
2. Enter the username and password created for your account.
3. The portal redirects to the appropriate dashboard based on the account role.
4. Use the sidebar to move between search, tickets, and administrative pages.
5. Use the arrow control in the desktop sidebar to collapse or expand it. On
   narrow screens, use the menu button to open the navigation drawer.
6. The **AI Help** control opens the optional support assistant.

There are no safe, universal login credentials: seed password hashes may be
placeholders. For a fresh local installation, create or reset a system
administrator password using the instructions in section 8 before signing in.

### 6.2 Search and knowledge-base workflow

1. A signed-in user opens **Search** and enters at least two characters.
2. The page requests matching approved solutions from the PHP search endpoint.
3. Selecting a result opens its answer.
4. If the user knows a missing answer, they can submit a proposed solution.
5. Administrators review submissions and approve or reject them.
6. Approved solutions are available to users and may be included in an AI
   re-index operation.

### 6.3 Ticket workflow

1. A signed-in user submits a ticket title, description, and optional category.
2. The application creates a ticket and records a `raised` activity event.
3. It selects the first active support level and assigns the ticket to an
   eligible administrator using the level's round-robin pointer.
4. Attend and resolve deadlines are calculated from the ticket's creation time
   using the SLA values configured for that level.
5. The assigned administrator takes up the ticket, changing its status to
   `in_progress`; the administrator can then resolve it or mark it unresolved.
6. If a configured deadline is missed, the SLA check escalates to the next
   higher `level_order` that is active. If no higher active level is available,
   the ticket is marked `unattended`.
7. Ticket actions and assignment changes are recorded in the activity history.
8. An administrator may use a configured extension reason to extend a ticket's
   deadline; the extension details are recorded separately.

Ticket status values in the schema are `open`, `in_progress`, `resolved`,
`unresolved`, and `unattended`.

### 6.4 Administration workflow

- **Users:** A system administrator creates accounts, chooses roles, and
  activates/deactivates or updates accounts.
- **Solutions:** An administrator creates and edits articles, reviews user
  submissions, sets visibility, and requests an AI re-index.
- **Categories:** Administrators maintain the category hierarchy used by
  solutions and tickets.
- **Announcements:** Administrators publish and order notices shown in the
  portal.
- **Tickets:** Administrators work on tickets assigned to them; system
  administrators can review the wider queue.
- **Ticket Configuration:** A system administrator manages support levels,
  their ordering and SLA values, admin membership, and available extension
  reasons.

### 6.5 Optional AI workflow

1. Keep Ollama and the Python API running locally.
2. A user submits a question through the AI Help widget.
3. The PHP proxy checks authentication and CSRF, then sends the question and
   recent conversation history to FastAPI.
4. FastAPI splits indexed solution text into overlapping 500-character chunks
   (80-character overlap), embeds the question, retrieves up to four relevant
   chunks from ChromaDB using cosine similarity, and filters weak matches
   (configured cosine-distance threshold: 0.45).
5. If relevant content is found, the local language model is prompted to
   answer only from the retrieved knowledge-base material.
6. The answer and source titles are returned to the browser.
7. If no sufficiently relevant article exists, the assistant asks the user to
   raise a support ticket rather than inventing an answer.

Re-index after adding or materially changing approved solutions. The first
indexing operation requires the embedding model and Ollama to be available.

## 7. Database

### 7.1 Database name and connection

The local configuration in `includes/db.php` currently uses:

- Host: `localhost`
- Database: `knowledgebase`
- User: `root`
- Password: empty (the typical default in a local XAMPP installation)
- Application base URL: `/IT-Support-System`

Use the values in your local `includes/db.php` as the source of truth. Do not
copy local credentials into public documentation or commit the config file.

### 7.2 Tables

| Table | Purpose |
|---|---|
| `users` | Login identity, password hash, role, active state, and profile fields |
| `categories` | Hierarchical categories and subcategories |
| `solutions` | Knowledge-base questions, rich-text answers, category, review status, and visibility |
| `announcements` | Notices displayed in the portal |
| `flags` | User-reported missing or problematic knowledge-base entries |
| `ticket_levels` | Ordered support levels and attendance/resolution SLA values |
| `ticket_level_admins` | Many-to-many membership between support levels and admin accounts |
| `ticket_extension_reasons` | Active extension reasons and permitted time limits |
| `tickets` | Ticket content, raiser, current assignment, status, deadlines, and resolution |
| `ticket_activity` | Timestamped ticket events, actors, assignment, deadlines, and notes |
| `ticket_extensions` | Extension reason, remarks, hours, and previous/new deadlines |
| `round_robin_pointer` | Last admin index used for assignment at each level |
| `sla_settings` | Throttle timestamp for the SLA check when the migration is installed |
| `ai_chat_logs` | Optional AI chat logs; only present if its migration was run and logging is wired |

Important relationships include:

- A category can have a parent category.
- A solution may reference a category and submitting/verifying users.
- A ticket belongs to a raising user and may reference its current support level
  and assigned admin.
- A ticket's activity rows and extension rows refer back to the ticket.
- Level assignments connect users with ticket levels.
- Extension rows reference a configured extension reason.

### 7.3 Check the database in phpMyAdmin

1. Start **MySQL** in the XAMPP Control Panel.
2. Visit `http://localhost/phpmyadmin/`.
3. Select the `knowledgebase` database in the left panel.
4. Open the **Structure** tab to inspect table names and columns.
5. Click a table to browse records.
6. Use the **SQL** tab for read-only checks such as:

```sql
SELECT DATABASE();
SHOW TABLES;
DESCRIBE users;
DESCRIBE ticket_levels;
DESCRIBE tickets;
SELECT id, full_name, username, role, is_active FROM users ORDER BY id;
SELECT id, level_name, level_order, attend_sla, resolve_sla, is_active
FROM ticket_levels
ORDER BY level_order;
SELECT status, COUNT(*) AS ticket_count
FROM tickets
GROUP BY status;
```

Do not run `DROP`, `DELETE`, or broad `UPDATE` statements just to inspect data.
Back up the database before applying schema changes.

## 8. Requirements and installation

### 8.1 Required for core portal

- Windows with XAMPP (or equivalent Apache, PHP, and MySQL/MariaDB services).
- PHP 8.x recommended; the existing project describes PHP 8.0+.
- MySQL 8 or compatible MariaDB.
- A modern browser.
- The repository copied to a directory under Apache's document root.

### 8.2 Optional for AI

- Python 3.12, available as `py -3.12` on Windows.
- Python packages in `ai/requirements.txt`.
- Ollama installed and running.
- The `nomic-embed-text` embedding model.
- The `llama3` chat model, or a model selected in `ai/main.py`.
- Sufficient disk space and memory for the selected local models.

The AI service is independent of normal PHP/MySQL operation; the assistant
cannot answer while FastAPI or Ollama is stopped.

### 8.3 Fresh local installation

1. Install XAMPP and copy/clone the project into:

   ```text
   C:\xampp\htdocs\IT-Support-System
   ```

2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. Confirm `includes/db.php` exists and has the correct local `BASE_URL`,
   database host/name, username, and password. This file is local configuration
   and should remain uncommitted.
4. Open phpMyAdmin at `http://localhost/phpmyadmin/`.
5. For a brand-new database only, run the SQL files in order:
   - `database_setup.sql`
   - `ticket_schema.sql`
   - `ticket_level_active_migration.sql` only if the schema file used does not
     already create `ticket_levels.is_active`
6. Set a strong password for the seeded `sysadmin` account (see 8.5).
7. Open `http://localhost/IT-Support-System/` and sign in.
8. Create real user and administrator accounts through **User Management**.
9. Assign administrators to ticket levels before relying on automatic ticket
   assignment.

> **Data-loss warning:** `database_setup.sql` drops and recreates the core
> `users`, `categories`, `solutions`, `announcements`, and `flags` tables.
> Never run it against a database containing data you need. Back up first.

The separate `knowledgebase.sql` file is a database dump, not a migration to
run on top of an already populated installation. Choose one appropriate
initialization route; do not import multiple competing full-database scripts.

### 8.4 Existing database upgrades

For an existing installation, do not rerun `database_setup.sql` or import the
full `knowledgebase.sql` dump. Back up first, then use only the migrations
required by the schema currently installed:

- If `ticket_levels` does not have `is_active`, run
  `ticket_level_active_migration.sql` once. New/fresh
  `ticket_schema.sql` versions now include this column.
- If the SLA throttle table is absent, run `sla_settings_migration.sql`.
- If you need an optional AI chat log table, run `ai_chat_migration.sql`.
- `add_time_limit_migration.sql` adds the extension time-limit field to an older
  extension-reason table; inspect the table first and run only if that field is
  missing.

An `ALTER TABLE ... ADD COLUMN` migration is normally a one-time operation.
Confirm the column/table is absent first to avoid duplicate-column errors.

### 8.5 Set or reset the system administrator password

The seeded hashes in `database_setup.sql` are explicitly marked as placeholders.
Do not assume a documented example password will work.

1. In Command Prompt, generate a bcrypt hash without creating a PHP file in the
   web root:

   ```powershell
   C:\xampp\php\php.exe -r "echo password_hash('Choose-A-Strong-Password', PASSWORD_BCRYPT), PHP_EOL;"
   ```

2. Copy the resulting hash.
3. In phpMyAdmin, select `knowledgebase` → **SQL**, and run the following after
   replacing the placeholder hash:

   ```sql
   UPDATE users
   SET password = 'PASTE_THE_GENERATED_HASH_HERE'
   WHERE username = 'sysadmin';
   ```

4. Sign in with username `sysadmin` and the password used to generate the
   hash.
5. Create or reset other accounts from **User Management**.

Do not store clear-text passwords in the database. PHP's `password_hash()` and
`password_verify()` handle password hashing and verification.

### 8.6 Start the optional AI service

Run this only if you want AI chat. Ollama and FastAPI must both stay running.

**Terminal 1 — Ollama and models**

Install Ollama from [ollama.com](https://ollama.com), then run:

```powershell
ollama serve
```

In another terminal, download the configured models:

```powershell
ollama pull nomic-embed-text
ollama pull llama3
```

**Terminal 2 — Python API**

```powershell
cd C:\xampp\htdocs\IT-Support-System\ai
py -3.12 -m pip install -r requirements.txt
py -3.12 main.py
```

Check the API at `http://127.0.0.1:8000/health`. A healthy API should return a
JSON response with its Ollama status and model/index information. Then sign in
as an admin, open **Solution Management**, and use **Re-index AI** to index
approved solutions.

If the widget reports that the AI backend is unreachable, check that Ollama is
available at port 11434 and FastAPI at port 8000, that both model downloads
completed, and that the Python dependency installation succeeded.

## 9. Running and checking the project

### Start the PHP application

1. Start Apache and MySQL in XAMPP.
2. Visit `http://localhost/IT-Support-System/`.
3. Sign in and use the role-appropriate navigation.
4. Keep the XAMPP services running while using the application.

### Verify database connectivity

- In a browser, open the application login page. A database connection problem
  is reported by the local PHP config.
- In phpMyAdmin, select `knowledgebase` and run:

  ```sql
  SELECT 1;
  SELECT COUNT(*) AS users FROM users;
  SELECT COUNT(*) AS tickets FROM tickets;
  ```

- Confirm the PHP values in `includes/db.php` agree with the MySQL server and
  selected database.

### Verify AI availability

- Ollama: open `http://127.0.0.1:11434/api/tags` or run `ollama list`.
- FastAPI: open `http://127.0.0.1:8000/health`.
- Check that both terminals remain open and show no startup errors.
- A running API does not automatically mean the vector store has indexed
  solutions; use the admin re-index action.

### Syntax checks

From the repository root in PowerShell:

```powershell
C:\xampp\php\php.exe -l index.php
C:\xampp\php\php.exe -l user_home.php
C:\xampp\php\php.exe -l ticket_detail.php
C:\xampp\php\php.exe -l admin\dashboard.php
C:\xampp\php\php.exe -l admin\ticket_config.php
```

For Python syntax validation, use the selected Python 3.12 interpreter:

```powershell
py -3.12 -m compileall ai\main.py
```

The repository does not currently define a formal PHPUnit test suite. Validate
the affected PHP syntax and manually exercise the corresponding page/workflow.

## 10. Project file map

```text
IT-Support-System/
├── admin/                         Administrative pages
│   ├── dashboard.php              Admin dashboard
│   ├── system_dashboard.php       System-admin dashboard
│   ├── users.php                  Account management (system admin)
│   ├── tickets.php                Admin ticket queue
│   ├── ticket_config.php          Levels, SLAs, admins, extension reasons
│   ├── solutions.php              Knowledge-base administration
│   ├── categories.php             Category administration
│   └── announcements.php          Announcement administration
├── ai/
│   ├── main.py                    Optional FastAPI RAG backend
│   └── requirements.txt           Python dependencies
├── assets/
│   ├── css/                       Shared, ticket, and assistant styles
│   └── js/                        Shared UI and assistant behavior
├── includes/
│   ├── db.php                     Local-only database config; do not publish
│   ├── auth_guard.php             Session, timeout, and role checks
│   ├── csrf.php                   CSRF helpers
│   ├── header.php / footer.php    Shared page shell and AI widget
│   ├── ticket_helpers.php         Assignment, SLA, and audit helpers
│   └── ai_chat.php                Authenticated PHP-to-FastAPI proxy
├── database_setup.sql             Core tables and sample data (destructive on rerun)
├── ticket_schema.sql              Ticket tables and sample levels/reasons
├── ticket_level_active_migration.sql
├── sla_settings_migration.sql
├── add_time_limit_migration.sql
├── ai_chat_migration.sql           Optional chat-log table
├── index.php                       Login screen
├── dashboard.php                   Role-based redirect
├── user_home.php                   Solution search and user actions
├── user_tickets.php                Signed-in user's ticket history
└── ticket_detail.php               Ticket information and permitted actions
```

## 11. Troubleshooting

| Symptom | Checks |
|---|---|
| Login page reports a database error | Start MySQL; verify `includes/db.php`, database name, user, and password |
| Login says the username/password is invalid | Confirm the account is active and reset its hash using section 8.5 |
| Apache shows a 404 | Confirm the project folder is under `C:\xampp\htdocs` and the `BASE_URL` in `includes/db.php` is `/IT-Support-System` |
| Ticket configuration says database update required | Inspect `ticket_levels`; if `is_active` is missing, back up and run `ticket_level_active_migration.sql` once |
| Ticket cannot be assigned | Confirm an active ticket level exists and at least one active admin is assigned to that level |
| AI assistant says backend is offline | Start Ollama and FastAPI, verify ports 11434 and 8000, and check model/dependency installation |
| AI API is healthy but cannot find useful articles | Confirm there are approved solutions and run **Re-index AI** after Ollama is available |
| A page is missing recent CSS/JavaScript | Reload the page and check browser developer tools for 404s on the asset requests |

## 12. Operational and data-care notes

- Back up MySQL before migrations, imports, deletions, or account maintenance.
- Do not rerun `database_setup.sql` on a database with data to preserve.
- Do not commit `includes/db.php`, passwords, generated hashes, or private user
  data.
- Change all initial/local passwords before using the application with real
  accounts.
- Keep approved support content accurate and current; the AI assistant uses
  indexed approved material and should not replace human support for incidents.
- Treat tickets and their audit records as operational data and retain them
  according to the organisation's policy.
