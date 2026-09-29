# Contributing to G&G Support Portal

Thank you for wanting to contribute. Here is how to do it correctly.

## Getting Started

1. Fork the repository on GitHub
2. Clone your fork locally:
   ```bash
   git clone https://github.com/YOUR-USERNAME/gg-support.git
   ```
3. Follow the setup steps in SETUP.md to get it running locally
4. Create a branch for your work:
   ```bash
   git checkout -b feature/your-feature-name
   ```

## Branch Naming

Use clear descriptive names:
- `feature/ticket-email-notifications`
- `fix/sla-countdown-bug`
- `ui/announcement-drag-drop`
- `docs/api-documentation`

## Making Changes

- Test your changes locally before submitting
- Make sure existing features still work after your change
- Keep db.php out of your commits (it is in .gitignore)
- Never commit real credentials, API keys, or passwords

## Submitting a Pull Request

1. Push your branch:
   ```bash
   git push origin feature/your-feature-name
   ```
2. Go to GitHub and open a Pull Request
3. Title: clear summary of what you changed
4. Description: explain what the change does and why
5. If fixing a bug, describe how to reproduce the original bug

## Code Style

- PHP files start with `<?php` — no closing `?>`  tag in pure PHP files
- Use prepared statements for all database queries — no raw SQL with user input
- Follow the existing file structure (admin pages in admin/, shared code in includes/)
- Comment anything non-obvious

## What We Need Help With

Check the Roadmap in README.md. Current priorities:
- Asset management module
- Email notifications for ticket assignment
- Better mobile responsive layout
- More TinyMCE font and formatting options
