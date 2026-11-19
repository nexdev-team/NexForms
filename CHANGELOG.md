# Changelog

Each release's section below is published as the GitHub release notes and shown in WordPress under
Plugins → NexForms → View details. Write it in English, for site owners: what changed and what to do, if anything.

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
