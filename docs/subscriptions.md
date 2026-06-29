# Subscription system

Unified annual subscriptions are managed from **Admin** (pricing catalog, global settings, promo codes). Each product stores subscription state locally.

## Admin

- Web UI: `/subscriptions/pricing`, `/subscriptions/settings`, `/subscriptions/promo-codes`, `/subscriptions/companies`
- Global settings (`subscription_settings`): `grace_period_days`, `gst_rate_percent`
- Product APIs (HMAC): `GET /api/v1/products/{product}/subscription-catalog`, `POST /api/v1/products/{product}/promo-codes/validate`

## Products

- Active row: `company_subscriptions` (broker/partload) or `tenant_subscriptions` (ownfleet)
- Renewal queue: `upcoming_subscriptions`
- Payments: `subscription_payments` (amounts in **rupees** with paise decimals, e.g. `25164.24`)
- Dates: `starts_on`, `ends_on`, `trial_ends_on` are **date-only** fields
- Checkout (super_admin): `/subscription` with live quote API
- Admin proxy API: `/api/admin/subscriptions/*` (HMAC)
- Access tiers: **full** (active), **grace** (read-only after expiry), **locked** (after grace period)

## Scheduler

`subscription:apply-upcoming` runs daily as a safety net. Pending renewals are also applied on first request after expiry.
