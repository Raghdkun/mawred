# ADR-0016: Notifications and logic-less templates

- Status: Proposed
- Date: 2026-10-13

## Decision
- **Categories**: a registry (`approvals`, `requests`, `leave`, `attendance`, `roster`, `security`, `documents`, `hiring`, `training`, `integrations`, `digest`, …). Each declares a criticality, the channels allowed, the variables available (typed) and the default templates.
- **Templates**: `notification_templates (category, channel, locale, subject?, body, wa_template_name?)`. Rendering uses `SafeTemplate`, which substitutes `{{ var }}` and `{{ var | date }}` from the allow-listed variables only, HTML-escapes for mail and has no loops or logic. **Blade is never compiled from DB content.** Mail wraps the rendered body in a branded RTL-aware Blade layout from files, using the email header assets.
- **Delivery**: `NotificationRouter` picks channels from the user's preferences, quiet hours (deferring non-critical messages) and the category's channel chain. WhatsApp goes through `WhatsAppRouter` with its fallback chain. Each attempt is a row in `notification_deliveries` (partitioned) with its status.
- **Digest**: a daily job per user timezone bundles non-critical in-app items into one email.
- Critical categories (approvals, security, password reset) ignore quiet hours and digest, and email cannot be disabled for `security`.
