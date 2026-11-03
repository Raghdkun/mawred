# Rotate the WhatsApp (Official) token

The Official driver uses a **permanent access token** of a Meta system user, and the **app
secret** to check webhook signatures. Both are stored encrypted in the integrations vault, never
in env. Rotate them when someone who could see them leaves, when Meta asks, or once a year.

## Access token

1. In Meta Business Settings → Users → System users, pick the Mawred system user → **Generate new
   token** for the WhatsApp app, with `whatsapp_business_messaging` and
   `whatsapp_business_management`. Copy it once.
2. In Mawred: **Integrations → WhatsApp → WhatsApp (Official)**, paste it into *Permanent access
   token* (leave the other secret fields empty to keep them) and save.
3. **Test connection**, then **Send a test message** to your own number.
4. Back in Meta, revoke the old token (System users → the user → the old token → Revoke).
5. Watch **Integrations** for a day: the health check runs every 5 minutes and shows a banner on
   the HR home if the driver disconnects.

## App secret

Resetting the app secret in Meta (App settings → Basic → App secret → Reset) breaks webhook
signature checks until Mawred has the new one, so do it in one sitting:

1. Reset it in Meta and copy the new secret.
2. Paste it into *App secret* in **Integrations → WhatsApp → WhatsApp (Official)** right away and save.
3. Reply to a test approval on WhatsApp and check it lands (Requests → the request's history).

Messages sent while the secrets didn't match are rejected by Mawred (bad signature) and are not
retried by Meta after a while: anyone who replied in that window replies again, or uses the email.

## Verify token

The webhook *verify token* only matters when (re)subscribing the callback URL in Meta. Changing it
in Mawred needs the same change in Meta → WhatsApp → Configuration → Webhook.
