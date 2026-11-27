# ADR 0003: Idempotent payment confirmation

## Status
Accepted

## Context
A payment can be confirmed through several paths: the browser callback after the hosted
payment page, the provider's server-to-server webhook, and provider retries of that webhook.
These can arrive in any order, twice, or concurrently. Each must not enroll twice, count a
coupon twice, or create two payment records.

## Decision
- The browser is never trusted. Every path calls the provider's retrieve API with the checkout
  token and uses that answer.
- Webhooks are verified first (iyzico `X-IYZ-SIGNATURE-V3`, HMAC-SHA256 with the secret key),
  then stored in `webhook_events` with a unique `(provider, idempotency_key)`. A repeated
  delivery hits the unique index and returns `duplicate` without doing any work.
- All paths end in `PaymentService::markPaid()`, which locks the order row
  (`SELECT … FOR UPDATE`) inside a transaction and returns early if it is already paid.
  This also covers the callback and webhook racing each other.
- The paid amount from the provider must equal the order total, otherwise the transaction
  is rolled back.
- `payments (provider, provider_payment_id)` is unique as a last line of defence.
- Side effects that leave the system (mail, SMS, e-invoice) run from the `OrderPaid` event,
  which is dispatched only after the transaction commits and only by the call that flipped
  the status.

## Consequences
- Fulfilment (enrollment, subscription activation, coupon usage) is safe to trigger from any
  path, any number of times.
- Webhook endpoints have no tenant header; the order is looked up across tenants by token and
  the rest of the work runs inside that tenant's context.
- Covered by `WebhookTest` (repeated deliveries, callback + webhook race, forged signature)
  and the Playwright purchase test.
