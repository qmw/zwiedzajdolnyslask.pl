# zwiedzajdolnyslask.pl

Landing page for the **Zwiedzaj Dolny Śląsk** mobile app (iOS + Android), in Polish, English and German, plus a single "smart" QR code / link that sends every phone to the right app store.

- App Store: https://apps.apple.com/pl/app/id6783342409
- Google Play: https://play.google.com/store/apps/details?id=com.pdw.zds

## How it works

```
src/template.html   one HTML template with {{placeholders}}
src/i18n.json       all texts, per language (pl / en / de)
public/             static assets (screenshots, badges, QR, icons)
build.mjs           renders template × languages into dist/
vercel.json         build config + the smart /app redirect
```

`node build.mjs` produces:

| URL              | file                  |
|------------------|-----------------------|
| `/`              | `dist/index.html` (PL) |
| `/en`            | `dist/en/index.html`  |
| `/de`            | `dist/de/index.html`  |

No framework, no dependencies. Edit texts in `src/i18n.json`, layout/CSS in `src/template.html`.

Local preview: `npm run dev` (builds and serves `dist/` on http://localhost:3000).

## The smart QR code — `https://zwiedzajdolnyslask.pl/app`

The QR code (in `public/qr/`) encodes **`https://zwiedzajdolnyslask.pl/app`** — our own URL, never a store URL directly. That means the printed code never goes stale: we control where it lands by changing `vercel.json`, not by reprinting posters.

`/app` is handled by Vercel redirects (server-side, no JavaScript, works from any QR scanner or camera app):

| Visitor                       | Goes to                                  |
|-------------------------------|------------------------------------------|
| iPhone / iPad / iPod          | App Store page of the app                |
| Android                       | Google Play page of the app              |
| anything else, browser in DE  | `/de#pobierz`                            |
| anything else, browser in EN  | `/en#pobierz`                            |
| anything else                 | `/#pobierz` (PL landing page, download section) |

If the app is already installed, both stores show an **Open** button, so the QR "just works" for existing users too.

### Print files

- `public/qr/zwiedzajdolnyslask-app.svg` — vector, use this for print (scales to any size).
- `public/qr/zwiedzajdolnyslask-app.png` — 2048 px, for tools that cannot use SVG.

Error-correction level **H** (30 %), so a small logo may be placed over the centre if desired. Keep a quiet zone (white margin) of at least 4 modules around the code.

### Upgrade path: open the app *directly* (deep links)

Right now the QR lands on the store page (with an "Open" button if installed). To make iOS / Android skip the store and open the app straight away, the **app** must claim the domain, and this site must serve two association files. This needs values only available from the app project (Apple Team ID, Android signing-key SHA-256), so it is not set up yet.

1. Add to `public/.well-known/apple-app-site-association` (no file extension, JSON):
   ```json
   { "applinks": { "details": [ { "appIDs": ["TEAMID.com.pdw.zds"], "components": [ { "/": "/app" } ] } ] } }
   ```
2. Add to `public/.well-known/assetlinks.json`:
   ```json
   [{ "relation": ["delegate_permission/common.handle_all_urls"],
      "target": { "namespace": "android_app", "package_name": "com.pdw.zds",
                  "sha256_cert_fingerprints": ["AA:BB:…"] } }]
   ```
3. Add a `headers` rule in `vercel.json` so `/.well-known/apple-app-site-association` is served with `Content-Type: application/json`.
4. In the app: iOS — Associated Domains entitlement `applinks:zwiedzajdolnyslask.pl`; Android — an intent filter for `https://zwiedzajdolnyslask.pl/app` with `android:autoVerify="true"`.

The QR code and the printed posters do not change.

## Deploy (Vercel + OVH DNS)

1. Vercel → *Add New Project* → import this GitHub repo. Framework preset: **Other**. Build command / output dir are read from `vercel.json` (`node build.mjs` → `dist`). Deploy.
2. Vercel → Project → *Settings → Domains* → add `zwiedzajdolnyslask.pl` **and** `www.zwiedzajdolnyslask.pl` (set `www` to redirect to the apex). Vercel shows the exact DNS records to create.
3. OVH → *Web Cloud → Domain names → zwiedzajdolnyslask.pl → DNS zone*:
   - delete OVH's default `A` / `AAAA` records for `@` (root) and any `www` record (parking page);
   - add `A` record, subdomain empty, target `76.76.21.21` (or the IP shown by Vercel);
   - add `CNAME` record, subdomain `www`, target `cname.vercel-dns.com.` (or the value shown by Vercel);
   - keep OVH nameservers as they are — nothing else changes.
4. Wait for DNS (minutes to a few hours). Vercel issues the TLS certificate automatically.

## Assets & credits

Screenshots and icon come from the store listings. Store badges are the official Apple / Google artwork and must not be modified. Privacy policy: https://projektdawnywroclaw.pl/polityka-prywatnosci

© PDW Sp. z o.o.
