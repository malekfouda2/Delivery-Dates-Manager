<?php
if (!defined('ABSPATH')) {
    exit;
}

class DDM_Shipping {
    
    public function __construct() {
        add_filter('woocommerce_package_rates', array($this, 'modify_shipping_rates'), 100, 2);
        add_action('wp_ajax_ddm_update_shipping', array($this, 'ajax_update_shipping'));
        add_action('wp_ajax_nopriv_ddm_update_shipping', array($this, 'ajax_update_shipping'));
        add_action('wp_ajax_ddm_set_fulfillment_method', array($this, 'ajax_set_fulfillment_method'));
        add_action('wp_ajax_nopriv_ddm_set_fulfillment_method', array($this, 'ajax_set_fulfillment_method'));
        add_action('woocommerce_cart_calculate_fees', array($this, 'add_delivery_fee'));
        add_filter('woocommerce_available_payment_gateways', array($this, 'ensure_cod_available'));
    }
    
    public function modify_shipping_rates($rates, $package) {
        $settings = get_option('ddm_zone_settings', array());
        $has_enabled_zones = false;
        
        foreach ($settings as $zone_data) {
            if (!empty($zone_data['enabled'])) {
                $has_enabled_zones = true;
                break;
            }
        }
        
        if (!$has_enabled_zones) {
            return $rates;
        }
        
        $fulfillment_method = WC()->session ? WC()->session->get('ddm_fulfillment_method', 'delivery') : 'delivery';
        
        if (empty($rates)) {
            if ($fulfillment_method === 'pickup') {
                $rate_id = 'flat_rate:ddm';
                $ddm_rate = new WC_Shipping_Rate(
                    $rate_id,
                    __('Pickup', 'delivery-dates-manager'),
                    0,
                    array(),
                    'flat_rate'
                );
                $new_rates = array($rate_id => $ddm_rate);
                $this->sync_chosen_method($new_rates);
                return $new_rates;
            }
            return $rates;
        }
        
        $first_rate_key = array_key_first($rates);
        $first_rate = $rates[$first_rate_key];
        
        if ($fulfillment_method === 'pickup') {
            $first_rate->cost = 0;
            $first_rate->label = __('Pickup', 'delivery-dates-manager');
            $first_rate->taxes = array();
            $new_rates = array($first_rate_key => $first_rate);
            $this->sync_chosen_method($new_rates);
            return $new_rates;
        }
        
        $zone_id = WC()->session ? WC()->session->get('ddm_selected_zone') : null;
        
        if ($zone_id && isset($settings[$zone_id]) && !empty($settings[$zone_id]['enabled'])) {
            $zone_name = $this->get_zone_name($zone_id);
            
            $first_rate->cost = 0;
            $first_rate->label = sprintf(
                __('Delivery to %s', 'delivery-dates-manager'),
                $zone_name
            );
            $first_rate->taxes = array();
        } else {
            $first_rate->cost = 0;
            $first_rate->label = __('Select delivery zone above', 'delivery-dates-manager');
            $first_rate->taxes = array();
        }
        
        $new_rates = array($first_rate_key => $first_rate);
        $this->sync_chosen_method($new_rates);
        return $new_rates;
    }
    
    private function sync_chosen_method($rates) {
        if (!WC()->session) {
            return;
        }
        
        $rate_keys = array_keys($rates);
        if (empty($rate_keys)) {
            return;
        }
        
        $chosen = WC()->session->get('chosen_shipping_methods', array());
        
        if (empty($chosen) || !isset($rates[$chosen[0]])) {
            WC()->session->set('chosen_shipping_methods', array($rate_keys[0]));
        }
    }
    
    public function add_delivery_fee($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        if (!WC()->session) {
            return;
        }
        
        $fulfillment_method = WC()->session->get('ddm_fulfillment_method', 'delivery');
        
        if ($fulfillment_method === 'pickup') {
            return;
        }
        
        $zone_id = WC()->session->get('ddm_selected_zone');
        
        if (!$zone_id) {
            return;
        }
        
        $settings = get_option('ddm_zone_settings', array());
        
        if (!isset($settings[$zone_id]) || empty($settings[$zone_id]['enabled'])) {
            return;
        }
        
        $flat_fee = self::get_wc_zone_flat_rate($zone_id);
        
        if ($flat_fee > 0) {
            $zone_name = $this->get_zone_name($zone_id);
            $cart->add_fee(
                sprintf(__('Delivery to %s', 'delivery-dates-manager'), $zone_name),
                $flat_fee,
                true
            );
        }
    }
    
    public function ajax_set_fulfillment_method() {
        check_ajax_referer('ddm_checkout_nonce', 'nonce');
        
        $method = isset($_POST['method']) ? sanitize_text_field($_POST['method']) : 'delivery';
        
        if (!in_array($method, array('delivery', 'pickup'))) {
            $method = 'delivery';
        }
        
        WC()->session->set('ddm_fulfillment_method', $method);
        
        if ($method === 'pickup') {
            WC()->session->set('ddm_selected_zone', null);
        }
        
        $this->invalidate_shipping_cache();
        WC()->cart->calculate_shipping();
        WC()->cart->calculate_totals();
        
        wp_send_json_success(array(
            'method' => $method,
            'cart_total' => WC()->cart->get_total(),
        ));
    }
    
    public function ajax_update_shipping() {
        check_ajax_referer('ddm_checkout_nonce', 'nonce');
        
        $zone_id = isset($_POST['zone_id']) ? absint($_POST['zone_id']) : 0;
        
        if ($zone_id) {
            WC()->session->set('ddm_selected_zone', $zone_id);
        } else {
            WC()->session->set('ddm_selected_zone', null);
        }
        
        $this->invalidate_shipping_cache();
        WC()->cart->calculate_shipping();
        WC()->cart->calculate_totals();
        
        $flat_fee = self::get_wc_zone_flat_rate($zone_id);
        
        wp_send_json_success(array(
            'flat_fee' => $flat_fee,
            'formatted_fee' => wc_price($flat_fee),
            'cart_total' => WC()->cart->get_total(),
        ));
    }
    
    private function invalidate_shipping_cache() {
        $packages = WC()->cart->get_shipping_packages();
        foreach ($packages as $package_key => $package) {
            $session_key = 'shipping_for_package_' . $package_key;
            WC()->session->set($session_key, false);
        }
        WC()->session->set('shipping_method_counts', array());
    }
    
    public function ensure_cod_available($gateways) {
        if (is_admin() && !(defined('DOING_AJAX') && DOING_AJAX)) {
            return $gateways;
        }
        
        if (!WC()->session) {
            return $gateways;
        }
        
        $fulfillment_method = WC()->session->get('ddm_fulfillment_method', 'delivery');
        
        if ($fulfillment_method !== 'pickup') {
            return $gateways;
        }
        
        if (isset($gateways['cod'])) {
            return $gateways;
        }
        
        $cod_settings = get_option('woocommerce_cod_settings', array());
        if (!empty($cod_settings['enabled']) && $cod_settings['enabled'] === 'yes') {
            $cod = new WC_Gateway_COD();
            $cod->enabled = 'yes';
            $gateways['cod'] = $cod;
        }
        
        return $gateways;
    }
    
    private function get_zone_name($zone_id) {
        if (!class_exists('WC_Shipping_Zones')) {
            return '';
        }
        
        $zone = WC_Shipping_Zones::get_zone($zone_id);
        return $zone ? $zone->get_zone_name() : '';
    }
    
    public static function get_wc_zone_flat_rate($zone_id) {
        global $wpdb;
        
        $instance_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT instance_id FROM {$wpdb->prefix}woocommerce_shipping_zone_methods
             WHERE zone_id = %d AND method_id = 'flat_rate' AND is_enabled = 1
             LIMIT 1",
            intval($zone_id)
        ) );
        
        if (!$instance_id) {
            return 0;
        }
        
        $cost = get_option( 'woocommerce_flat_rate_' . $instance_id . '_settings' );
        
        if (is_array($cost) && isset($cost['cost']) && $cost['cost'] !== '') {
            return floatval($cost['cost']);
        }
        
        return 0;
    }
    
    public static function get_delivery_fee_for_zone($zone_id) {
        $settings = get_option('ddm_zone_settings', array());
        
        if (!isset($settings[$zone_id]) || empty($settings[$zone_id]['enabled'])) {
            return 0;
        }
        
        return self::get_wc_zone_flat_rate($zone_id);
    }
}

new DDM_Shipping();
