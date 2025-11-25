# BEIRS User Interface

Vue 3 + Vite single-page application for the Barangay E-Information & Reporting System (BEIRS).

## What the UI offers
- **Residents:** register/login, manage profiles, submit blotter reports with attachments, request certificates, and monitor case statuses with notifications.
- **Barangay staff:** review reports, inspect resident details, and approve or reject blotter and certificate requests.
- **Administrators:** manage user accounts, oversee approvals, and view analytical summaries such as heatmap-style incident distributions.

## Tech stack
- Vue 3 with TypeScript
- Vite dev/build tooling
- Vue Router & state management (Pinia)
- Vitest for unit testing

## Project setup
```sh
npm install
```

### Compile and hot-reload for development
```sh
npm run dev
```

### Type-check, compile, and minify for production
```sh
npm run build
```

### Run unit tests with [Vitest](https://vitest.dev/)
```sh
npm run test:unit
```

### Lint with [ESLint](https://eslint.org/)
```sh
npm run lint
```

## Environment variables
- Copy `.env.example` to `.env.local` for local development. The defaults target `http://localhost:8000/api` for the Laravel API and `http://localhost:8000/storage` for downloads served by the API.
- On Vercel, add `VITE_API_BASE_URL` and `VITE_STORAGE_URL` under **Project Settings → Environment Variables** pointing to your Render backend (e.g., `https://<render-service>.onrender.com/api` and `/storage`).
- If your backend stores media in Supabase, keep `VITE_STORAGE_URL` pointing at the API `/storage` route (or your CDN in front of it). The API will resolve or sign Supabase object URLs as needed, so you do not need to point the frontend directly at Supabase buckets.
