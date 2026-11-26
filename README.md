# apps.ren

Source for **[apps.renienamocot.com](https://apps.renienamocot.com)**, a one-page showcase of the apps built by Renie Namocot.

Plain HTML, CSS and JS with a small PHP admin. No build step.

## Structure

| Path | What it is |
|---|---|
| `index.html` | The page. App cards, SEO meta and structured data live here. |
| `styles.css`, `main.js` | Styles; chat widget, visitor counter and revenue display. |
| `admin/` | Password-protected admin for setting MRR / revenue per app. |
| `data/revenue.json` | Revenue numbers written by the admin, read by the page. |
| `private/` | Server-only settings, blocked from the web by `.htaccess`. |
| `logos/`, `shots/` | App icons and 1280×800 homepage screenshots. |

## Deploy (Hostinger)

1. Upload everything to the `apps` subdomain folder.
2. Copy `private/config.example.php` to `private/config.php` and set `setup_key` to a long random string.
3. Visit `/admin/`, enter the setup key, and create your password.
4. Make sure `data/` and `private/` are writable.

## Add an app

1. Add a card to `index.html` with a unique `data-app="slug"` (copy an existing `<li>`), plus a logo in `logos/` and a screenshot in `shots/`.
2. Add the same slug to `apps` in `private/config.php`.
3. Add it to the JSON-LD list and `sitemap.xml`.

## Contact chat

Set `web3formsKey` in `main.js` (free key from web3forms.com) so messages go straight to your inbox. Without it, the chat opens the visitor's email app.
