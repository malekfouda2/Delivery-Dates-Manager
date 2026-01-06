<?php
if (!defined('ABSPATH')) {
    exit;
}

class DDM_Shipping {
    
    public function __construct() {
        add_filter('woocommerce_package_rates', array($this, 'modify_shipping_rates'), 100, 2);
        add_action('wp_ajax_ddm_update_shipping', array($this, 'ajax_update_shipping'));
        add_action('wp_ajax_nopriv_ddm_update_shipping', array($this, 'ajax_update_shipping'));
    }
    
    public function modify_shipping_rates($rates, $package) {
        $zone_id = WC()->session->get('ddm_selected_zone');
        
        if (!$zone_id) {
            return $rates;
        }
        
        $settings = get_option('ddm_zone_settings', array());
        
        if (!isset($settings[$zone_id]) || empty($settings[$zone_id]['enabled'])) {
            return $rates;
        }
        
        $flat_fee = isset($settings[$zone_id]['flat_fee']) ? floatval($settings[$zone_id]['flat_fee']) : 0;
        
        foreach ($rates as $rate_key => $rate) {
            $rates[$rate_key]->cost = $flat_fee;
            $rates[$rate_key]->label = sprintf(
                __('Delivery to %s', 'delivery-dates-manager'),
                $this->get_zone_name($zone_id)
            );
        }
        
        return $rates;
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
        
        $settings = get_option('ddm_zone_settings', array());
        $flat_fee = isset($settings[$zone_id]['flat_fee']) ? floatval($settings[$zone_id]['flat_fee']) : 0;
        
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
    
    public static function get_delivery_fee_for_zone($zone_id) {
        $settings = get_option('ddm_zone_settings', array());
        
        if (!isset($settings[$zone_id]) || empty($settings[$zone_id]['enabled'])) {
            return 0;
        }
        
        return isset($settings[$zone_id]['flat_fee']) ? floatval($settings[$zone_id]['flat_fee']) : 0;
    }
}

new DDM_Shipping();
