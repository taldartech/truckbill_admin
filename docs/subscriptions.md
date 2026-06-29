# Subscription system

Unified annual subscriptions are managed from **Admin** (pricing catalog, GST, promo codes). Each product stores subscription state locally.

## Admin

- Web UI: `/subscriptions/pricing`, `/subscriptions/gst`, `/subscriptions/promo-codes`, `/subscriptions/companies`
- Product APIs (HMAC): `GET /api/v1/products/{product}/subscription-catalog`, `POST /api/v1/products/{product}/promo-codes/validate`

## Products

- Active row: `company_subscriptions` (broker/partload) or `tenant_subscriptions` (ownfleet)
- Renewal queue: `upcoming_subscriptions`
- Payments: `subscription_payments` (amounts in **rupees** with paise decimals, e.g. `25164.24`)
- Dates: `starts_on`, `ends_on`, `trial_ends_on` are **date-only** fields
- Checkout (super_admin): `/subscription` with live quote API
- Admin proxy API: `/api/admin/subscriptions/*` (HMAC)

## Scheduler

`subscription:apply-upcoming` runs daily to apply pending upcoming plans when `ends_on < today()`.
