# Delivery Dates Manager - Developer Documentation

**Version:** 1.0.7  
**Author:** Malek Fouda  
**Last Updated:** January 2026

This documentation provides comprehensive technical details for developers who will maintain, extend, or modify the Delivery Dates Manager plugin.

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [File Structure](#file-structure)
3. [Class Reference](#class-reference)
4. [Database & Options](#database--options)
5. [AJAX Endpoints](#ajax-endpoints)
6. [Hooks & Filters](#hooks--filters)
7. [JavaScript API](#javascript-api)
8. [CSS Classes](#css-classes)
9. [Extending the Plugin](#extending-the-plugin)
10. [Testing Guidelines](#testing-guidelines)
11. [Troubleshooting](#troubleshooting)

---

## Architecture Overview

The plugin follows a modular object-oriented architecture with clear separation of concerns:

```
┌─────────────────────────────────────────────────────────────┐
│                    Main Plugin File                          │
│              (delivery-dates-manager.php)                    │
│   - Singleton pattern                                        │
│   - Dependency checking                                      │
│   - Asset enqueuing                                          │
│   - HPOS compatibility declaration                           │
└─────────────────────┬───────────────────────────────────────┘
                      │
    ┌─────────────────┼─────────────────┐
    │                 │                 │
    ▼                 ▼                 ▼
┌─────────┐    ┌───────────┐    ┌───────────┐
│ Admin   │    │ Checkout  │    │ Shipping  │
│ Class   │    │ Class     │    │ Class     │
└────┬────┘    └─────┬─────┘    └─────┬─────┘
     │               │                │
     │               │                │
     ▼               ▼                ▼
┌─────────┐    ┌───────────┐    ┌───────────┐
│ Product │    │  Order    │    │ WooCommerce│
│ Class   │    │  Class    │    │ Integration│
└─────────┘    └───────────┘    └───────────┘
```

### Design Principles

1. **Singleton Pattern** - Main plugin class uses singleton to prevent multiple instantiation
2. **Hooks-Based Integration** - All WooCommerce modifications use WordPress/WooCommerce hooks
3. **AJAX for Dynamic Content** - Date availability and shipping updates use AJAX
4. **Server-Side Validation** - All user inputs validated on server (never trust client)
5. **Nonce Security** - All AJAX requests protected with WordPress nonces

---

## File Structure

```
delivery-dates-manager/
├── delivery-dates-manager.php    # Main plugin file (entry point)
├── readme.txt                    # WordPress.org plugin readme
├── DEVELOPER.md                  # This documentation
│
├── includes/
│   ├── class-ddm-admin.php       # Admin settings page & zone configuration
│   ├── class-ddm-checkout.php    # Checkout fields, validation, date logic
│   ├── class-ddm-order.php       # Order meta display (admin, email, frontend)
│   ├── class-ddm-product.php     # Product-level same-day eligibility
│   └── class-ddm-shipping.php    # Shipping rate modification & fees
│
└── assets/
    ├── css/
    │   ├── ddm-admin.css         # Admin panel styles
    │   └── ddm-frontend.css      # Checkout page styles
    └── js/
        ├── ddm-admin.js          # Admin accordion functionality
        └── ddm-checkout.js       # Datepicker, zone selection, AJAX
```

---

## Class Reference

### Delivery_Dates_Manager (Main Class)

**File:** `delivery-dates-manager.php`

The main plugin class that initializes all components.

#### Properties

| Property | Type | Description |
|----------|------|-------------|
| `$instance` | static self | Singleton instance |

#### Methods

| Method | Visibility | Description |
|--------|------------|-------------|
| `instance()` | public static | Returns singleton instance |
| `__construct()` | private | Initializes plugin components |
| `check_dependencies()` | private | Verifies WooCommerce is active |
| `includes()` | private | Loads all class files |
| `init_hooks()` | private | Registers WordPress hooks |
| `declare_hpos_compatibility()` | public | Declares HPOS support |
| `load_textdomain()` | public | Loads translation files |
| `enqueue_frontend_assets()` | public | Loads checkout CSS/JS |
| `enqueue_admin_assets()` | public | Loads admin CSS/JS |
| `get_all_zone_settings()` | public | Returns formatted zone settings |
| `activate()` | public | Plugin activation hook |
| `deactivate()` | public | Plugin deactivation hook |

#### Constants

| Constant | Description |
|----------|-------------|
| `DDM_VERSION` | Current plugin version |
| `DDM_PLUGIN_DIR` | Absolute path to plugin directory |
| `DDM_PLUGIN_URL` | URL to plugin directory |
| `DDM_PLUGIN_BASENAME` | Plugin basename for hooks |

---

### DDM_Admin

**File:** `includes/class-ddm-admin.php`

Handles the admin settings page under WooCommerce menu.

#### Registered WordPress Hooks

| Hook | Type | Callback |
|------|------|----------|
| `admin_menu` | action | `add_admin_menu()` |
| `admin_init` | action | `register_settings()` |
| `wp_ajax_ddm_save_zone_settings` | action | `ajax_save_zone_settings()` |

#### Methods

| Method | Description |
|--------|-------------|
| `add_admin_menu()` | Adds "Delivery Dates" submenu under WooCommerce |
| `register_settings()` | Registers all plugin options with Settings API |
| `sanitize_blocked_dates($input)` | Validates date format (YYYY-MM-DD) |
| `sanitize_zone_settings($input)` | Validates and sanitizes zone settings array |
| `ajax_save_zone_settings()` | AJAX handler for saving individual zone settings |
| `render_settings_page()` | Outputs the settings page HTML |
| `get_cairo_shipping_zones()` | Returns all WooCommerce shipping zones |
| `get_blocked_dates_for_zone($zone_id)` | Static - Returns merged global + zone blocked dates |

#### Registered Options

| Option Name | Type | Default |
|-------------|------|---------|
| `ddm_zone_settings` | array | `[]` |
| `ddm_global_blocked_dates` | string | `''` |
| `ddm_pickup_message` | string | (long default message) |
| `ddm_pickup_cutoff_time` | string | `'14:00'` |

---

### DDM_Checkout

**File:** `includes/class-ddm-checkout.php`

Handles checkout page customization, field injection, and date validation.

#### Registered WordPress Hooks

| Hook | Type | Priority | Callback |
|------|------|----------|----------|
| `woocommerce_states` | filter | 10 | `filter_egypt_states()` |
| `woocommerce_countries` | filter | 10 | `filter_countries()` |
| `woocommerce_default_address_fields` | filter | 10 | `customize_address_fields()` |
| `woocommerce_checkout_fields` | filter | 10 | `lock_country_state_fields()` |
| `woocommerce_checkout_fields` | filter | 20 | `add_delivery_fields()` |
| `woocommerce_checkout_process` | action | 10 | `validate_delivery_fields()` |
| `woocommerce_checkout_process` | action | 10 | `validate_location_fields()` |
| `woocommerce_checkout_update_order_meta` | action | 10 | `save_delivery_fields()` |
| `woocommerce_billing_fields` | filter | 10 | `force_egypt_country()` |
| `wp_ajax_ddm_get_zone_dates` | action | - | `ajax_get_zone_dates()` |
| `wp_ajax_nopriv_ddm_get_zone_dates` | action | - | `ajax_get_zone_dates()` |
| `wp_ajax_ddm_check_date_availability` | action | - | `ajax_check_date_availability()` |
| `wp_ajax_nopriv_ddm_check_date_availability` | action | - | `ajax_check_date_availability()` |
| `wp_ajax_ddm_get_pickup_dates` | action | - | `ajax_get_pickup_dates()` |
| `wp_ajax_nopriv_ddm_get_pickup_dates` | action | - | `ajax_get_pickup_dates()` |

#### Key Methods

| Method | Description |
|--------|-------------|
| `filter_egypt_states($states)` | Restricts Egypt states to Cairo only |
| `filter_countries($countries)` | Restricts countries to Egypt only |
| `add_delivery_fields($fields)` | Injects fulfillment method, zone, and date fields |
| `validate_delivery_fields()` | Server-side validation of selected date |
| `validate_location_fields()` | Forces Egypt/Cairo on form submission |
| `save_delivery_fields($order_id)` | Saves all delivery meta to order |
| `ajax_get_zone_dates()` | Returns available dates for a zone |
| `ajax_get_pickup_dates()` | Returns available pickup dates |
| `ajax_check_date_availability()` | Checks specific date availability |
| `calculate_available_dates($zone_id, $zone_settings)` | Generates available delivery dates |
| `calculate_pickup_dates()` | Generates available pickup dates |
| `is_before_cutoff($cutoff_time)` | Checks if current time is before cutoff |
| `is_date_valid($date, $zone_id)` | Validates a delivery date |
| `is_pickup_date_valid($date)` | Validates a pickup date |
| `get_orders_count_for_date($zone_id, $date)` | Returns order count for capacity check |
| `get_enabled_zones()` | Returns array of enabled delivery zones |
| `get_global_blocked_dates()` | Returns array of globally blocked dates |

#### Checkout Fields Added

| Field ID | Type | Description |
|----------|------|-------------|
| `ddm_fulfillment_method` | radio | Delivery or Pickup selection |
| `ddm_delivery_zone` | select | Zone dropdown (hidden for pickup) |
| `ddm_delivery_date` | text | Datepicker input (readonly) |
| `ddm_delivery_type` | hidden | Stores 'same_day', 'pickup', etc. |

---

### DDM_Shipping

**File:** `includes/class-ddm-shipping.php`

Handles shipping rate modification and delivery fee calculation.

#### Registered WordPress Hooks

| Hook | Type | Priority | Callback |
|------|------|----------|----------|
| `woocommerce_package_rates` | filter | 100 | `modify_shipping_rates()` |
| `woocommerce_cart_calculate_fees` | action | 10 | `add_delivery_fee()` |
| `wp_ajax_ddm_update_shipping` | action | - | `ajax_update_shipping()` |
| `wp_ajax_nopriv_ddm_update_shipping` | action | - | `ajax_update_shipping()` |
| `wp_ajax_ddm_set_fulfillment_method` | action | - | `ajax_set_fulfillment_method()` |
| `wp_ajax_nopriv_ddm_set_fulfillment_method` | action | - | `ajax_set_fulfillment_method()` |

#### Methods

| Method | Description |
|--------|-------------|
| `modify_shipping_rates($rates, $package)` | Overrides WC shipping labels/costs |
| `add_delivery_fee($cart)` | Adds delivery fee as cart fee |
| `ajax_set_fulfillment_method()` | Sets delivery/pickup in session |
| `ajax_update_shipping()` | Updates selected zone in session |
| `get_zone_name($zone_id)` | Returns zone name from WC |
| `get_wc_zone_flat_rate($zone_id)` | Static - Returns flat rate from WC zone |
| `get_delivery_fee_for_zone($zone_id)` | Static - Returns fee for enabled zone |

#### Session Variables

| Session Key | Description |
|-------------|-------------|
| `ddm_fulfillment_method` | 'delivery' or 'pickup' |
| `ddm_selected_zone` | Selected zone ID or null |

---

### DDM_Product

**File:** `includes/class-ddm-product.php`

Handles product-level same-day eligibility settings.

#### Registered WordPress Hooks

| Hook | Type | Callback |
|------|------|----------|
| `woocommerce_product_options_shipping` | action | `add_same_day_field()` |
| `woocommerce_process_product_meta` | action | `save_same_day_field()` |
| `woocommerce_variation_options` | action | `add_variation_same_day_field()` |
| `woocommerce_save_product_variation` | action | `save_variation_same_day_field()` |

#### Methods

| Method | Description |
|--------|-------------|
| `add_same_day_field()` | Adds checkboxes to product shipping tab |
| `save_same_day_field($post_id)` | Saves product meta |
| `add_variation_same_day_field($loop, $variation_data, $variation)` | Adds checkboxes to variations |
| `save_variation_same_day_field($variation_id, $loop)` | Saves variation meta |
| `is_product_same_day_eligible($product_id)` | Static - Checks delivery eligibility |
| `is_cart_same_day_eligible()` | Static - Checks all cart items for delivery |
| `is_product_same_day_pickup_eligible($product_id)` | Static - Checks pickup eligibility |
| `is_cart_same_day_pickup_eligible()` | Static - Checks all cart items for pickup |

#### Product Meta Keys

| Meta Key | Values | Default |
|----------|--------|---------|
| `_ddm_same_day_eligible` | 'yes' / 'no' / '' | '' (eligible) |
| `_ddm_same_day_pickup_eligible` | 'yes' / 'no' / '' | '' (eligible) |

**Note:** Empty string (`''`) means the product is eligible (opt-out model).

---

### DDM_Order

**File:** `includes/class-ddm-order.php`

Handles order meta display in admin, customer views, and emails.

#### Registered WordPress Hooks

| Hook | Type | Callback |
|------|------|----------|
| `woocommerce_admin_order_data_after_billing_address` | action | `display_admin_order_meta()` |
| `woocommerce_order_details_after_order_table` | action | `display_frontend_order_meta()` |
| `woocommerce_email_after_order_table` | action | `display_email_order_meta()` |
| `woocommerce_admin_order_preview_get_order_details` | filter | `add_order_preview_meta()` |
| `manage_shop_order_posts_custom_column` | action | `add_delivery_column_content()` |
| `manage_edit-shop_order_columns` | filter | `add_delivery_column()` |
| `manage_edit-shop_order_sortable_columns` | filter | `make_delivery_column_sortable()` |
| `pre_get_posts` | action | `sort_by_delivery_date()` |
| `manage_woocommerce_page_wc-orders_custom_column` | action | `add_delivery_column_content_hpos()` |
| `manage_woocommerce_page_wc-orders_columns` | filter | `add_delivery_column()` |
| `manage_woocommerce_page_wc-orders_sortable_columns` | filter | `make_delivery_column_sortable()` |

#### Methods

| Method | Description |
|--------|-------------|
| `display_admin_order_meta($order)` | Shows delivery info in admin order edit |
| `display_frontend_order_meta($order)` | Shows delivery info on order-received/my-account |
| `display_email_order_meta($order, $sent_to_admin, $plain_text, $email)` | Adds delivery info to emails |
| `add_order_preview_meta($data, $order)` | Adds info to admin order preview |
| `add_delivery_column($columns)` | Adds "Delivery Date" column to orders list |
| `add_delivery_column_content($column, $post_id)` | Populates column (legacy) |
| `add_delivery_column_content_hpos($column, $order)` | Populates column (HPOS) |
| `render_delivery_column($order)` | Renders column content |
| `make_delivery_column_sortable($columns)` | Makes column sortable |
| `sort_by_delivery_date($query)` | Handles sorting logic |
| `get_order_delivery_info($order_id)` | Static - Returns all delivery meta |

---

## Database & Options

### WordPress Options

| Option Name | Type | Description |
|-------------|------|-------------|
| `ddm_zone_settings` | array | Zone-specific settings keyed by zone ID |
| `ddm_global_blocked_dates` | string | Comma-separated YYYY-MM-DD dates |
| `ddm_pickup_message` | string | Pickup option label text |
| `ddm_pickup_cutoff_time` | string | HH:MM format time |

### Zone Settings Structure

```php
$ddm_zone_settings = [
    1 => [  // Zone ID
        'enabled' => true,
        'allowed_days' => [0, 1, 2, 3, 4, 5, 6],  // 0=Sunday
        'cutoff_time' => '14:00',
        'same_day' => true,
        'max_orders' => 50,  // 0 = unlimited
        'blocked_dates' => '2026-01-01,2026-01-07'
    ],
    2 => [
        // Another zone...
    ]
];
```

### Order Meta

| Meta Key | Type | Description |
|----------|------|-------------|
| `_ddm_fulfillment_method` | string | 'delivery' or 'pickup' |
| `_ddm_delivery_zone` | int | WooCommerce zone ID |
| `_ddm_delivery_zone_name` | string | Zone display name |
| `_ddm_delivery_date` | string | YYYY-MM-DD format |
| `_ddm_delivery_type` | string | 'same_day', 'same_day_pickup', 'pickup', 'standard' |
| `_ddm_delivery_fee` | float | Delivery fee amount |

### Product Meta

| Meta Key | Type | Description |
|----------|------|-------------|
| `_ddm_same_day_eligible` | string | 'yes', 'no', or '' (empty = eligible) |
| `_ddm_same_day_pickup_eligible` | string | 'yes', 'no', or '' (empty = eligible) |

---

## AJAX Endpoints

All AJAX endpoints use the nonce `ddm_checkout_nonce`.

### ddm_get_zone_dates

**Purpose:** Get available delivery dates for a zone.

**Request:**
```javascript
{
    action: 'ddm_get_zone_dates',
    zone_id: 1,
    nonce: '...'
}
```

**Response:**
```javascript
{
    success: true,
    data: {
        dates: [
            { date: '2026-01-14', label: 'Today', type: 'same_day' },
            { date: '2026-01-15', label: 'Wed, Jan 15', type: 'standard' }
        ],
        same_day_available: true,
        flat_fee: 50.00
    }
}
```

### ddm_get_pickup_dates

**Purpose:** Get available pickup dates.

**Request:**
```javascript
{
    action: 'ddm_get_pickup_dates',
    nonce: '...'
}
```

**Response:**
```javascript
{
    success: true,
    data: {
        dates: [
            { date: '2026-01-14', label: 'Today', type: 'same_day_pickup' },
            { date: '2026-01-15', label: 'Wed, Jan 15', type: 'pickup' }
        ],
        same_day_available: true
    }
}
```

### ddm_update_shipping

**Purpose:** Update selected zone in session and recalculate cart.

**Request:**
```javascript
{
    action: 'ddm_update_shipping',
    zone_id: 1,
    nonce: '...'
}
```

**Response:**
```javascript
{
    success: true,
    data: {
        flat_fee: 50.00,
        formatted_fee: '<span class="woocommerce-Price-amount">EGP 50.00</span>',
        cart_total: '<span class="woocommerce-Price-amount">EGP 150.00</span>'
    }
}
```

### ddm_set_fulfillment_method

**Purpose:** Set delivery or pickup mode in session.

**Request:**
```javascript
{
    action: 'ddm_set_fulfillment_method',
    method: 'pickup',  // or 'delivery'
    nonce: '...'
}
```

**Response:**
```javascript
{
    success: true,
    data: {
        method: 'pickup',
        cart_total: '<span class="woocommerce-Price-amount">EGP 100.00</span>'
    }
}
```

### ddm_check_date_availability

**Purpose:** Check specific date availability for a zone.

**Request:**
```javascript
{
    action: 'ddm_check_date_availability',
    zone_id: 1,
    date: '2026-01-15',
    nonce: '...'
}
```

**Response:**
```javascript
{
    success: true,
    data: {
        available: true,
        orders_count: 23,
        max_orders: 50,
        slots_remaining: 27  // -1 if unlimited
    }
}
```

---

## Hooks & Filters

### Actions (Do Something)

```php
// After delivery fields are saved to order
do_action('ddm_after_save_delivery_fields', $order_id, $fulfillment_method, $zone_id, $date);

// Example usage:
add_action('ddm_after_save_delivery_fields', function($order_id, $method, $zone_id, $date) {
    // Send notification, log, etc.
}, 10, 4);
```

### Filters (Modify Data)

Currently, the plugin doesn't define custom filters, but you can use existing WordPress/WooCommerce filters:

```php
// Modify available dates before returning
add_filter('ddm_available_dates', function($dates, $zone_id) {
    // Add/remove dates
    return $dates;
}, 10, 2);

// Modify pickup message
add_filter('ddm_pickup_message', function($message) {
    return $message . ' Additional info...';
});
```

**Note:** These filters would need to be implemented in the plugin if custom filtering is required.

---

## JavaScript API

### DDMCheckout Object

The main JavaScript controller for the checkout page.

#### Properties

| Property | Type | Description |
|----------|------|-------------|
| `settings` | object | Zone settings from PHP |
| `selectedZone` | int/null | Currently selected zone ID |
| `availableDates` | array | Available dates for datepicker |
| `fulfillmentMethod` | string | 'delivery' or 'pickup' |

#### Methods

| Method | Description |
|--------|-------------|
| `init()` | Initializes the controller |
| `bindEvents()` | Attaches event listeners |
| `handleInitialState()` | Sets initial fulfillment method |
| `onFulfillmentChange(e)` | Handles delivery/pickup toggle |
| `onZoneChange(e)` | Handles zone selection |
| `loadZoneDates(zoneId)` | AJAX loads dates for zone |
| `loadPickupDates()` | AJAX loads pickup dates |
| `initDatepicker()` | Initializes jQuery UI datepicker |
| `isDateAvailable(date)` | Returns datepicker day state |
| `onDateSelect(dateText)` | Handles date selection |
| `updateShipping(zoneId)` | AJAX updates shipping in session |
| `updateFulfillmentMethod(method)` | AJAX sets fulfillment method |
| `toggleZoneField()` | Shows/hides zone dropdown |
| `showSameDayBadge()` | Shows "Same-Day Available" badge |
| `showSameDayPickupBadge()` | Shows "Same-Day Pickup Available" badge |
| `hideSameDayBadge()` | Removes badge |
| `onCheckoutUpdate()` | Handles WC checkout update event |

#### Localized Data (ddm_checkout)

```javascript
var ddm_checkout = {
    ajax_url: '/wp-admin/admin-ajax.php',
    nonce: 'abc123...',
    zone_settings: {
        1: {
            enabled: true,
            allowed_days: [0, 1, 2, 3, 4, 5, 6],
            cutoff_time: '14:00',
            same_day: true,
            max_orders: 50,
            flat_fee: 50.00
        }
    },
    i18n: {
        select_zone: 'Select a delivery zone',
        select_date: 'Select delivery date',
        no_sameday: 'Same-day delivery not available for this order'
    }
};
```

---

## CSS Classes

### Checkout Page

| Class | Element | Description |
|-------|---------|-------------|
| `.ddm-field` | wrapper | All DDM field wrappers |
| `.ddm-fulfillment-field` | wrapper | Fulfillment method radios |
| `.ddm-zone-field` | wrapper | Zone select dropdown |
| `.ddm-loading` | wrapper | Loading state (spinner) |
| `.ddm-same-day-badge` | span | Same-day availability badge |
| `.ddm-same-day-pickup-badge` | span | Same-day pickup badge |

### Datepicker

| Class | Element | Description |
|-------|---------|-------------|
| `.ddm-date-available` | td | Standard available date |
| `.ddm-date-sameday` | td | Same-day delivery date (green) |
| `.ddm-date-sameday-pickup` | td | Same-day pickup date (orange) |
| `.ddm-date-pickup` | td | Standard pickup date (purple) |
| `.ddm-date-unavailable` | td | Blocked/unavailable date |

### Radio Button Cards

| Class | Element | Description |
|-------|---------|-------------|
| `.ddm-fulfillment-field input[type="radio"]` | input | Hidden native radio |
| `.ddm-fulfillment-field label` | label | Styled card button |

### Admin Page

| Class | Element | Description |
|-------|---------|-------------|
| `.ddm-admin-wrap` | div | Admin page wrapper |
| `.ddm-zones-accordion` | div | Zone panels container |
| `.ddm-zone-panel` | div | Individual zone panel |
| `.ddm-zone-header` | div | Clickable zone header |
| `.ddm-zone-content` | div | Collapsible content |
| `.ddm-zone-enabled` | modifier | Active zone styling |
| `.ddm-zone-toggle` | span | Arrow icon |
| `.ddm-zone-status` | span | Active/Inactive badge |

---

## Extending the Plugin

### Adding a New Setting

1. **Register the setting** in `DDM_Admin::register_settings()`:

```php
register_setting('ddm_settings', 'ddm_new_setting', array(
    'type' => 'string',
    'sanitize_callback' => 'sanitize_text_field',
    'default' => 'default_value'
));
```

2. **Add the field** in `DDM_Admin::render_settings_page()`:

```php
<tr>
    <th scope="row">
        <label for="ddm_new_setting">New Setting</label>
    </th>
    <td>
        <input type="text" 
               name="ddm_new_setting" 
               id="ddm_new_setting" 
               value="<?php echo esc_attr(get_option('ddm_new_setting', '')); ?>">
    </td>
</tr>
```

3. **Use the setting**:

```php
$value = get_option('ddm_new_setting', 'default_value');
```

### Adding a New AJAX Endpoint

1. **Register the action** in class constructor:

```php
add_action('wp_ajax_ddm_new_action', array($this, 'ajax_new_action'));
add_action('wp_ajax_nopriv_ddm_new_action', array($this, 'ajax_new_action'));
```

2. **Create the handler**:

```php
public function ajax_new_action() {
    check_ajax_referer('ddm_checkout_nonce', 'nonce');
    
    $param = isset($_POST['param']) ? sanitize_text_field($_POST['param']) : '';
    
    // Your logic here
    
    wp_send_json_success(array(
        'result' => $result
    ));
}
```

3. **Call from JavaScript**:

```javascript
$.ajax({
    url: ddm_checkout.ajax_url,
    type: 'POST',
    data: {
        action: 'ddm_new_action',
        param: 'value',
        nonce: ddm_checkout.nonce
    },
    success: function(response) {
        if (response.success) {
            console.log(response.data.result);
        }
    }
});
```

### Adding Order Meta Display

Add to `DDM_Order::display_admin_order_meta()`:

```php
$new_meta = $order->get_meta('_ddm_new_meta');
if ($new_meta) {
    echo '<p><strong>' . esc_html__('New Field:', 'delivery-dates-manager') . '</strong> ' 
         . esc_html($new_meta) . '</p>';
}
```

---

## Testing Guidelines

### Manual Testing Checklist

#### Admin Settings
- [ ] Global blocked dates save correctly
- [ ] Pickup message saves correctly
- [ ] Pickup cutoff time saves correctly
- [ ] Zone enable/disable works
- [ ] Allowed days checkboxes save
- [ ] Cutoff time saves per zone
- [ ] Same-day toggle works
- [ ] Max orders saves (0 for unlimited)
- [ ] Zone blocked dates save

#### Product Settings
- [ ] Same-day delivery checkbox appears in Shipping tab
- [ ] Same-day pickup checkbox appears in Shipping tab
- [ ] Settings save correctly
- [ ] Variation checkboxes work

#### Checkout - Delivery Flow
- [ ] Delivery radio button works
- [ ] Zone dropdown appears
- [ ] Zone selection loads dates via AJAX
- [ ] Datepicker shows correct available dates
- [ ] Same-day appears when eligible (before cutoff)
- [ ] Same-day hidden when ineligible product in cart
- [ ] Same-day hidden after cutoff time
- [ ] Blocked dates are not selectable
- [ ] Max orders reached dates are not selectable
- [ ] Delivery fee appears correctly
- [ ] Shipping label shows "Delivery to [Zone]"

#### Checkout - Pickup Flow
- [ ] Pickup radio button works
- [ ] Zone dropdown hides
- [ ] Pickup dates load via AJAX
- [ ] Same-day pickup appears when eligible
- [ ] Same-day pickup hidden when ineligible product
- [ ] Same-day pickup hidden after cutoff
- [ ] Shipping shows "Pickup" with $0
- [ ] No delivery fee added

#### Order Processing
- [ ] All meta saves to order
- [ ] Admin order page shows delivery info
- [ ] Customer order page shows delivery info
- [ ] Order emails include delivery info
- [ ] Orders list shows delivery date column
- [ ] Column is sortable

### Debug Mode

Add to `wp-config.php` for debugging:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check `/wp-content/debug.log` for errors.

---

## Troubleshooting

### Common Issues

#### Datepicker Not Appearing
- Check if jQuery UI is loaded
- Check browser console for JS errors
- Verify the zone has enabled days

#### Same-Day Not Showing
- Check cutoff time hasn't passed
- Check all cart products are eligible
- Check zone has same_day enabled
- Check today isn't a blocked date
- Check today is an allowed day

#### Shipping Fee Not Updating
- Clear WooCommerce session/cache
- Check zone has flat rate method in WC settings
- Verify AJAX requests in Network tab

#### Order Meta Not Saving
- Check for PHP errors in debug log
- Verify form fields are present
- Check nonce validation

### Debug Logging

Add temporary logging:

```php
error_log('DDM Debug: ' . print_r($variable, true));
```

View in `/wp-content/debug.log`.

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.7 | Jan 2026 | Same-day pickup, pickup cutoff time, validation fixes |
| 1.0.6 | Jan 2026 | Pickup option, fulfillment method toggle |
| 1.0.5 | Jan 2026 | Dynamic shipping, blocked dates |
| 1.0.0 | Jan 2026 | Initial release |

---

## Contact

For questions or support regarding development:

**Malek Fouda**  
Website: [malekfouda.com](https://www.malekfouda.com)

---

*This documentation is part of the Delivery Dates Manager plugin.*  
*Copyright (c) 2024-2026 Malek Fouda. All rights reserved.*
