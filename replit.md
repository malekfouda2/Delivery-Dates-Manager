# Delivery Dates Manager - WordPress Plugin

## Overview
A production-ready WooCommerce plugin for delivery date scheduling, specifically designed for Cairo-based delivery operations.

## Project Structure
```
delivery-dates-manager/
├── delivery-dates-manager.php    # Main plugin file
├── readme.txt                    # WordPress plugin readme
├── includes/
│   ├── class-ddm-admin.php       # Admin settings page
│   ├── class-ddm-checkout.php    # Checkout customization (Cairo-only enforced)
│   ├── class-ddm-order.php       # Order data handling & display
│   ├── class-ddm-product.php     # Product meta for same-day eligibility
│   └── class-ddm-shipping.php    # Shipping cost calculations
└── assets/
    ├── css/
    │   ├── ddm-admin.css         # Admin styles
    │   └── ddm-frontend.css      # Checkout styles
    └── js/
        ├── ddm-admin.js          # Admin accordion UI
        └── ddm-checkout.js       # Date picker & zone selection
```

## Features Implemented
1. **Shipping Zone Settings** - Per-zone configuration for delivery days, cutoff times, same-day options, max orders, and flat fees
2. **Product Same-Day Eligibility** - Checkbox in product edit screen
3. **Cairo-Only Checkout** - Enforced Egypt/Cairo (hidden fields + server-side validation)
4. **Delivery Date Logic** - Validates dates against zone rules and capacity
5. **Dynamic Shipping Costs** - Applies zone-specific flat rates via shipping rate modification (single charge, no double billing)
6. **Order Data Storage** - Saves zone, date, type, AND fee to order meta; displays in admin, emails, and customer orders
7. **Admin UI** - Clean accordion layout for zone management
8. **Security** - Nonces, sanitization, and server-side validation

## Installation Instructions
1. Download/zip the `delivery-dates-manager` folder
2. Upload to WordPress via Plugins > Add New > Upload
3. Activate the plugin
4. Configure at WooCommerce > Delivery Dates
5. Create shipping zones in WooCommerce > Settings > Shipping

## Technical Notes
- Requires WooCommerce 5.0+
- Requires PHP 7.4+
- Uses WordPress Settings API
- AJAX-powered date availability
- jQuery UI Datepicker for date selection
- Country/State enforced server-side (hidden fields + validation)

## Order Meta Keys
- `_ddm_delivery_zone` - Zone ID
- `_ddm_delivery_zone_name` - Zone name
- `_ddm_delivery_date` - Selected date (Y-m-d)
- `_ddm_delivery_type` - 'same_day' or 'standard'
- `_ddm_delivery_fee` - Delivery fee amount

## Development Notes
This is a WordPress plugin - no web server runs here. To test:
1. Install on a WordPress site with WooCommerce
2. Create shipping zones for Cairo areas
3. Configure delivery settings per zone
4. Test checkout flow with date selection

## Recent Changes
- Fixed double-charging issue (removed cart fee, kept shipping rate modification only)
- Added delivery fee to order meta storage and all displays (admin, email, frontend)
- Enforced Cairo-only checkout with hidden fields and server-side validation
- Restricted countries to Egypt only via woocommerce_countries filter
