# 🛡️ G&G Support Portal — Setup Guide

---

## Part 1 — Install Required Software

### 1. XAMPP (PHP + MySQL + Apache)
- Download from **https://www.apachefriends.org**
- Install with default settings
- After install, open **XAMPP Control Panel** and start **Apache** and **MySQL** (both should turn green)

### 2. Git
- Download from **https://git-scm.com/download/win**
- Install with all default settings

---

## Part 2 — Download the Project

Open **Command Prompt** (press `Win + R` → type `cmd` → Enter) and run:

```bash
cd C:\xampp\htdocs
```
```bash
git clone https://github.com/areni03/gg-support.git
```

This downloads the project into `C:\xampp\htdocs\gg-support\`

---

## Part 3 — Configure the Database Connection

1. Go to `C:\xampp\htdocs\gg-support\includes\`
2. Find the file `db.example.php`
3. Make a copy of it and rename the copy to `db.php`
4. Open `db.php` with Notepad and make sure it looks like this:

```php
define('BASE_URL', '/gg-support');
define('DB_HOST',  'localhost');
define('DB_NAME',  'knowledgebase');
define('DB_USER',  'root');
define('DB_PASS',  '');
```

Save and close.

---

## Part 4 — Set Up the Database

1. Open your browser and go to **http://localhost/phpmyadmin**
2. Click **New** on the left panel
3. Name it `knowledgebase`, set encoding to `utf8mb4_unicode_ci`, click **Create**
4. Click on `knowledgebase` in the left panel → click the **SQL** tab
5. Open `C:\xampp\htdocs\gg-support\database_setup.sql` in Notepad → select all → copy → paste into phpMyAdmin SQL tab → click **Go**
6. Now open `ticket_schema.sql` the same way → copy → paste → **Go**

---

## Part 5 — Set the Passwords

1. Go to `C:\xampp\htdocs\gg-support\`
2. Open `generate_hash.php` in Notepad and make sure it says:

```php
<?php echo password_hash('Test@1234', PASSWORD_BCRYPT);
```

3. Visit **http://localhost/gg-support/generate_hash.php** in your browser
4. Copy the long hash that appears on screen
5. Go to phpMyAdmin → click `knowledgebase` → SQL tab → run:

```sql
UPDATE users SET password = 'PASTE_YOUR_HASH_HERE';
```

6. **Delete `generate_hash.php` after this step**

---

## Part 6 — Open the Portal

Go to **http://localhost/gg-support**

Login with:

| Role         | Username   | Password    |
|--------------|------------|-------------|
| System Admin | `sysadmin` | `Test@1234` |
| Admin        | `admin`    | `Test@1234` |
| User         | `user1`    | `Test@1234` |

> ⚠️ Change all passwords after first login.

---

## Part 7 — AI Assistant (Optional)

Only needed if you want the AI chat feature. The portal works perfectly fine without it — skip this entire part if you don't need AI.

### 1. Install Python 3.11
- Download from **https://www.python.org/downloads/release/python-3110/**
- During install ✅ check **"Add Python to PATH"**
- Click **Install Now**

### 2. Install Ollama
- Download from **https://ollama.com**
- Install it, then open Command Prompt and run:

```bash
ollama serve
```

Open a **second** Command Prompt window and run:

```bash
ollama pull nomic-embed-text
ollama pull llama3
```

> ⚠️ `llama3` is about **4.7 GB** — will take time depending on internet speed.

### 3. Start the AI Backend

Open Command Prompt and run:

```bash
cd C:\xampp\htdocs\gg-support\ai
pip install -r requirements.txt
python main.py
```

You should see `Uvicorn running on http://0.0.0.0:8000`

**Keep this window open** while using the AI feature.

### 4. Index the Knowledge Base

1. Log in as **Admin** or **System Admin**
2. Go to **Solution Management**
3. Click **🤖 Re-index AI**
4. Wait for the confirmation message

The AI chat button will now appear in the bottom-right corner for all users.

---

## 🔁 Every Time You Start the Portal

1. Open **XAMPP Control Panel** → start **Apache** and **MySQL**
2. *(If using AI)* Open Command Prompt → `cd C:\xampp\htdocs\gg-support\ai` → `python main.py`
3. Go to **http://localhost/gg-support**

---

## 🆘 Common Problems

| Problem | Fix |
|---------|-----|
| "Not Found" on the site | Make sure Apache is running in XAMPP |
| "Database connection failed" | Make sure MySQL is running, check `includes/db.php` credentials |
| "Invalid username or password" | Redo the generate_hash.php step |
| AI chat says "service unreachable" | Make sure `python main.py` is running in a separate window |
| `ollama` command not found | Restart Command Prompt after installing Ollama |
| Port conflict (Apache won't start) | Something is using port 80 — in XAMPP httpd.conf change `Listen 80` to `Listen 8090`, then use `http://localhost:8090/gg-support` |
