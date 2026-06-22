# TruckBill CRM API Documentation

Base URL: `https://admin.truckbill.in`

## Public API (Website)

Authentication: `X-Api-Key` header (server-side only in `submit.php`)

### Create Lead

`POST /api/v1/public/leads`

```json
{
  "name": "John Doe",
  "company_name": "Acme Transport",
  "mobile": "9876543210",
  "email": "john@example.com",
  "product_interested_in": "broker",
  "source": "website_demo",
  "remarks": "Optional message",
  "utm_source": "google",
  "utm_medium": "cpc",
  "utm_campaign": "demo",
  "landing_url": "https://truckbill.in/",
  "referrer": "https://google.com",
  "ip_address": "1.2.3.4"
}
```

Response `201`:

```json
{ "success": true, "message": "Lead created successfully.", "lead_id": 1 }
```

Rate limit: 20 requests/minute per IP.

---

## Public Onboarding API (Website SPA)

CORS allowed origins: `https://truckbill.in`, `https://www.truckbill.in`

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/v1/public/onboarding/{token}` | Validate token, return session state |
| POST | `/api/v1/public/onboarding/{token}/otp/send` | Send email OTP |
| POST | `/api/v1/public/onboarding/{token}/otp/verify` | Verify OTP `{ "otp": "123456" }` |
| POST | `/api/v1/public/onboarding/{token}/policy/accept` | Accept policies `{ "accepted": ["terms_and_conditions", ...] }` |
| POST | `/api/v1/public/onboarding/{token}/password` | Set password `{ "password": "...", "password_confirmation": "..." }` |
| POST | `/api/v1/public/onboarding/{token}/complete` | Complete onboarding `{ "password": "..." }` |

---

## Admin API (CRM)

Authentication: Laravel Sanctum bearer token

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/v1/admin/dashboards/crm` | Dashboard metrics |
| GET | `/api/v1/admin/leads` | List leads (paginated) |
| POST | `/api/v1/admin/leads` | Create lead manually |
| GET | `/api/v1/admin/leads/{id}` | Lead detail |
| PUT | `/api/v1/admin/leads/{id}` | Update lead |
| POST | `/api/v1/admin/leads/{id}/notes` | Add note |
| GET | `/api/v1/admin/leads/{id}/activities` | Activity timeline |
| POST | `/api/v1/admin/leads/{id}/invite` | Send onboarding invite `{ "product": "broker" }` |

---

## Product Provisioning API (called by Admin)

Each product exposes:

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/onboarding/create-user` | HMAC signature |
| POST | `/api/onboarding/update-user` | HMAC signature |
| GET | `/api/onboarding/status?email=` | HMAC signature |

Headers:
- `X-Timestamp`: Unix timestamp
- `X-Signature`: `hash_hmac('sha256', timestamp + '.' + body, secret)`
- `Idempotency-Key`: UUID for create-user
