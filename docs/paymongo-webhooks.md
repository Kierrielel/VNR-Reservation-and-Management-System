# PayMongo webhook configuration

The `POST /webhooks/paymongo` endpoint verifies the `Paymongo-Signature` header before processing any event.

Set `PAYMONGO_WEBHOOK_SECRET` in `.env` to the signing secret for this endpoint from PayMongo. This is different from `PAYMONGO_SECRET_KEY`, which authenticates API calls. Never commit the actual secret.

The signature mode defaults to live when `PAYMONGO_SECRET_KEY` starts with `sk_live_`; otherwise it defaults to test. Set `PAYMONGO_LIVE_MODE=true` or `false` only when an explicit override is needed. The webhook endpoint and API keys must use the same mode.

After changing environment configuration, run `php artisan config:clear`. For deployments that cache configuration, rebuild it with `php artisan config:cache`.

The middleware checks HMAC-SHA256 over `timestamp.raw_body` using a constant-time comparison. Test mode uses `te`; live mode uses `li`. Requests must be within five minutes of the server clock, so keep the server time synchronized.

- Missing signing secret: HTTP 503; no payment updates.
- Missing, malformed, incorrect, expired, or future-dated signature: HTTP 401; no payment updates.
- Valid signature: continue to event processing; repeated paid events retain the existing confirmation.

Run the focused tests with `php artisan test --filter=PayMongoWebhookSignatureTest`. These use synthetic signatures and an isolated database; they do not contact PayMongo.

Reference: [PayMongo webhook setup and security](https://docs.paymongo.com/do/docs/developer-tools-webhook-setup-management).
