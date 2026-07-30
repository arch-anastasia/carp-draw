# CarpDraw AI

A responsive multi-page interface for an AI-assisted product documentation
platform. The project includes a public marketing site and a working application
preview for photo-to-drawing, drawing-to-photo and photo-to-3D workflows.

## Stack

- Next.js 16 App Router APIs
- TypeScript
- Tailwind CSS 4 plus project-level CSS design tokens
- Vinext/Vite runtime for Cloudflare-compatible Sites deployment
- Lucide React icons

## Install

```bash
npm install
```

Node.js 22.13 or newer is required.

## Run locally

```bash
npm run dev
```

Open `http://localhost:3000`.

## Production build

```bash
npm run build
npm run start
```

The build output is written to `dist/`.

## Environment variables

Copy `.env.example` to `.env.local` when a production domain is known:

```text
NEXT_PUBLIC_SITE_URL=https://your-domain.example
```

The variable is optional for local development. It is used as the canonical
fallback for sitemap, robots and social metadata.

## Current functionality

- Responsive marketing landing page, FAQ and footer navigation
- Email sign-in preview with validation and repeat-submit protection
- Responsive app sidebar and profile menu
- Upload, drag-and-drop and paste flows with type/size validation
- Guided measurement, setup, review and result states
- Projects, plans and settings pages
- Contact form validation and success state
- SEO metadata, Open Graph image, robots, sitemap, favicon and custom 404
- Keyboard navigation, visible focus states, labels and ARIA attributes

## Production integrations to connect

The interface is complete, but the following services intentionally use safe
front-end preview states:

- OAuth/email authentication
- AI image interpretation and CAD geometry processing
- Durable project/file storage
- PDF/DXF/DWG/3D export generation
- Payment checkout
- Contact-form email delivery

Before production launch, connect secure server-side services for these
features, document file retention and replace this provisional privacy copy with
legal counsel-approved text.

## Deployment

The repository is configured for OpenAI Sites and Cloudflare Worker-compatible
output. A standard deployment consists of a successful production build,
packaging the `dist/` output, saving a Sites version and deploying that version.
