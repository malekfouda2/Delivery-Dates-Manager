<?php
if (!defined('ABSPATH')) {
    exit;
}

class DDM_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_ddm_save_zone_settings', array($this, 'ajax_save_zone_settings'));
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Delivery Dates Manager', 'delivery-dates-manager'),
            __('Delivery Dates', 'delivery-dates-manager'),
            'manage_woocommerce',
            'ddm-settings',
            array($this, 'render_settings_page')
        );
    }
    
    public function register_settings() {
        register_setting('ddm_settings', 'ddm_zone_settings', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_zone_settings'),
            'default' => array()
        ));
        
        register_setting('ddm_settings', 'ddm_global_blocked_dates', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_blocked_dates'),
            'default' => ''
        ));
    }
    
    public function sanitize_blocked_dates($input) {
        if (empty($input)) {
            return '';
        }
        
        $dates = array_map('trim', explode(',', $input));
        $valid_dates = array();
        
        foreach ($dates as $date) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $valid_dates[] = $date;
            }
        }
        
        return implode(',', $valid_dates);
    }
    
    public function sanitize_zone_settings($input) {
        $sanitized = array();
        
        if (!is_array($input)) {
            return $sanitized;
        }
        
        foreach ($input as $zone_id => $zone_data) {
            $zone_id = absint($zone_id);
            
            $blocked_dates = '';
            if (isset($zone_data['blocked_dates'])) {
                $blocked_dates = $this->sanitize_blocked_dates($zone_data['blocked_dates']);
            }
            
            $sanitized[$zone_id] = array(
                'enabled' => !empty($zone_data['enabled']),
                'allowed_days' => isset($zone_data['allowed_days']) ? array_map('absint', (array)$zone_data['allowed_days']) : array(),
                'cutoff_time' => isset($zone_data['cutoff_time']) ? sanitize_text_field($zone_data['cutoff_time']) : '14:00',
                'same_day' => !empty($zone_data['same_day']),
                'max_orders' => isset($zone_data['max_orders']) ? absint($zone_data['max_orders']) : 0,
                'blocked_dates' => $blocked_dates,
            );
        }
        
        return $sanitized;
    }
    
    public function ajax_save_zone_settings() {
        check_ajax_referer('ddm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied', 'delivery-dates-manager'));
        }
        
        $zone_id = isset($_POST['zone_id']) ? absint($_POST['zone_id']) : 0;
        $settings = get_option('ddm_zone_settings', array());
        
        $blocked_dates = '';
        if (isset($_POST['blocked_dates'])) {
            $blocked_dates = $this->sanitize_blocked_dates($_POST['blocked_dates']);
        }
        
        $settings[$zone_id] = array(
            'enabled' => !empty($_POST['enabled']),
            'allowed_days' => isset($_POST['allowed_days']) ? array_map('absint', (array)$_POST['allowed_days']) : array(),
            'cutoff_time' => isset($_POST['cutoff_time']) ? sanitize_text_field($_POST['cutoff_time']) : '14:00',
            'same_day' => !empty($_POST['same_day']),
            'max_orders' => isset($_POST['max_orders']) ? absint($_POST['max_orders']) : 0,
            'blocked_dates' => $blocked_dates,
        );
        
        update_option('ddm_zone_settings', $settings);
        wp_send_json_success(__('Settings saved successfully', 'delivery-dates-manager'));
    }
    
    public function render_settings_page() {
        $zones = $this->get_cairo_shipping_zones();
        $settings = get_option('ddm_zone_settings', array());
        $global_blocked_dates = get_option('ddm_global_blocked_dates', '');
        $days = array(
            0 => __('Sunday', 'delivery-dates-manager'),
            1 => __('Monday', 'delivery-dates-manager'),
            2 => __('Tuesday', 'delivery-dates-manager'),
            3 => __('Wednesday', 'delivery-dates-manager'),
            4 => __('Thursday', 'delivery-dates-manager'),
            5 => __('Friday', 'delivery-dates-manager'),
            6 => __('Saturday', 'delivery-dates-manager'),
        );
        ?>
        <div class="wrap ddm-admin-wrap">
            <h1><?php esc_html_e('Delivery Dates Manager', 'delivery-dates-manager'); ?></h1>
            <p class="description"><?php esc_html_e('Configure delivery settings for each Cairo shipping zone.', 'delivery-dates-manager'); ?></p>
            
            <form method="post" action="options.php" id="ddm-settings-form">
                <?php settings_fields('ddm_settings'); ?>
                <?php wp_nonce_field('ddm_admin_nonce', 'ddm_nonce'); ?>
                
                <div class="ddm-global-settings" style="background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h2 style="margin-top: 0;"><?php esc_html_e('Global Settings', 'delivery-dates-manager'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="ddm_global_blocked_dates"><?php esc_html_e('Blocked Dates (All Zones)', 'delivery-dates-manager'); ?></label>
                            </th>
                            <td>
                                <textarea name="ddm_global_blocked_dates" 
                                          id="ddm_global_blocked_dates" 
                                          rows="3" 
                                          cols="50" 
                                          class="large-text"
                                          placeholder="2025-12-25, 2025-12-31, 2026-01-01"><?php echo esc_textarea($global_blocked_dates); ?></textarea>
                                <p class="description">
                                    <?php esc_html_e('Enter dates that are blocked for ALL delivery zones (holidays, vacations, etc). Use format YYYY-MM-DD, separated by commas.', 'delivery-dates-manager'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <h2><?php esc_html_e('Zone Settings', 'delivery-dates-manager'); ?></h2>
                
                <div class="ddm-zones-accordion">
                    <?php if (empty($zones)) : ?>
                        <div class="ddm-notice ddm-notice-warning">
                            <p><?php esc_html_e('No shipping zones found. Please create shipping zones in WooCommerce Settings > Shipping.', 'delivery-dates-manager'); ?></p>
                        </div>
                    <?php else : ?>
                        <?php foreach ($zones as $zone) : 
                            $zone_settings = isset($settings[$zone->get_id()]) ? $settings[$zone->get_id()] : array();
                            $is_enabled = !empty($zone_settings['enabled']);
                            $allowed_days = isset($zone_settings['allowed_days']) ? $zone_settings['allowed_days'] : array();
                            $cutoff_time = isset($zone_settings['cutoff_time']) ? $zone_settings['cutoff_time'] : '14:00';
                            $same_day = !empty($zone_settings['same_day']);
                            $max_orders = isset($zone_settings['max_orders']) ? $zone_settings['max_orders'] : 0;
                            $zone_blocked_dates = isset($zone_settings['blocked_dates']) ? $zone_settings['blocked_dates'] : '';
                        ?>
                        <div class="ddm-zone-panel <?php echo $is_enabled ? 'ddm-zone-enabled' : ''; ?>">
                            <div class="ddm-zone-header">
                                <span class="ddm-zone-toggle dashicons dashicons-arrow-right"></span>
                                <span class="ddm-zone-name"><?php echo esc_html($zone->get_zone_name()); ?></span>
                                <span class="ddm-zone-status <?php echo $is_enabled ? 'active' : 'inactive'; ?>">
                                    <?php echo $is_enabled ? esc_html__('Active', 'delivery-dates-manager') : esc_html__('Inactive', 'delivery-dates-manager'); ?>
                                </span>
                            </div>
                            <div class="ddm-zone-content" style="display: none;">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php esc_html_e('Enable Zone', 'delivery-dates-manager'); ?></th>
                                        <td>
                                            <label>
                                                <input type="checkbox" 
                                                       name="ddm_zone_settings[<?php echo esc_attr($zone->get_id()); ?>][enabled]" 
                                                       value="1" 
                                                       <?php checked($is_enabled); ?>>
                                                <?php esc_html_e('Enable delivery date selection for this zone', 'delivery-dates-manager'); ?>
                                            </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e('Allowed Delivery Days', 'delivery-dates-manager'); ?></th>
                                        <td>
                                            <fieldset>
                                                <?php foreach ($days as $day_num => $day_name) : ?>
                                                    <label style="display: inline-block; margin-right: 15px;">
                                                        <input type="checkbox" 
                                                               name="ddm_zone_settings[<?php echo esc_attr($zone->get_id()); ?>][allowed_days][]" 
                                                               value="<?php echo esc_attr($day_num); ?>"
                                                               <?php checked(in_array($day_num, $allowed_days)); ?>>
                                                        <?php echo esc_html($day_name); ?>
                                                    </label>
                                                <?php endforeach; ?>
                                            </fieldset>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e('Cutoff Time', 'delivery-dates-manager'); ?></th>
                                        <td>
                                            <input type="time" 
                                                   name="ddm_zone_settings[<?php echo esc_attr($zone->get_id()); ?>][cutoff_time]" 
                                                   value="<?php echo esc_attr($cutoff_time); ?>">
                                            <p class="description"><?php esc_html_e('Orders placed after this time will be scheduled for the next available day.', 'delivery-dates-manager'); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e('Same-Day Delivery', 'delivery-dates-manager'); ?></th>
                                        <td>
                                            <label>
                                                <input type="checkbox" 
                                                       name="ddm_zone_settings[<?php echo esc_attr($zone->get_id()); ?>][same_day]" 
                                                       value="1"
                                                       <?php checked($same_day); ?>>
                                                <?php esc_html_e('Allow same-day delivery for this zone', 'delivery-dates-manager'); ?>
                                            </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e('Max Orders Per Day', 'delivery-dates-manager'); ?></th>
                                        <td>
                                            <input type="number" 
                                                   name="ddm_zone_settings[<?php echo esc_attr($zone->get_id()); ?>][max_orders]" 
                                                   value="<?php echo esc_attr($max_orders); ?>"
                                                   min="0"
                                                   step="1">
                                            <p class="description"><?php esc_html_e('Set to 0 for unlimited orders.', 'delivery-dates-manager'); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="zone_blocked_dates_<?php echo esc_attr($zone->get_id()); ?>">
                                                <?php esc_html_e('Zone Blocked Dates', 'delivery-dates-manager'); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <textarea name="ddm_zone_settings[<?php echo esc_attr($zone->get_id()); ?>][blocked_dates]" 
                                                      id="zone_blocked_dates_<?php echo esc_attr($zone->get_id()); ?>" 
                                                      rows="2" 
                                                      cols="50" 
                                                      class="large-text"
                                                      placeholder="2025-12-25, 2025-12-31"><?php echo esc_textarea($zone_blocked_dates); ?></textarea>
                                            <p class="description">
                                                <?php esc_html_e('Enter dates blocked for THIS zone only. Format: YYYY-MM-DD, separated by commas.', 'delivery-dates-manager'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                                <?php 
                                $wc_flat_rate = DDM_Shipping::get_wc_zone_flat_rate($zone->get_id());
                                if ($wc_flat_rate > 0) : ?>
                                <p class="description" style="margin-top: 10px; padding: 10px; background: #e7f3fe; border-left: 3px solid #2196F3;">
                                    <?php printf(
                                        esc_html__('Delivery fee: %s (from WooCommerce Shipping Settings)', 'delivery-dates-manager'),
                                        wc_price($wc_flat_rate)
                                    ); ?>
                                </p>
                                <?php else : ?>
                                <p class="description" style="margin-top: 10px; padding: 10px; background: #fff3cd; border-left: 3px solid #ffc107;">
                                    <?php esc_html_e('No flat rate shipping method found for this zone. Please add one in WooCommerce > Settings > Shipping.', 'delivery-dates-manager'); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($zones)) : ?>
                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'delivery-dates-manager'); ?></button>
                    </p>
                <?php endif; ?>
            </form>
        </div>
        <?php
    }
    
    private function get_cairo_shipping_zones() {
        if (!class_exists('WC_Shipping_Zones')) {
            return array();
        }
        
        $all_zones = WC_Shipping_Zones::get_zones();
        $cairo_zones = array();
        
        foreach ($all_zones as $zone_data) {
            $zone = new WC_Shipping_Zone($zone_data['zone_id']);
            $cairo_zones[] = $zone;
        }
        
        $rest_of_world = WC_Shipping_Zones::get_zone(0);
        if ($rest_of_world) {
            $cairo_zones[] = $rest_of_world;
        }
        
        return $cairo_zones;
    }
    
    public static function get_blocked_dates_for_zone($zone_id) {
        $global_blocked = get_option('ddm_global_blocked_dates', '');
        $settings = get_option('ddm_zone_settings', array());
        $zone_blocked = isset($settings[$zone_id]['blocked_dates']) ? $settings[$zone_id]['blocked_dates'] : '';
        
        $all_blocked = array();
        
        if (!empty($global_blocked)) {
            $all_blocked = array_merge($all_blocked, array_map('trim', explode(',', $global_blocked)));
        }
        
        if (!empty($zone_blocked)) {
            $all_blocked = array_merge($all_blocked, array_map('trim', explode(',', $zone_blocked)));
        }
        
        return array_unique($all_blocked);
    }
}

new DDM_Admin();
