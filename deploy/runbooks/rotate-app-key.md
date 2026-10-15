# Rotate APP_KEY

Mawred encrypts sensitive columns (IDs, bank details, form answers, integration credentials) with `APP_KEY` (ADR-0007, ADR-0015). Blind indexes use `BLIND_INDEX_KEY` and are not affected.

1. Generate a new key without writing it: `php artisan key:generate --show`.
2. Put the **current** key in `APP_PREVIOUS_KEYS` (comma-separated if there are several) and the new key in `APP_KEY`, on every app, Horizon, scheduler and Reverb container.
3. Deploy. Old values still decrypt through `APP_PREVIOUS_KEYS`; new writes use the new key.
4. Run `php artisan mawred:reencrypt --dry-run`, check the counts, then `php artisan mawred:reencrypt`. It is safe to run again.
5. When it reports success, remove the old key from `APP_PREVIOUS_KEYS` and deploy again.

Side effects to expect: users are signed out (sessions and remember-me cookies are encrypted), outstanding ALTCHA challenges on public forms expire (their HMAC key derives from `APP_KEY`), and submission IP hashes made before the rotation no longer match new ones.
