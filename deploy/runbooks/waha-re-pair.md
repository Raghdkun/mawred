# Re-pair the unofficial WhatsApp driver (WAHA)

"Unofficial — WhatsApp Web (WAHA)" (D31) runs WhatsApp Web for a company phone. The session
lives in the `waha` volume. WhatsApp ends it when the phone is offline for about 14 days, when
someone removes the linked device on the phone, after a WhatsApp ban, or when the volume is lost.

You know it happened when **Integrations** shows the driver disconnected (health check every 5
minutes, banner on the HR home) or **Integrations → WhatsApp → Unofficial (WAHA)** shows
"The session is stopped." Notifications meanwhile go by the next channel in each category's
routing (email always last).

## Re-pair

1. Have the company phone at hand, online, with WhatsApp open.
2. **Integrations → WhatsApp → Unofficial (WAHA)** → **Pair a phone** → **Start pairing**.
3. On the phone: WhatsApp → Linked devices → Link a device, and scan the QR in Mawred.
4. Wait for **Connected**, then **Test unofficial** with **Send a test message**.

## If it won't pair

- "The sidecar can't be reached": `deploy/dc.sh ps waha`, `deploy/dc.sh logs --tail 100 waha`,
  then `deploy/dc.sh up -d waha`.
- Pairing fails repeatedly: stop the session, then remove the stale one and start clean:
  `deploy/dc.sh stop waha`, `docker volume rm mawred_waha`, `deploy/dc.sh up -d waha`, re-pair.
- The number is **banned**: WhatsApp's decision; there is no appeal path through Mawred. Move
  critical categories to the Official driver or email in the routing step, and decide with HR
  whether to pair another number.

## Upgrading WAHA

`compose.prod.yaml` must pin a tested WAHA tag before go-live (D8). To upgrade: change the tag,
`deploy/deploy.sh up` in a quiet hour, then check the session is still **Connected** (an engine
change can need a re-pair).
