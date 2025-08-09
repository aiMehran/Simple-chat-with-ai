# Cyrus Ultimate (WordPress Plugin)

Modern project management SPA embedded in WordPress.

## Quick Start (Dev)

1. PHP deps
   - In plugin dir: `composer install`
2. JS deps
   - In plugin dir: `npm install`
3. Build assets
   - `npm run dev` (for HMR) or `npm run build` (outputs to `public/dist`)
4. Activate plugin in WP admin and add the shortcode `[cyrus_ultimate]` to a page.

## REST (initial)
- POST `/wp-json/cyrus/v1/auth/login`
- POST `/wp-json/cyrus/v1/auth/refresh`
- GET `/wp-json/cyrus/v1/users/me` (Authorization: Bearer <access_token>)