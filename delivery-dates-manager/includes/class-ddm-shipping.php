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
    }
    
    public function modify_shipping_rates($rates, $package) {
        $fulfillment_method = WC()->session->get('ddm_fulfillment_method', 'delivery');
        
        if ($fulfillment_method === 'pickup') {
            foreach ($rates as $rate_key => $rate) {
                $rates[$rate_key]->cost = 0;
                $rates[$rate_key]->label = __('Pickup', 'delivery-dates-manager');
            }
            return $rates;
        }
        
        $zone_id = WC()->session->get('ddm_selected_zone');
        
        if (!$zone_id) {
            return $rates;
        }
        
        $settings = get_option('ddm_zone_settings', array());
        
        if (!isset($settings[$zone_id]) || empty($settings[$zone_id]['enabled'])) {
            return $rates;
        }
        
        $flat_fee = self::get_wc_zone_flat_rate($zone_id);
        $zone_name = $this->get_zone_name($zone_id);
        
        foreach ($rates as $rate_key => $rate) {
            $rates[$rate_key]->cost = 0;
            $rates[$rate_key]->label = sprintf(
                __('Delivery to %s', 'delivery-dates-manager'),
                $zone_name
            );
        }
        
        return $rates;
    }
    
    public function add_delivery_fee($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
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
        
        WC()->cart->calculate_totals();
        
        $flat_fee = self::get_wc_zone_flat_rate($zone_id);
        
        wp_send_json_success(array(
            'flat_fee' => $flat_fee,
            'formatted_fee' => wc_price($flat_fee),
            'cart_total' => WC()->cart->get_total(),
        ));
    }
    
    private function get_zone_name($zone_id) {
        if (!class_exists('WC_Shipping_Zones')) {
            return '';
        }
        
        $zone = WC_Shipping_Zones::get_zone($zone_id);
        return $zone ? $zone->get_zone_name() : '';
    }
    
    public static function get_wc_zone_flat_rate($zone_id) {
        if (!class_exists('WC_Shipping_Zones')) {
            return 0;
        }
        
        $zone = WC_Shipping_Zones::get_zone($zone_id);
        if (!$zone) {
            return 0;
        }
        
        $shipping_methods = $zone->get_shipping_methods(true);
        
        foreach ($shipping_methods as $method) {
            if ($method->id === 'flat_rate' && $method->is_enabled()) {
                $cost = $method->get_option('cost');
                if ($cost !== '' && $cost !== null) {
                    return floatval($cost);
                }
            }
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
