# JMAC web

Read docs/ARCHITECTURE.md, docs/PLAN.md, docs/contracts/CMS_PREVENTAS.md and docs/STATUS.md before changes. Contract source is the owner's JMAC 2.0 package, retained outside the release in /workspace/reference/JMAC_2_0_Codex.

PHP 8.3+ / Laravel 13 / Blade / MySQL-MariaDB. No WordPress or old catalogue. No invented corporate facts. Seeds must keep all commercial data empty and corporate pages draft. Candidate images must not become public before owner approval.

Proceed one tested milestone at a time. Authenticate, verify MFA, authorize server-side, validate and audit mutations. Keep private storage/.env/vendor outside document root. Never change production/DNS/cPanel without explicit consent. Build static assets locally/CI; no persistent Node/queue requirements in cPanel.

Use existing isolated checkout; do not create worktrees unless requested. Tests only against SQLite memory or disposable *_test databases. Local cloud tools: source /workspace/toolchain/activate. Do not print secrets or commit .env. Tests: php artisan test; DB_CONNECTION=mysql DB_DATABASE=jmac_test php artisan test; vendor/bin/pint --test; npm run build. Record failed/unrun checks honestly.
