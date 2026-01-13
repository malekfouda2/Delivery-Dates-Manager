<?php
/**
 * Plugin Name: Delivery Dates Manager
 * Plugin URI: https://www.malekfouda.com
 * Description: A powerful delivery scheduling plugin for WooCommerce with Cairo-only shipping zones, same-day delivery options, and flexible date management.
 * Version: 1.0.5
 * Author: Malek Fouda
 * Author URI: https://www.malekfouda.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: delivery-dates-manager
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('DDM_VERSION', '1.0.5');
define('DDM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DDM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DDM_PLUGIN_BASENAME', plugin_basename(__FILE__));

final class Delivery_Dates_Manager {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->check_dependencies();
        $this->includes();
        $this->init_hooks();
    }
    
    private function check_dependencies() {
        add_action('admin_init', function() {
            if (!class_exists('WooCommerce')) {
                add_action('admin_notices', function() {
                    echo '<div class="error"><p>';
                    esc_html_e('Delivery Dates Manager requires WooCommerce to be installed and active.', 'delivery-dates-manager');
                    echo '</p></div>';
                });
                deactivate_plugins(DDM_PLUGIN_BASENAME);
            }
        });
    }
    
    private function includes() {
        require_once DDM_PLUGIN_DIR . 'includes/class-ddm-admin.php';
        require_once DDM_PLUGIN_DIR . 'includes/class-ddm-product.php';
        require_once DDM_PLUGIN_DIR . 'includes/class-ddm-checkout.php';
        require_once DDM_PLUGIN_DIR . 'includes/class-ddm-shipping.php';
        require_once DDM_PLUGIN_DIR . 'includes/class-ddm-order.php';
    }
    
    private function init_hooks() {
        add_action('init', array($this, 'load_textdomain'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));
        
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function declare_hpos_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('delivery-dates-manager', false, dirname(DDM_PLUGIN_BASENAME) . '/languages');
    }
    
    public function enqueue_frontend_assets() {
        if (is_checkout()) {
            wp_enqueue_style(
                'ddm-frontend',
                DDM_PLUGIN_URL . 'assets/css/ddm-frontend.css',
                array(),
                DDM_VERSION
            );
            
            wp_enqueue_style(
                'jquery-ui-datepicker-style',
                'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css',
                array(),
                '1.13.2'
            );
            
            wp_enqueue_script('jquery-ui-datepicker');
            
            wp_enqueue_script(
                'ddm-checkout',
                DDM_PLUGIN_URL . 'assets/js/ddm-checkout.js',
                array('jquery', 'jquery-ui-datepicker'),
                DDM_VERSION,
                true
            );
            
            wp_localize_script('ddm-checkout', 'ddm_checkout', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ddm_checkout_nonce'),
                'zone_settings' => $this->get_all_zone_settings(),
                'i18n' => array(
                    'select_zone' => __('Select a delivery zone', 'delivery-dates-manager'),
                    'select_date' => __('Select delivery date', 'delivery-dates-manager'),
                    'no_sameday' => __('Same-day delivery not available for this order', 'delivery-dates-manager'),
                )
            ));
        }
    }
    
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'ddm-settings') !== false || $hook === 'post.php' || $hook === 'post-new.php') {
            wp_enqueue_style(
                'ddm-admin',
                DDM_PLUGIN_URL . 'assets/css/ddm-admin.css',
                array(),
                DDM_VERSION
            );
            
            wp_enqueue_script(
                'ddm-admin',
                DDM_PLUGIN_URL . 'assets/js/ddm-admin.js',
                array('jquery'),
                DDM_VERSION,
                true
            );
        }
    }
    
    public function get_all_zone_settings() {
        $settings = get_option('ddm_zone_settings', array());
        $formatted = array();
        
        foreach ($settings as $zone_id => $zone_data) {
            if (!empty($zone_data['enabled'])) {
                $formatted[$zone_id] = array(
                    'enabled' => true,
                    'allowed_days' => isset($zone_data['allowed_days']) ? $zone_data['allowed_days'] : array(),
                    'cutoff_time' => isset($zone_data['cutoff_time']) ? $zone_data['cutoff_time'] : '14:00',
                    'same_day' => !empty($zone_data['same_day']),
                    'max_orders' => isset($zone_data['max_orders']) ? intval($zone_data['max_orders']) : 0,
                    'flat_fee' => isset($zone_data['flat_fee']) ? floatval($zone_data['flat_fee']) : 0,
                );
            }
        }
        
        return $formatted;
    }
    
    public function activate() {
        if (!get_option('ddm_zone_settings')) {
            update_option('ddm_zone_settings', array());
        }
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
}

function DDM() {
    return Delivery_Dates_Manager::instance();
}

add_action('plugins_loaded', 'DDM', 10);
