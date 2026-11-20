# Changelog

Each release's section below is published as the GitHub release notes and shown in WordPress under
Plugins → NexForms → View details. Write it in English, for site owners: what changed and what to do, if anything.

## 0.1.8

- **No links in the notification email.** The email no longer shows the page the form was sent from, and the email address and phone number are plain text instead of links. You reply from the inbox, so the links were not needed, and a reply no longer quotes a long address with ad-tracking codes (`gclid`, `_gl`).
- **Safer page address.** The page address is sent by the visitor's browser, so it could be anything. It is now kept only when it is on your own site, without the query string, and is passed only to the `nexforms_submitted` hook, never put in the email.

## 0.1.7

- **Ticket numbers.** Turn on *Ticket numbers* in a form's Email tab and every message gets a code such as `SUP-00042`. It is added to the email subject (easy to search and thread in the inbox), shown at the top of the email, and can be used in the success message and the auto-reply as `{ticket}`.
  - **Per section:** choose a dropdown or radio field (e.g. "Section") and give each option its own prefix, e.g. Orders → `ORD`, Returns → `RET`. Each prefix has its own counter. Options without a prefix use the form's prefix.
  - Only the counters are stored. Submissions are still not saved on the site.
- **Auto-reply to the visitor.** An optional "we received your message" email with your own subject and text (`{name}`, `{ticket}`, `{site_name}` and field keys work). When the visitor replies, the reply goes to the form's *Send to* address. Default texts are in English, Greek and Bulgarian, following the site language.
  - By default the auto-reply does **not** include a copy of what the visitor sent, so the form cannot be used to send arbitrary text to someone else's address. You can turn the copy on per form.

## 0.1.6

- **Much faster sending.** On a typical WooCommerce site a submission took about 3 seconds: ~1.4 s to load WordPress with every plugin and ~1.5 s for the mail provider (e.g. Gmail API through FluentSMTP).
  - *Fast submissions*: when a form is sent, only NexForms and the mail plugin are loaded. This uses a small helper file that NexForms installs in `wp-content/mu-plugins` and removes when it is turned off or the plugin is deactivated.
  - *Send the email after answering the visitor*: the success message appears immediately and the email is sent right after (LiteSpeed or PHP-FPM servers). Sending failures are recorded in FluentSMTP's log.
  - Both are on by default and can be turned off under NexForms → Settings → Speed.

## 0.1.5

- **"Send another message" after sending.** The success message now has a link that brings back an empty form, so visitors (and you, when testing) can send again without reloading the page.
- **Fresh form when coming back.** Returning to the page with the browser's Back button no longer shows the old success message instead of the form.
- The success box keeps softly rounded corners on the Rounded style.
- **Clearer recipient settings.** The form's *Send to* field shows which address is used when it is empty (the site admin email), and the test-email box in Settings says that it does not change where forms send.

## 0.1.4

- **NexDev branding.** The NexDev logo now appears in the WordPress admin menu, at the top of the Forms and Settings pages, in the form editor, and as the plugin icon under Dashboard → Updates (the same mark NexPilot uses).

## 0.1.3

- **Form names in shortcodes.** Give each form a name under Form → *Form name* and use `[nexform name="contact-bg"]` instead of a number. Names are created from the title (Greek and Bulgarian are transliterated), must be unique, and can be changed at any time. Existing forms get a name automatically when the plugin updates, and existing `[nexform id="…"]` shortcodes keep working.
- **All of Europe first in the phone country list.** The default list now has 54 European countries and territories, with Greece, Cyprus and Bulgaria on top. Settings → Phone field has two new buttons: *All European countries* and *None (A–Z only)*. Sites that already saved a list keep it until you press the button and save.
- **Phone default country follows the site language.** New phone fields start with Bulgaria on Bulgarian sites, Greece on Greek sites, and so on. Existing fields are not changed.

## 0.1.2

- **Bulgarian translation** for everything visitors see: the success and error messages, "Sending…", and Yes/No in emails. It is used automatically when the site language is Bulgarian, next to the existing Greek translation.

## 0.1.1

- **Turnstile on local and staging sites.** Cloudflare's testing keys now pass outside production, so the widget can be tested before going live. Production keeps the strict checks.
- **Reply-To fix.** The visitor's name no longer arrives wrapped in quotes in the email client.
- **Button styling.** Themes such as Flatsome no longer change the submit button to uppercase.

## 0.1.0

- First release: visual form builder, validation, phone field with country picker, Cloudflare Turnstile, design presets, sending through WordPress mail (FluentSMTP), no stored submissions, updates from GitHub.
