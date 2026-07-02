# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

WooCommerce plugin (**Delivery Dates Manager**) for Cairo-only delivery scheduling: per-zone delivery days/cutoffs, same-day delivery & pickup, capacity limits, blocked dates. Plain PHP, no framework, no dependency manager. The installable plugin lives in the `delivery-dates-manager/` subdirectory — repo root only holds tooling (`validate.php`), docs, and a build zip.

## Commands

- **Validate PHP syntax + print plugin tree/info:** `php validate.php` — the only automated check in the repo. Runs `php -l` over every `.php` file under `delivery-dates-manager/`. This is what the Replit "Project" workflow runs. There is **no** test suite, linter, or build script.
- **Build the distributable:** zip the `delivery-dates-manager/` folder (e.g. `zip -r delivery-dates-manager-<version>.zip delivery-dates-manager`). WordPress installs the folder via Plugins > Add New > Upload.
- Runs inside a real WordPress + WooCommerce install; there is no local run target here.

## Version bumps

Version is duplicated in three places — keep in sync: the `Version:` header **and** `DDM_VERSION` constant in `delivery-dates-manager/delivery-dates-manager.php`, plus `readme.txt`, `README.md`, and `DEVELOPER.md`.

## Architecture

Singleton entry point `delivery-dates-manager.php` (`Delivery_Dates_Manager::instance()`, booted on `plugins_loaded`) requires five feature classes in `includes/`, each self-registering its own WordPress/WooCommerce hooks in its constructor:

- `class-ddm-admin.php` — settings page under **WooCommerce > Delivery Dates**; per-zone config + global options.
- `class-ddm-checkout.php` — injects checkout fields, computes available dates, server-side validation, date/availability AJAX. Largest/most complex class.
- `class-ddm-shipping.php` — rewrites WC shipping rates/labels, adds delivery fee, tracks fulfillment method + selected zone in WC **session**.
- `class-ddm-product.php` — product/variation same-day eligibility checkboxes (Shipping tab).
- `class-ddm-order.php` — renders delivery meta in admin, customer views, emails, and an orders-list column (dual code paths for legacy posts + HPOS).

`before_woocommerce_init` declares HPOS (`custom_order_tables`) compatibility — order code must not assume the legacy post-meta store.

Full hook/method/AJAX reference: `delivery-dates-manager/DEVELOPER.md`. Data contract (option, order-meta, product-meta keys): `README.md`.

## Conventions & gotchas (non-obvious)

- **Opt-out eligibility model:** product meta `_ddm_same_day_eligible` / `_ddm_same_day_pickup_eligible` — empty string `''` means **eligible**; only `'no'` excludes. Don't treat missing meta as ineligible.
- **Settings are saved bypassing `options.php` (v1.1.6).** All four DDM options (`ddm_zone_settings`, `ddm_global_blocked_dates`, `ddm_pickup_message`, `ddm_pickup_cutoff_time`) are stored with `autoload = 'no'` and saved directly to avoid memory exhaustion on sites with oversized autoloaded options. When adding a new option, follow the same pattern (`add_option(..., '', 'no')` + `force_no_autoload()`); do **not** reintroduce autoloaded options or route saves through the standard Settings API submit.
- **Cairo-only is enforced, not cosmetic:** checkout filters lock country to Egypt and state to Cairo. Location logic assumes this.
- **All AJAX uses one nonce:** `ddm_checkout_nonce`. Handlers are registered for both `wp_ajax_` and `wp_ajax_nopriv_` (guests check out). Always `check_ajax_referer('ddm_checkout_nonce', 'nonce')` and sanitize `$_POST`.
- **Date format is `Y-m-d` everywhere** (options store comma-separated blocked dates); zone `allowed_days` use `0=Sunday..6=Saturday`; `max_orders = 0` means unlimited.
- Frontend assets (jQuery UI datepicker + `ddm-checkout.js`) load only on `is_checkout()`; admin assets only on the settings page and product edit screens.
- `DEVELOPER.md` is gitignored (shipped in the plugin, not tracked); `attached_assets/` (screenshots) is gitignored too.
