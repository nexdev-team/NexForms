# NexForms

Lightweight contact forms for DP's WordPress sites — a WPForms replacement without the weight or the licence.

- **Visual builder** in wp-admin (WordPress' own React, no build step): drag & drop fields, live preview in light/dark, field settings, email and design tabs.
- **Fields:** text, email, phone (country picker), paragraph, dropdown, radio, checkboxes, consent (with links), number, date, website, hidden. Widths full / ½ / ⅓, stacking to full on phones.
- **Validation** in the browser and again on the server: required, length, min/max, regex pattern with custom message, allowed options only. Hidden values come from the saved form, never from the request.
- **Phone:** flag + calling code picker (preferred countries first, names in the site language), number normalised to E.164 (`+306912345678`); typing `+357…` or `00357…` overrides the picker.
- **Spam:** Cloudflare Turnstile (per form, `interaction-only`, server-side Siteverify with action + hostname check), honeypot, minimum fill time, per-IP rate limit. Behind Cloudflare the real visitor IP is read from `CF-Connecting-IP` only when the request comes from a Cloudflare edge address.
- **Email:** sent with `wp_mail()`, so FluentSMTP handles server and From address. HTML table, Reply-To = visitor, subject tokens like `{form_title}`, `{name}`, `{topic}`. Test-email button.
- **Design presets:** Clean, Rounded, Glossy, Outline, Underline, Theme (uses the theme's own input styles). Accent colour (defaults to the theme's primary), corner radius, size, full-width button. Font and text colour always come from the theme; borders/backgrounds are mixed from `currentColor`, so forms fit light and dark sites.
- **No storage of submissions.** Forms live in a private post type (`nexform`, JSON in post meta) and settings in one option. Rate-limit counters are short-lived transients.
- **Greek translation** for everything visitors see (`languages/nex-forms-el.mo`).

Front-end weight: ~8 KB JS + ~12 KB CSS (unminified), loaded only on pages with a form. Turnstile's script loads only when the form uses it.

## Usage

1. Upload/activate the plugin.
2. **NexForms → Settings:** paste the Turnstile site & secret keys (Cloudflare → Turnstile → add widget for the hostname). Send a test email.
3. **NexForms → Forms → Add new form**, edit, **Save** (Ctrl/Cmd+S).
4. Put the shortcode in a page: `[nexform id="12"]` (Shortcode block works in Gutenberg and Flatsome UX Builder).

## Hooks

| Hook | Use |
| --- | --- |
| `nexforms_submitted` (action) | `($form, $values, $context)` after a successful send — e.g. forward to Power Automate / a webhook. |
| `nexforms_pre_send` (filter) | Return `true`/`false` to replace the built-in email. |
| `nexforms_mail` (filter) | Change `to`, `subject`, `message`, `headers` before `wp_mail`. |
| `nexforms_turnstile_hostnames` (filter) | Extra hostnames accepted from Turnstile. |
| `nexforms_cloudflare_ranges` (filter) | Override Cloudflare IP ranges. |

Theme overrides: any `--nf-*` CSS variable on `.nf` (e.g. `.nf { --nf-accent: #0a7; --nf-radius: 4px; }`).

## Development

```
php tests/integration.php /path/to/wordpress   # 83 checks: REST, sanitising, validation, mail, Turnstile, rate limit, IPs
python3 bin/make-el-translation.py languages/nex-forms-el   # rebuild Greek .po/.mo
bin/build-zip.sh                                # dist/nex-forms-<version>.zip (no tests/bin)
```

The integration test needs a mu-plugin that captures `wp_mail` and fakes Turnstile Siteverify (see the header of `tests/integration.php`).

Requires WordPress 6.5+, PHP 8.0+. The `intl` extension is optional (localised country names and sorting).
