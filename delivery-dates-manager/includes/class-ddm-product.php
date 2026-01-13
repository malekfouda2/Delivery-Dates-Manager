<?php
if (!defined('ABSPATH')) {
    exit;
}

class DDM_Product {
    
    public function __construct() {
        add_action('woocommerce_product_options_shipping', array($this, 'add_same_day_field'));
        add_action('woocommerce_process_product_meta', array($this, 'save_same_day_field'));
        add_action('woocommerce_variation_options', array($this, 'add_variation_same_day_field'), 10, 3);
        add_action('woocommerce_save_product_variation', array($this, 'save_variation_same_day_field'), 10, 2);
    }
    
    public function add_same_day_field() {
        global $post;
        
        echo '<div class="options_group ddm-same-day-options">';
        
        woocommerce_wp_checkbox(array(
            'id' => '_ddm_same_day_eligible',
            'label' => __('Same-Day Delivery', 'delivery-dates-manager'),
            'description' => __('Check if this product is eligible for same-day delivery', 'delivery-dates-manager'),
            'desc_tip' => true,
        ));
        
        woocommerce_wp_checkbox(array(
            'id' => '_ddm_same_day_pickup_eligible',
            'label' => __('Same-Day Pickup', 'delivery-dates-manager'),
            'description' => __('Check if this product is eligible for same-day pickup', 'delivery-dates-manager'),
            'desc_tip' => true,
        ));
        
        echo '</div>';
    }
    
    public function save_same_day_field($post_id) {
        $same_day_eligible = isset($_POST['_ddm_same_day_eligible']) ? 'yes' : 'no';
        update_post_meta($post_id, '_ddm_same_day_eligible', $same_day_eligible);
        
        $same_day_pickup_eligible = isset($_POST['_ddm_same_day_pickup_eligible']) ? 'yes' : 'no';
        update_post_meta($post_id, '_ddm_same_day_pickup_eligible', $same_day_pickup_eligible);
    }
    
    public function add_variation_same_day_field($loop, $variation_data, $variation) {
        woocommerce_wp_checkbox(array(
            'id' => '_ddm_same_day_eligible_' . $variation->ID,
            'name' => '_ddm_same_day_eligible_variation[' . $loop . ']',
            'label' => __('Same-Day Delivery', 'delivery-dates-manager'),
            'description' => __('Eligible for same-day delivery', 'delivery-dates-manager'),
            'value' => get_post_meta($variation->ID, '_ddm_same_day_eligible', true),
            'wrapper_class' => 'form-row',
        ));
        
        woocommerce_wp_checkbox(array(
            'id' => '_ddm_same_day_pickup_eligible_' . $variation->ID,
            'name' => '_ddm_same_day_pickup_eligible_variation[' . $loop . ']',
            'label' => __('Same-Day Pickup', 'delivery-dates-manager'),
            'description' => __('Eligible for same-day pickup', 'delivery-dates-manager'),
            'value' => get_post_meta($variation->ID, '_ddm_same_day_pickup_eligible', true),
            'wrapper_class' => 'form-row',
        ));
    }
    
    public function save_variation_same_day_field($variation_id, $loop) {
        $same_day_eligible = isset($_POST['_ddm_same_day_eligible_variation'][$loop]) ? 'yes' : 'no';
        update_post_meta($variation_id, '_ddm_same_day_eligible', $same_day_eligible);
        
        $same_day_pickup_eligible = isset($_POST['_ddm_same_day_pickup_eligible_variation'][$loop]) ? 'yes' : 'no';
        update_post_meta($variation_id, '_ddm_same_day_pickup_eligible', $same_day_pickup_eligible);
    }
    
    public static function is_product_same_day_eligible($product_id) {
        $eligible = get_post_meta($product_id, '_ddm_same_day_eligible', true);
        if ($eligible === '') {
            return true;
        }
        return $eligible === 'yes';
    }
    
    public static function is_cart_same_day_eligible() {
        if (!WC()->cart) {
            return true;
        }
        
        $cart_contents = WC()->cart->get_cart();
        if (empty($cart_contents)) {
            return true;
        }
        
        foreach ($cart_contents as $cart_item) {
            $product_id = $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];
            
            if (!self::is_product_same_day_eligible($product_id)) {
                return false;
            }
        }
        
        return true;
    }
    
    public static function is_product_same_day_pickup_eligible($product_id) {
        $eligible = get_post_meta($product_id, '_ddm_same_day_pickup_eligible', true);
        if ($eligible === '') {
            return true;
        }
        return $eligible === 'yes';
    }
    
    public static function is_cart_same_day_pickup_eligible() {
        if (!WC()->cart) {
            return true;
        }
        
        $cart_contents = WC()->cart->get_cart();
        if (empty($cart_contents)) {
            return true;
        }
        
        foreach ($cart_contents as $cart_item) {
            $product_id = $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];
            
            if (!self::is_product_same_day_pickup_eligible($product_id)) {
                return false;
            }
        }
        
        return true;
    }
}

new DDM_Product();
