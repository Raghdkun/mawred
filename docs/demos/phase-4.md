# Phase 4 demo script: Integrations

About 20 minutes. Uses the demo company (see [phase-1.md](phase-1.md)); sign in as an HR admin with two-factor on. Keep a queue worker running (`composer dev`). For the unofficial WhatsApp driver, start the sidecar: `docker compose --profile whatsapp up -d`.

## 1. The hub (handoff 41)
1. **Admin → Integrations**: one card each for Email, WhatsApp (Official), Unofficial — WhatsApp Web (WAHA) and Cognito Forms, with a health badge, masked details, last success and last failure.
2. **Test** runs the check now (SMTP handshake, Meta phone lookup, sidecar session, Cognito failure rate). A change of badge alerts everyone who manages integrations, in the app and by email. `integrations:health` runs every 5 minutes.

## 2. Cognito Forms (D19, D60, D61)
1. **Cognito → New source**: name it, pick the request type (e.g. Expense reimbursement) and paste the form's public link. The next page shows the three webhook URLs **once**; paste them into the form's *Post JSON Data to a Website* settings (Submit, Update, Delete). **Rotate URLs** issues new ones; the old ones stop at once.
2. **Capture next entry**, submit a test entry in Cognito: it appears as the sample. Map each request-form field to a Cognito field (suggestions come from the sample), choose how to find the employee (email or employee number), and the fields to prefill.
3. Submit a real entry: it becomes the employee's request and follows the normal approvals. Attachments are downloaded before Cognito's links expire.
4. Edit the entry in Cognito: the open request's answers change. Delete it: the request is cancelled. Once decided, a change shows as *Late update*.
5. Submit with an unknown email: it waits as **Needs a person**; **Pick the person** starts their request. Failed entries show why; fix the mapping and **Run again**. The payload viewer is audited.
6. As an employee, **New request** shows the Cognito-backed type like any other; choosing it opens the Cognito form in a new tab with your name and email filled in.

## 3. WhatsApp (handoff 40, D58, D59)
1. **WhatsApp → Choose a driver** (Official, Unofficial, or both).
2. **Official**: phone number ID, WABA ID, access token and app secret (kept encrypted, never shown again). Copy the callback URL and verify token into Meta and subscribe to *messages*. **Sync from Meta** lists templates with approval badges.
3. **Unofficial**: read the ban-risk warning, enter the sidecar URL, session and API key, **Start pairing**, scan the QR from WhatsApp → Linked devices. The QR gives way to *Connected*.
4. **Routing**: per category, Official → Unofficial → Email (email always last). Putting a critical category on the unofficial driver alone shows the warning. Choose the authentication template used for number codes.
5. **Test and invite**: send a test to your own number; **Send invites** tells everyone without a confirmed number how to connect.
6. As an employee, **Notification settings → WhatsApp**: enter your number, confirm the code. The matrix now has a WhatsApp column.
7. Submit a request as someone whose manager has WhatsApp: the approval arrives on WhatsApp (template with **Approve / Reject** outside the 24-hour window). **Approve** decides on the spot; **Reject** and private requests reply with the confirmation link. If WhatsApp fails, the email goes out instead.
8. **Notification templates → WhatsApp**: map each message to an approved Meta template, its variables and its quick replies.

## 4. Key rotation
Follow [deploy/runbooks/rotate-app-key.md](../deploy/runbooks/rotate-app-key.md): `php artisan mawred:reencrypt --dry-run`, then without `--dry-run`.

## Checks
`composer test`, `composer test:browser` (integration screens in EN + AR with axe; `SCREENSHOTS=1` for the sweep), `pnpm test`.
