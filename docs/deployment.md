# Deployment Guide

## Admin CRM (`admin.truckbill.in`)

```bash
cd admin
composer install --no-dev
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
npm ci && npm run build
```

### Required environment variables

```env
APP_URL=https://admin.truckbill.in
WEBSITE_API_KEY=<shared-secret-with-website>
CORS_ALLOWED_ORIGINS=https://truckbill.in,https://www.truckbill.in
ONBOARDING_FRONTEND_URL=https://truckbill.in

OWNFLEET_API_URL=https://fleet.truckbill.in
OWNFLEET_SIGNING_SECRET=<shared-with-ownfleet>

PARCHUN_API_URL=https://parchun.truckbill.in
PARCHUN_SIGNING_SECRET=<shared-with-partload>

BROKER_API_URL=https://live.truckbill.in
BROKER_LOGIN_URL=https://live.truckbill.in/login
BROKER_SIGNING_SECRET=<shared-with-broker-app>
```

Run queue worker for provisioning retries: `php artisan queue:work`

---

## Website (`truckbill.in`)

Deploy static HTML/PHP site. Configure `.env`:

```env
ADMIN_API_URL=https://admin.truckbill.in
ADMIN_API_KEY=<same-as-admin-WEBSITE_API_KEY>
```

Ensure Apache rewrite for `/onboarding/{token}` is active (see `.htaccess`).

---

## Product Apps

Each product needs:

```env
ONBOARDING_SIGNING_SECRET=<matching-admin-signing-secret>
```

Run migrations:

```bash
php artisan migrate
```

---

## Smoke Test Checklist

1. Submit demo form on website → lead appears in Admin CRM
2. Assign lead, change status, add note
3. Send onboarding invite → open link on website
4. Complete OTP, policies, password flow
5. Verify user created in target product with `email_verified_at` and `policy_accepted_at`
6. Login to product app

## Default Admin Users (development)

| Email | Password | Role |
|-------|----------|------|
| admin@truckbill.in | password | Super Admin |
| sales.manager@truckbill.in | password | Sales Manager |
| sales@truckbill.in | password | Sales Executive |
| sales.partner@truckbill.in | password | Sales Partner |

Change passwords before production deployment.
