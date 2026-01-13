# Delivery Dates Manager

A powerful WooCommerce plugin for delivery date scheduling, specifically designed for Cairo-based delivery operations.

**Author:** Malek Fouda  
**Website:** [malekfouda.com](https://www.malekfouda.com)  
**Version:** 1.0.7  
**License:** GPL v2 or later

## Features

- **Shipping Zone Configuration** - Per-zone settings for delivery days, cutoff times, same-day options, and capacity limits
- **Same-Day Delivery** - Product-level eligibility with configurable cutoff times
- **Same-Day Pickup** - Product-level eligibility with separate cutoff time setting
- **Pickup Option** - Configurable pickup from Heliopolis with custom messaging
- **Cairo-Only Delivery** - Enforced Egypt/Cairo location for all orders
- **Dynamic Shipping Costs** - Automatically applies zone-specific flat rates from WooCommerce
- **Blocked Dates** - Global and zone-specific date blocking for holidays
- **Capacity Management** - Maximum orders per day per zone
- **Order Integration** - Full delivery info in admin, emails, and customer order views
- **HPOS Compatible** - Works with WooCommerce High-Performance Order Storage

## Requirements

- WordPress 5.8+
- WooCommerce 5.0+
- PHP 7.4+

## Installation

1. Download the `delivery-dates-manager` folder
2. Upload to WordPress via **Plugins > Add New > Upload Plugin**
3. Activate the plugin
4. Configure at **WooCommerce > Delivery Dates**

## Configuration

### Global Settings

- **Blocked Dates (All Zones)** - Dates unavailable for all zones (holidays, etc.)
- **Pickup Button Label** - Custom text for the pickup option at checkout
- **Pickup Cutoff Time** - Time after which same-day pickup is unavailable

### Zone Settings

For each WooCommerce shipping zone:

- **Enable Zone** - Activate delivery date selection
- **Allowed Delivery Days** - Which days of the week deliveries are available
- **Cutoff Time** - Orders after this time go to next available day
- **Same-Day Delivery** - Enable/disable same-day option
- **Max Orders Per Day** - Capacity limit (0 = unlimited)
- **Zone Blocked Dates** - Additional blocked dates for this zone only

### Product Settings

In each product's **Shipping** tab:

- **Same-Day Delivery** - Check if product is eligible for same-day delivery
- **Same-Day Pickup** - Check if product is eligible for same-day pickup

## Order Data

The plugin stores the following order meta:

| Meta Key | Description |
|----------|-------------|
| `_ddm_fulfillment_method` | 'delivery' or 'pickup' |
| `_ddm_delivery_zone` | Zone ID |
| `_ddm_delivery_zone_name` | Zone name |
| `_ddm_delivery_date` | Selected date (Y-m-d) |
| `_ddm_delivery_type` | 'same_day', 'same_day_pickup', 'pickup', or 'standard' |
| `_ddm_delivery_fee` | Delivery fee amount |

## Support

For support, feature requests, or bug reports, please contact [Malek Fouda](https://www.malekfouda.com).

## Changelog

### 1.0.7
- Added same-day pickup product eligibility
- Added pickup cutoff time global setting
- Fixed pickup date validation with blocked dates
- Improved order type display labels

### 1.0.6
- Added pickup option with configurable messaging
- Shipping fee removal for pickup orders
- Same-day delivery product eligibility

### 1.0.5
- Dynamic shipping costs from WooCommerce zones
- Blocked dates management (global + zone-specific)
- Capacity limits per zone

### 1.0.0
- Initial release

## Credits

Developed by **Malek Fouda**

---

Copyright (c) 2024-2026 Malek Fouda. All rights reserved.
