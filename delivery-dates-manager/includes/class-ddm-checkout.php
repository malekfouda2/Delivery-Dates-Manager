<?php
if (!defined('ABSPATH')) {
    exit;
}

class DDM_Checkout {
    
    public function __construct() {
        add_filter('woocommerce_states', array($this, 'filter_egypt_states'));
        add_filter('woocommerce_countries', array($this, 'filter_countries'));
        add_filter('woocommerce_default_address_fields', array($this, 'customize_address_fields'));
        add_filter('woocommerce_checkout_fields', array($this, 'lock_country_state_fields'));
        add_filter('woocommerce_checkout_fields', array($this, 'add_delivery_fields'), 20);
        add_action('woocommerce_checkout_process', array($this, 'validate_delivery_fields'));
        add_action('woocommerce_checkout_process', array($this, 'validate_location_fields'));
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_delivery_fields'));
        add_action('wp_ajax_ddm_get_zone_dates', array($this, 'ajax_get_zone_dates'));
        add_action('wp_ajax_nopriv_ddm_get_zone_dates', array($this, 'ajax_get_zone_dates'));
        add_action('wp_ajax_ddm_check_date_availability', array($this, 'ajax_check_date_availability'));
        add_action('wp_ajax_nopriv_ddm_check_date_availability', array($this, 'ajax_check_date_availability'));
        add_action('wp_ajax_ddm_get_pickup_dates', array($this, 'ajax_get_pickup_dates'));
        add_action('wp_ajax_nopriv_ddm_get_pickup_dates', array($this, 'ajax_get_pickup_dates'));
        add_filter('woocommerce_billing_fields', array($this, 'force_egypt_country'));
    }
    
    public function filter_egypt_states($states) {
        $states['EG'] = array(
            'C' => __('Cairo', 'delivery-dates-manager'),
        );
        return $states;
    }
    
    public function filter_countries($countries) {
        return array('EG' => __('Egypt', 'delivery-dates-manager'));
    }
    
    public function force_egypt_country($fields) {
        $fields['billing_country']['default'] = 'EG';
        $fields['billing_state']['default'] = 'C';
        return $fields;
    }
    
    public function customize_address_fields($fields) {
        if (isset($fields['country'])) {
            $fields['country']['default'] = 'EG';
        }
        if (isset($fields['state'])) {
            $fields['state']['default'] = 'C';
        }
        return $fields;
    }
    
    public function lock_country_state_fields($fields) {
        if (isset($fields['billing']['billing_country'])) {
            $fields['billing']['billing_country']['type'] = 'hidden';
            $fields['billing']['billing_country']['default'] = 'EG';
        }
        if (isset($fields['billing']['billing_state'])) {
            $fields['billing']['billing_state']['type'] = 'hidden';
            $fields['billing']['billing_state']['default'] = 'C';
        }
        if (isset($fields['shipping']['shipping_country'])) {
            $fields['shipping']['shipping_country']['type'] = 'hidden';
            $fields['shipping']['shipping_country']['default'] = 'EG';
        }
        if (isset($fields['shipping']['shipping_state'])) {
            $fields['shipping']['shipping_state']['type'] = 'hidden';
            $fields['shipping']['shipping_state']['default'] = 'C';
        }
        return $fields;
    }
    
    public function validate_location_fields() {
        $billing_country = isset($_POST['billing_country']) ? sanitize_text_field($_POST['billing_country']) : '';
        $billing_state = isset($_POST['billing_state']) ? sanitize_text_field($_POST['billing_state']) : '';
        
        if ($billing_country !== 'EG') {
            $_POST['billing_country'] = 'EG';
        }
        if ($billing_state !== 'C') {
            $_POST['billing_state'] = 'C';
        }
        
        if (isset($_POST['shipping_country']) && $_POST['shipping_country'] !== 'EG') {
            $_POST['shipping_country'] = 'EG';
        }
        if (isset($_POST['shipping_state']) && $_POST['shipping_state'] !== 'C') {
            $_POST['shipping_state'] = 'C';
        }
    }
    
    public function add_delivery_fields($fields) {
        $zones = $this->get_enabled_zones();
        
        if (empty($zones)) {
            return $fields;
        }
        
        $pickup_message = get_option('ddm_pickup_message', __('Pickup from Heliopolis (order will be ready in 24 Hours, please make sure to select pickup date from the date form below)', 'delivery-dates-manager'));
        
        $fields['billing']['ddm_fulfillment_method'] = array(
            'type' => 'radio',
            'label' => __('Fulfillment Method', 'delivery-dates-manager'),
            'required' => true,
            'class' => array('form-row-wide', 'ddm-field', 'ddm-fulfillment-field'),
            'options' => array(
                'delivery' => __('Delivery', 'delivery-dates-manager'),
                'pickup' => $pickup_message,
            ),
            'default' => 'delivery',
            'priority' => 115,
        );
        
        $zone_options = array('' => __('Select delivery zone', 'delivery-dates-manager'));
        foreach ($zones as $zone_id => $zone_data) {
            $zone_options[$zone_id] = $zone_data['name'];
        }
        
        $fields['billing']['ddm_delivery_zone'] = array(
            'type' => 'select',
            'label' => __('Delivery Zone', 'delivery-dates-manager'),
            'required' => true,
            'class' => array('form-row-wide', 'ddm-field', 'ddm-zone-field'),
            'options' => $zone_options,
            'priority' => 120,
        );
        
        $fields['billing']['ddm_delivery_date'] = array(
            'type' => 'text',
            'label' => __('Delivery/Pickup Date', 'delivery-dates-manager'),
            'required' => true,
            'class' => array('form-row-wide', 'ddm-field'),
            'placeholder' => __('Select date', 'delivery-dates-manager'),
            'custom_attributes' => array(
                'readonly' => 'readonly',
            ),
            'priority' => 130,
        );
        
        $fields['billing']['ddm_delivery_type'] = array(
            'type' => 'hidden',
            'label' => '',
            'required' => false,
            'default' => 'standard',
            'priority' => 140,
        );
        
        return $fields;
    }
    
    public function validate_delivery_fields() {
        $fulfillment_method = isset($_POST['ddm_fulfillment_method']) ? sanitize_text_field($_POST['ddm_fulfillment_method']) : 'delivery';
        
        if ($fulfillment_method === 'delivery') {
            if (empty($_POST['ddm_delivery_zone'])) {
                wc_add_notice(__('Please select a delivery zone.', 'delivery-dates-manager'), 'error');
            }
        }
        
        if (empty($_POST['ddm_delivery_date'])) {
            wc_add_notice(__('Please select a date.', 'delivery-dates-manager'), 'error');
        } else {
            $delivery_date = sanitize_text_field($_POST['ddm_delivery_date']);
            
            if ($fulfillment_method === 'delivery') {
                $zone_id = absint($_POST['ddm_delivery_zone']);
                if (!$this->is_date_valid($delivery_date, $zone_id)) {
                    wc_add_notice(__('The selected delivery date is not available. Please choose another date.', 'delivery-dates-manager'), 'error');
                }
            } else {
                if (!$this->is_pickup_date_valid($delivery_date)) {
                    wc_add_notice(__('The selected pickup date is not available. Please choose another date.', 'delivery-dates-manager'), 'error');
                }
            }
        }
    }
    
    private function is_pickup_date_valid($date) {
        $timezone = new DateTimeZone(wp_timezone_string());
        $pickup_date = DateTime::createFromFormat('Y-m-d', $date, $timezone);
        
        if (!$pickup_date) {
            return false;
        }
        
        $blocked_dates = $this->get_global_blocked_dates();
        if (in_array($date, $blocked_dates)) {
            return false;
        }
        
        $now = new DateTime('now', $timezone);
        $today = $now->format('Y-m-d');
        
        if ($date === $today) {
            $cutoff_time = get_option('ddm_pickup_cutoff_time', '14:00');
            $cart_same_day_pickup_eligible = DDM_Product::is_cart_same_day_pickup_eligible();
            
            if (!$this->is_before_cutoff($cutoff_time) || !$cart_same_day_pickup_eligible) {
                return false;
            }
            return true;
        }
        
        if ($date < $today) {
            return false;
        }
        
        return true;
    }
    
    public function ajax_get_pickup_dates() {
        check_ajax_referer('ddm_checkout_nonce', 'nonce');
        
        $pickup_dates = $this->calculate_pickup_dates();
        $cart_same_day_pickup_eligible = DDM_Product::is_cart_same_day_pickup_eligible();
        $cutoff_time = get_option('ddm_pickup_cutoff_time', '14:00');
        $can_same_day = $cart_same_day_pickup_eligible && $this->is_before_cutoff($cutoff_time);
        
        wp_send_json_success(array(
            'dates' => $pickup_dates,
            'same_day_available' => $can_same_day,
        ));
    }
    
    private function calculate_pickup_dates() {
        $dates = array();
        $blocked_dates = $this->get_global_blocked_dates();
        
        $timezone = new DateTimeZone(wp_timezone_string());
        $now = new DateTime('now', $timezone);
        
        $cutoff_time = get_option('ddm_pickup_cutoff_time', '14:00');
        $is_before_cutoff = $this->is_before_cutoff($cutoff_time);
        $cart_same_day_pickup_eligible = DDM_Product::is_cart_same_day_pickup_eligible();
        
        if ($is_before_cutoff && $cart_same_day_pickup_eligible) {
            $today = $now->format('Y-m-d');
            if (!in_array($today, $blocked_dates)) {
                $dates[] = array(
                    'date' => $today,
                    'label' => __('Today', 'delivery-dates-manager'),
                    'type' => 'same_day_pickup',
                );
            }
        }
        
        $check_date = clone $now;
        $days_checked = 0;
        $max_days = 30;
        
        while (count($dates) < 14 && $days_checked < $max_days) {
            $check_date->modify('+1 day');
            $date_string = $check_date->format('Y-m-d');
            $days_checked++;
            
            if (in_array($date_string, $blocked_dates)) {
                continue;
            }
            
            $dates[] = array(
                'date' => $date_string,
                'label' => $check_date->format('D, M j'),
                'type' => 'pickup',
            );
        }
        
        return $dates;
    }
    
    private function get_global_blocked_dates() {
        $global_blocked = get_option('ddm_global_blocked_dates', '');
        
        if (empty($global_blocked)) {
            return array();
        }
        
        return array_map('trim', explode(',', $global_blocked));
    }
    
    public function save_delivery_fields($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $fulfillment_method = isset($_POST['ddm_fulfillment_method']) ? sanitize_text_field($_POST['ddm_fulfillment_method']) : 'delivery';
        $order->update_meta_data('_ddm_fulfillment_method', $fulfillment_method);
        
        if ($fulfillment_method === 'pickup') {
            $order->update_meta_data('_ddm_delivery_zone_name', __('Pickup - Heliopolis', 'delivery-dates-manager'));
            $order->update_meta_data('_ddm_delivery_fee', 0);
        } else {
            $zone_id = 0;
            
            if (!empty($_POST['ddm_delivery_zone'])) {
                $zone_id = absint($_POST['ddm_delivery_zone']);
                $order->update_meta_data('_ddm_delivery_zone', $zone_id);
                
                $zones = $this->get_enabled_zones();
                if (isset($zones[$zone_id])) {
                    $order->update_meta_data('_ddm_delivery_zone_name', sanitize_text_field($zones[$zone_id]['name']));
                }
                
                $delivery_fee = DDM_Shipping::get_delivery_fee_for_zone($zone_id);
                $order->update_meta_data('_ddm_delivery_fee', $delivery_fee);
            }
        }
        
        if (!empty($_POST['ddm_delivery_date'])) {
            $order->update_meta_data('_ddm_delivery_date', sanitize_text_field($_POST['ddm_delivery_date']));
        }
        
        $delivery_type = !empty($_POST['ddm_delivery_type']) ? sanitize_text_field($_POST['ddm_delivery_type']) : 'standard';
        $order->update_meta_data('_ddm_delivery_type', $delivery_type);
        
        $order->save();
    }
    
    public function ajax_get_zone_dates() {
        check_ajax_referer('ddm_checkout_nonce', 'nonce');
        
        $zone_id = isset($_POST['zone_id']) ? absint($_POST['zone_id']) : 0;
        $settings = get_option('ddm_zone_settings', array());
        
        if (!isset($settings[$zone_id]) || empty($settings[$zone_id]['enabled'])) {
            wp_send_json_error(__('Invalid zone', 'delivery-dates-manager'));
        }
        
        $zone_settings = $settings[$zone_id];
        $available_dates = $this->calculate_available_dates($zone_id, $zone_settings);
        $cart_same_day_eligible = DDM_Product::is_cart_same_day_eligible();
        $can_same_day = !empty($zone_settings['same_day']) && $cart_same_day_eligible && $this->is_before_cutoff($zone_settings['cutoff_time']);
        
        $flat_fee = DDM_Shipping::get_wc_zone_flat_rate($zone_id);
        
        wp_send_json_success(array(
            'dates' => $available_dates,
            'same_day_available' => $can_same_day,
            'flat_fee' => $flat_fee,
        ));
    }
    
    public function ajax_check_date_availability() {
        check_ajax_referer('ddm_checkout_nonce', 'nonce');
        
        $zone_id = isset($_POST['zone_id']) ? absint($_POST['zone_id']) : 0;
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        
        $is_valid = $this->is_date_valid($date, $zone_id);
        $orders_count = $this->get_orders_count_for_date($zone_id, $date);
        $settings = get_option('ddm_zone_settings', array());
        $max_orders = isset($settings[$zone_id]['max_orders']) ? intval($settings[$zone_id]['max_orders']) : 0;
        
        wp_send_json_success(array(
            'available' => $is_valid,
            'orders_count' => $orders_count,
            'max_orders' => $max_orders,
            'slots_remaining' => $max_orders > 0 ? max(0, $max_orders - $orders_count) : -1,
        ));
    }
    
    private function calculate_available_dates($zone_id, $zone_settings) {
        $dates = array();
        $allowed_days = isset($zone_settings['allowed_days']) ? $zone_settings['allowed_days'] : array();
        $max_orders = isset($zone_settings['max_orders']) ? intval($zone_settings['max_orders']) : 0;
        $cutoff_time = isset($zone_settings['cutoff_time']) ? $zone_settings['cutoff_time'] : '14:00';
        $same_day = !empty($zone_settings['same_day']);
        
        $blocked_dates = DDM_Admin::get_blocked_dates_for_zone($zone_id);
        
        $timezone = new DateTimeZone(wp_timezone_string());
        $now = new DateTime('now', $timezone);
        $current_day = (int) $now->format('w');
        
        $is_before_cutoff = $this->is_before_cutoff($cutoff_time);
        $cart_same_day_eligible = DDM_Product::is_cart_same_day_eligible();
        
        if ($same_day && $is_before_cutoff && $cart_same_day_eligible && in_array($current_day, $allowed_days)) {
            $today = $now->format('Y-m-d');
            if (!in_array($today, $blocked_dates)) {
                if ($max_orders == 0 || $this->get_orders_count_for_date($zone_id, $today) < $max_orders) {
                    $dates[] = array(
                        'date' => $today,
                        'label' => __('Today', 'delivery-dates-manager'),
                        'type' => 'same_day',
                    );
                }
            }
        }
        
        $check_date = clone $now;
        $days_checked = 0;
        $max_days = 60;
        
        while (count($dates) < 14 && $days_checked < $max_days) {
            $check_date->modify('+1 day');
            $day_of_week = (int) $check_date->format('w');
            $date_string = $check_date->format('Y-m-d');
            $days_checked++;
            
            if (!in_array($day_of_week, $allowed_days)) {
                continue;
            }
            
            if (in_array($date_string, $blocked_dates)) {
                continue;
            }
            
            if ($max_orders > 0) {
                $orders_count = $this->get_orders_count_for_date($zone_id, $date_string);
                if ($orders_count >= $max_orders) {
                    continue;
                }
            }
            
            $dates[] = array(
                'date' => $date_string,
                'label' => $check_date->format('D, M j'),
                'type' => 'standard',
            );
        }
        
        return $dates;
    }
    
    private function is_before_cutoff($cutoff_time) {
        $timezone = new DateTimeZone(wp_timezone_string());
        $now = new DateTime('now', $timezone);
        $cutoff = DateTime::createFromFormat('H:i', $cutoff_time, $timezone);
        
        if (!$cutoff) {
            return false;
        }
        
        $cutoff->setDate($now->format('Y'), $now->format('m'), $now->format('d'));
        
        return $now < $cutoff;
    }
    
    private function is_date_valid($date, $zone_id) {
        $settings = get_option('ddm_zone_settings', array());
        
        if (!isset($settings[$zone_id]) || empty($settings[$zone_id]['enabled'])) {
            return false;
        }
        
        $zone_settings = $settings[$zone_id];
        $allowed_days = isset($zone_settings['allowed_days']) ? $zone_settings['allowed_days'] : array();
        $max_orders = isset($zone_settings['max_orders']) ? intval($zone_settings['max_orders']) : 0;
        
        $blocked_dates = DDM_Admin::get_blocked_dates_for_zone($zone_id);
        if (in_array($date, $blocked_dates)) {
            return false;
        }
        
        $timezone = new DateTimeZone(wp_timezone_string());
        $delivery_date = DateTime::createFromFormat('Y-m-d', $date, $timezone);
        
        if (!$delivery_date) {
            return false;
        }
        
        $now = new DateTime('now', $timezone);
        $today = $now->format('Y-m-d');
        
        if ($date < $today) {
            return false;
        }
        
        if ($date === $today) {
            $cutoff_time = isset($zone_settings['cutoff_time']) ? $zone_settings['cutoff_time'] : '14:00';
            $same_day = !empty($zone_settings['same_day']);
            $cart_same_day_eligible = DDM_Product::is_cart_same_day_eligible();
            
            if (!$same_day || !$this->is_before_cutoff($cutoff_time) || !$cart_same_day_eligible) {
                return false;
            }
        }
        
        $day_of_week = (int) $delivery_date->format('w');
        if (!in_array($day_of_week, $allowed_days)) {
            return false;
        }
        
        if ($max_orders > 0) {
            $orders_count = $this->get_orders_count_for_date($zone_id, $date);
            if ($orders_count >= $max_orders) {
                return false;
            }
        }
        
        return true;
    }
    
    private function get_orders_count_for_date($zone_id, $date) {
        $orders = wc_get_orders(array(
            'limit' => -1,
            'status' => array('wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed'),
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_ddm_delivery_zone',
                    'value' => $zone_id,
                    'compare' => '=',
                ),
                array(
                    'key' => '_ddm_delivery_date',
                    'value' => $date,
                    'compare' => '=',
                ),
            ),
            'return' => 'ids',
        ));
        
        return count($orders);
    }
    
    private function get_enabled_zones() {
        $settings = get_option('ddm_zone_settings', array());
        $enabled_zones = array();
        
        if (!class_exists('WC_Shipping_Zones')) {
            return $enabled_zones;
        }
        
        $all_zones = WC_Shipping_Zones::get_zones();
        
        foreach ($all_zones as $zone_data) {
            $zone_id = $zone_data['zone_id'];
            if (isset($settings[$zone_id]) && !empty($settings[$zone_id]['enabled'])) {
                $enabled_zones[$zone_id] = array(
                    'name' => $zone_data['zone_name'],
                    'settings' => $settings[$zone_id],
                );
            }
        }
        
        $rest_zone = WC_Shipping_Zones::get_zone(0);
        if ($rest_zone && isset($settings[0]) && !empty($settings[0]['enabled'])) {
            $enabled_zones[0] = array(
                'name' => $rest_zone->get_zone_name(),
                'settings' => $settings[0],
            );
        }
        
        return $enabled_zones;
    }
}

new DDM_Checkout();
