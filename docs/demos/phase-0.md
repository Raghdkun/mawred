# Phase 0 demo script: Foundation

About 15 minutes. Runs on a fresh local install.

## Prepare
```bash
docker compose up -d
php artisan migrate:fresh --seed
pnpm build
php artisan serve
php artisan mawred:install --name="Nora Al-Harbi" --email="admin@mawred.test"
```

## 1. First run (brief §4, handoff 1 and 2)
1. Open `http://localhost:8000/login`. Show the split layout and the hero on the reading-end half. Switch to **العربية**: the hero moves left, the form right, with no reload.
2. Open the one-time link printed by `mawred:install` and set a password. Two-factor enrolment is required before anything else (the Super Admin role requires 2FA).
3. Turn on 2FA from Security: QR code or setup key, then a 6-digit code. Recovery codes appear.
4. The wizard opens. Point out:
   - the rail filling like water;
   - Admin is already done, because 2FA is confirmed;
   - required steps (company, admin) can't be skipped;
   - later-phase steps show "available in a later release".
5. Company profile: enter EN and AR names and an accent. The live preview shows co-branding only (D22).
6. Review → **Finish setup** → "You're all set".

## 2. Shell and account (handoff 3, 44)
1. Home: greeting, today's date (add a Hijri line via Preferences → Show Hijri dates), empty agenda illustration.
2. ⌘K palette: jump to pages, switch language, toggle dark mode.
3. Account → Preferences: Arabic-Indic digits, then show dates and numbers change.
4. Account → Security: active sessions (sign out another browser), recent sign-in activity.
5. Resize to a phone width: bottom tab bar, compact top bar, layout mirrored in Arabic.

## 3. Roles & permissions (brief §6, handoff 38)
1. Roles: 12 seeded roles with bilingual labels and 2FA/API-only badges.
2. Open **HR Officer**:
   - Click a cell to cycle None → Own → Team → Department → Branch → All.
   - The cell menu offers "Team: direct reports only".
   - The orange dot marks a change from the seeded default; the floating bar counts unsaved changes.
   - **Review and save** shows the diff, then save.
3. Clone the role as "Regional HR / موارد بشرية إقليمية" and set field access (Salary: Read).
4. Users: assign roles, then **View as** for the read-only preview (permissions, fields, visible audit entries). Then **Sign in as** an employee: the impersonation banner appears; stop impersonating.

## 4. Audit (brief §19, handoff 43)
1. The audit log shows every step above, including impersonation, with "done while … was viewing as this user".
2. Filter by area (Sign-in & security), by actor (async picker → `/api/v1/users`), and by date.
3. Expand an entry: field diff, channel, IP, request id. Sensitive values would appear as `••••`.

## 5. Quality gates
```bash
composer lint:check && composer analyse && composer test && composer test:browser
pnpm format:check && pnpm lint && pnpm types:check && pnpm test && pnpm build
```
