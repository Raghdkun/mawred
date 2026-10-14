# Phase 3b demo script: Requests, approvals, workflows and notifications

About 25 minutes. Uses the demo company (see [phase-1.md](phase-1.md)). Run `php artisan db:seed --class='Database\Seeders\Production\ProductionSeeder'` once to add the 15 request types and the onboarding/offboarding checklists to an existing database, and keep a queue worker running (`composer dev`, or `php artisan queue:work --queue=workflows,notifications,default`). Reverb (`composer dev`) pushes the bell live; without it notifications still land on refresh.

## 1. Ask for something (handoff 10–12) — sign in as `employee@mawred.test`
1. **Home**: quick requests (a scroller on phones), *My requests*, and the bottom tab bar with the raised **New** button.
2. **New request**: search and category pills; private types say so. Live types are the nine from D55; leave, shift swap, attendance, overtime and training wait for their phases (D55) and don't appear.
3. Open **Equipment or access**, pick *Laptop*, add details, **Send request**. The success ripple shows on the request, and the **Progress** rail (reading-start side) shows who it is waiting for. It refreshes while the request is open.
4. Add a **comment**. **Withdraw** asks for confirmation and an optional reason.

## 2. Decide it (handoff 13 ★) — sign in as the employee's manager (`team.lead@mawred.test`)
1. The **Approvals** nav item and the bell show a count. The approval email (Mailpit, `localhost:8025`) has **Approve / Reject** buttons in the recipient's language.
2. **Approvals**: 400 px list + detail. Approve with an optional comment; **Reject** and **Send back** need one. Several waiting? Select them and **Approve n**.
3. **Delegate** opens a picker of colleagues (names only). **Away?** sets "while I'm away" dates: new approvals go to your delegate.
4. On a phone, swipe a card toward the reading end to approve (mirrored in Arabic). Reduced motion turns swiping off; the buttons always work.
5. Open the email's **Approve** link in a private window: it shows a confirmation and decides nothing until you confirm. A used or expired link explains itself. Private request types (salary certificate, advance, resignation, details change) need you signed in first (D24).

## 3. Correct and resend
1. As the manager, **Send back** with a comment. The requester gets an in-app and email message, sees the comment on the request, **Make changes** (answers and files carried over) and **Send again**: it returns to the same step.

## 4. Shape the process (handoff 15 ★, desktop 1280+) — sign in as `hr.admin@mawred.test`
1. **Workflows**: every process with its live version, unpublished changes and what's running. **Needs attention** lists workflows stuck on a failed step with **Retry / Skip / Cancel**.
2. Open **Salary advance**: manager → department head → HR (D56). In Arabic the canvas flows right to left.
3. Click a step to edit it: who (manager, levels up, department head, branch manager, role, a person, a person named in the form…), "everyone must approve", the 48 h deadline, the 24 h reminder and who it escalates to.
4. Add a **Notify** step from the palette (or drag it in), connect it, and fill in the message. Changes save automatically; problems show on the step and in **Checks**. **Publish** creates version 2; running requests keep the version they started on.
5. **Test run**: fill sample answers, choose *Rejected* for HR, **Run**. The path lights up on the canvas and the outcome shows. Nothing is saved, sent or posted.
6. **Request types**: link a type to its form and workflow, mark it private or turn it off.

## 5. Notifications (handoff 42, 44)
1. **Bell → Settings**: category × channel switches (critical in-app messages are locked on), **Quiet hours** in your own timezone (non-urgent email waits), and the **daily digest**.
2. **Notification templates** (admin): pick an event, edit in-app and email wording in English and Arabic side by side, insert variables from the chips, preview, **Use the default wording** to reset. Unknown variables are refused.

## 6. Email set-up (setup wizard step 7)
1. **Setup → Email**: SMTP host, port, security, username, password and sender. The password is stored encrypted and never shown again; leave it empty to keep it. **Send a test email** delivers to you and records the connection's status.

## 7. People checklists
1. **People → Add person** (by hand): an onboarding checklist starts with to-dos for HR (laptop and accounts, documents) and the new person's manager (first week). Imports don't start one. Ending someone's employment starts the offboarding checklist.
2. **Update my contact or bank details** (as the employee) goes to HR only; once approved, the record updates itself.

## 8. API (ADR-0012)
`GET /api/v1/request-types`, `GET /api/v1/request-types/{id}` (with the form), `POST /api/v1/request-types/{id}/requests` (send `Idempotency-Key`), `GET /api/v1/requests`, `POST /api/v1/requests/{id}/withdraw`, `GET /api/v1/approvals`, `POST /api/v1/approvals/{id}/decision`. Out-of-scope records answer 404.

## Checks
- `composer test` (499), `composer test:browser` (79, EN + AR with axe; set `SCREENSHOTS=1` for the 390/834/1440 × LTR/RTL + dark sweep), `pnpm test` (145).
