=== Delivery Dates Manager ===
Contributors: malekfouda
Tags: woocommerce, delivery, shipping, cairo, scheduling
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
WC requires at least: 5.0
WC tested up to: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A powerful delivery scheduling plugin for WooCommerce with Cairo-only shipping zones, same-day delivery options, and flexible date management.

== Description ==

Delivery Dates Manager is a comprehensive WooCommerce extension designed specifically for Cairo-based delivery operations. It allows customers to select their preferred delivery date during checkout while respecting your business rules and capacity constraints.

= Key Features =

* **Shipping Zone Integration** - Leverages WooCommerce's native shipping zones (Nasr City, New Cairo, etc.)
* **Zone-Specific Settings** - Configure each zone with:
  * Enable/Disable delivery scheduling
  * Allowed delivery days (select specific days of the week)
  * Cutoff time for same-day orders
  * Same-day delivery toggle
  * Maximum orders per day capacity
  * Flat delivery fee per zone
* **Product-Level Same-Day Eligibility** - Mark products as eligible for same-day delivery
* **Cairo-Only Checkout** - Forces Egypt/Cairo location for streamlined checkout
* **Smart Date Picker** - Respects all zone rules and shows only available dates
* **Dynamic Shipping Costs** - Automatically applies zone-specific delivery fees
* **Order Integration** - Delivery information displayed in admin, emails, and customer orders

= How It Works =

1. Create shipping zones in WooCommerce for Cairo areas
2. Configure delivery settings for each zone in the plugin settings
3. Mark products eligible for same-day delivery (optional)
4. Customers select their zone and preferred delivery date at checkout
5. Orders display delivery information throughout the order lifecycle

== Installation ==

1. Upload the `delivery-dates-manager` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce > Delivery Dates to configure zone settings
4. Create shipping zones in WooCommerce > Settings > Shipping if not already done

== Frequently Asked Questions ==

= Does this work with any WooCommerce shipping zone? =

Yes, the plugin works with all WooCommerce shipping zones. While designed for Cairo, it can be adapted for any location.

= How does same-day delivery work? =

Same-day delivery is available when:
- The zone has same-day delivery enabled
- The order is placed before the cutoff time
- All products in the cart are marked as same-day eligible

= Can I limit orders per day? =

Yes, each zone can have a maximum orders per day limit. Once reached, that date becomes unavailable for new orders.

== Changelog ==

= 1.0.0 =
* Initial release
* Zone-based delivery settings
* Product same-day eligibility
* Checkout date picker
* Order meta integration
* Admin UI with accordion layout
