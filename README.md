# Community Ambulance Company

Website for [Community Ambulance Company, Inc.](https://communityamb.org) — a volunteer 501(c)(3) providing emergency medical services to Sayville, West Sayville, Oakdale, Bayport, and Bohemia, NY since 1950.

## Stack

- **CMS**: [Statamic 6](https://statamic.com) (flat-file, Laravel-based)
- **Framework**: Laravel 13 / PHP 8.4+
- **Frontend**: Tailwind CSS 4, Alpine.js 3, Vite 8
- **Hosting**: Hostinger (deployed via [Deployer](https://deployer.org))

## Local Development

```bash
# Install dependencies
composer install
npm install

# Copy environment and generate key
cp .env.example .env
php artisan key:generate

# Start dev servers (PHP, Vite, queue worker, log tail)
composer run dev
```

The site runs at `http://localhost:8000`. The Statamic control panel is at `/cp`.

## Project Structure

```
content/            Flat-file CMS content (collections, globals, navigation)
resources/
  blueprints/       Statamic field definitions
  forms/            Form configurations (contact, join, youth squad)
  views/            Antlers templates
  css/              Tailwind CSS
  js/               Alpine.js + gallery + reCAPTCHA
config/statamic/    Statamic configuration
app/
  Http/Middleware/   Security headers, rate limiting, reCAPTCHA validation
  Listeners/         Form submission handlers (email, Zapier webhooks)
```

## Forms

Three public forms, all protected by honeypot, rate limiting (5/min/IP), and optional reCAPTCHA v3:

| Form | Path | Handler |
|------|------|---------|
| Contact Us | `/contact-us` | Email to secretary/board |
| Join Community | `/join-community` | Zapier webhook |
| Youth Squad | `/join-community/join-youth-squad` | Zapier webhook |

## Environment Variables

See `.env.example` for the full list. Key production settings:

| Variable | Purpose |
|----------|---------|
| `QUEUE_CONNECTION` | Set to `database` in production for async form processing |
| `RECAPTCHA_SITE_KEY` / `RECAPTCHA_SECRET_KEY` | Google reCAPTCHA v3 (optional — forms work without it) |
| `ZAPIER_JOIN_WEBHOOK_URL` / `ZAPIER_YOUTH_WEBHOOK_URL` | Zapier endpoints for membership forms |
| `CONTACT_FORM_TO` / `CONTACT_FORM_CC` | Email recipients for contact form |
| `STATAMIC_LICENSE_KEY` | Required for Statamic Pro features |

## Deployment

Deployments use Deployer for atomic releases with zero-downtime symlink swaps.

```bash
# Manual deploy (usually triggered by GitHub Actions)
vendor/bin/dep deploy production
```

The deploy workflow (`.github/workflows/deploy.yml`) runs CI first, builds Vite assets in the runner, then deploys code and uploads built assets to the server.

## CI/CD

- **CI**: Runs on every PR and push to main — Pint lint, Vite build, PHPUnit tests
- **Deploy**: Manual trigger via GitHub Actions (workflow_dispatch)
- **Dependabot**: Weekly PRs for Composer, npm, and GitHub Actions updates
- **Branch protection**: `main` requires passing CI (`test-and-build` check)
