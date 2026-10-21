# Phase 9a demo script: Hardening, push and the installable app

About 10 minutes, on a production build (`pnpm build`, `php artisan serve`) so the service worker runs. Push needs VAPID keys: `php artisan mawred:vapid-keys`, then put both values in `.env`.

## 1. Push notifications (D128)
1. As **employee@mawred.test**, **Notifications → Settings**: **Push notifications on this device → Turn on**, allow the browser prompt. The table gains a **Push** column per category.
2. Have a manager approve a request: the phone or browser shows a notification even with Mawred closed; tapping it opens the request.
3. **Turn off** stops this device only.

## 2. Installable app and updates
1. In Chrome or Edge, an **Install Mawred** card appears (Not now hides it for 30 days).
2. After a new deploy, an open Mawred shows **A new version of Mawred is ready → Refresh**.
3. Offline check-in: turn the network off, check in, close the tab, turn the network on: the punch is sent in the background (Chrome), and the page shows it next time.

## 3. Email bounces (D127)
1. As HR admin, **Integrations → Email**: **Create the webhook URL**, copy it into Postmark's Bounce and Spam Complaint webhooks (shown once).
2. A hard bounce or complaint lists the address under **Addresses Mawred doesn't email**; notifications to it skip email (WhatsApp still works), candidate emails are refused with a clear message. **Allow again** removes it.

## 4. Security
- Every page carries a Content-Security-Policy (`curl -I`): scripts only from Mawred with a per-request nonce, no framing by other sites. Violations are logged and reported to `/hooks/csp-report`.
- Uploaded employee documents, import files and the company logo are virus-scanned like form uploads (with ClamAV on).
- `/api/v1` answers 429 `rate_limited` after 300 calls a minute per person.

## Checks
`composer test` (upload scanning, CSP header and reports, API limit, bounces and suppression, push subscriptions and routing), `composer test:browser` (CSP sweep with zero violations, dark-mode axe across the main screens in EN + AR), `composer test:performance` (5,000-employee lists, dashboards and report previews), `pnpm test`, `composer audit`, `pnpm audit`.
