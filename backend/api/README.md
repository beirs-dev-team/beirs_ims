# BEIRS API

The BEIRS API powers the Barangay E-Information & Reporting System. It is built on Laravel and exposes the secured endpoints that support the resident, barangay staff, and admin experiences.

## What the API does
- **Authentication & authorization:** Role-based guards secure resident, staff, and admin workflows.
- **Resident services:** Handles registration, profile updates, blotter/case submissions with attachments, and certificate requests.
- **Case & certificate processing:** Supports review, approval, or rejection flows for barangay staff and administrators, including notification dispatch.
- **Reporting:** Provides data needed for analytics such as heatmap-style summaries derived from incident locations.
- **Notifications & audit trails:** Sends status updates (SMS/in-app) and records interaction history for monitoring.

## Key directories
- `app/Http/Controllers` – Request handling for cases, certificates, authentication, and notifications.
- `app/Models` – Eloquent models for users, cases, certificates, and related entities.
- `database/migrations` – Schema definitions for user management, reporting, and request tracking tables.

## Getting started
1. Install dependencies: `composer install`
2. Copy the environment file and set credentials: `cp .env.example .env`
3. Generate the app key: `php artisan key:generate`
4. Run migrations (and seeders if available): `php artisan migrate --seed`
5. Start the API: `php artisan serve`

> Tip: Use dedicated API tokens/guards per role when exercising endpoints during development.

## Environment variables
Use `.env.example` as the source of truth for both local development and Render deployment.

### Local development
- Copy `.env.example` to `.env` and update the Postgres credentials under the _Local development settings_ block if needed.
- Set `FRONTEND_URL`, `CORS_ALLOWED_ORIGINS`, and `SANCTUM_STATEFUL_DOMAINS` to your Vite dev server URL (default `http://localhost:5173`).
- Run `php artisan key:generate` to populate `APP_KEY`.

### Render deployment
- In Render, create environment variables using the _Render deployment_ block in `.env.example` (Render provides `RENDER_EXTERNAL_URL`, `PGHOST`, `PGPORT`, `PGDATABASE`, `PGUSER`, `PGPASSWORD`, and `DATABASE_URL`).
- Point `FRONTEND_URL`, `CORS_ALLOWED_ORIGINS`, and `SANCTUM_STATEFUL_DOMAINS` to your Vercel domain so CORS and Sanctum cookies work in production.
- Keep `APP_ENV=production` and `APP_DEBUG=false` for the hosted service.

## Supabase Storage setup
The API uploads IDs and blotter evidences to Supabase using the S3-compatible driver. To make uploads work:

1. In Supabase, go to **Project Settings → Storage → S3 Access** and generate an **Access key ID** and **Secret access key**. Copy the endpoint shown there (e.g., `https://<project-ref>.supabase.co/storage/v1/s3`).
2. Create or choose a bucket for uploads (e.g., `beirs-media`). Buckets are **private by default**—keep them private for restricted access or add a read policy if you want public objects. For private buckets, expose downloads via signed URLs (e.g., Storage API `object/sign`).
3. Set these values in `.env`:
   - `SUPABASE_ACCESS_KEY_ID`
   - `SUPABASE_SECRET_ACCESS_KEY`
   - `SUPABASE_BUCKET`
   - `SUPABASE_S3_ENDPOINT`
4. If you want Supabase to be the default filesystem, set `FILESYSTEM_DISK=supabase` (otherwise the code explicitly selects the `supabase` disk where needed).

Uploaded files will live under the bucket path you configure (e.g., `government-ids/{userProfileId}/` and `blotter-evidences/{blotterReportId}/`). For public buckets, files can be viewed at `https://<project-ref>.supabase.co/storage/v1/object/public/<bucket>/<path>`.
