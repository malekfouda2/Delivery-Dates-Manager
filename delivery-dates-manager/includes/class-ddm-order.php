<?php
if (!defined('ABSPATH')) {
    exit;
}

class DDM_Order {
    
    public function __construct() {
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_admin_order_meta'), 10, 1);
        add_action('woocommerce_order_details_after_order_table', array($this, 'display_frontend_order_meta'), 10, 1);
        add_action('woocommerce_email_after_order_table', array($this, 'display_email_order_meta'), 10, 4);
        add_filter('woocommerce_admin_order_preview_get_order_details', array($this, 'add_order_preview_meta'), 10, 2);
        add_action('manage_shop_order_posts_custom_column', array($this, 'add_delivery_column_content'), 20, 2);
        add_filter('manage_edit-shop_order_columns', array($this, 'add_delivery_column'));
        add_filter('manage_edit-shop_order_sortable_columns', array($this, 'make_delivery_column_sortable'));
        add_action('pre_get_posts', array($this, 'sort_by_delivery_date'));
    }
    
    public function display_admin_order_meta($order) {
        $zone_name = $order->get_meta('_ddm_delivery_zone_name');
        $delivery_date = $order->get_meta('_ddm_delivery_date');
        $delivery_type = $order->get_meta('_ddm_delivery_type');
        $delivery_fee = $order->get_meta('_ddm_delivery_fee');
        
        if ($zone_name || $delivery_date) {
            echo '<div class="ddm-order-meta" style="margin-top: 15px; padding: 10px; background: #f8f8f8; border-left: 4px solid #0073aa;">';
            echo '<h3 style="margin: 0 0 10px; font-size: 14px;">' . esc_html__('Delivery Information', 'delivery-dates-manager') . '</h3>';
            
            if ($zone_name) {
                echo '<p><strong>' . esc_html__('Delivery Zone:', 'delivery-dates-manager') . '</strong> ' . esc_html($zone_name) . '</p>';
            }
            
            if ($delivery_date) {
                $formatted_date = date_i18n(get_option('date_format'), strtotime($delivery_date));
                echo '<p><strong>' . esc_html__('Delivery Date:', 'delivery-dates-manager') . '</strong> ' . esc_html($formatted_date) . '</p>';
            }
            
            if ($delivery_type) {
                $type_label = $delivery_type === 'same_day' ? __('Same-Day Delivery', 'delivery-dates-manager') : __('Standard Delivery', 'delivery-dates-manager');
                echo '<p><strong>' . esc_html__('Delivery Type:', 'delivery-dates-manager') . '</strong> ' . esc_html($type_label) . '</p>';
            }
            
            if ($delivery_fee !== '') {
                echo '<p><strong>' . esc_html__('Delivery Fee:', 'delivery-dates-manager') . '</strong> ' . wc_price($delivery_fee) . '</p>';
            }
            
            echo '</div>';
        }
    }
    
    public function display_frontend_order_meta($order) {
        $zone_name = $order->get_meta('_ddm_delivery_zone_name');
        $delivery_date = $order->get_meta('_ddm_delivery_date');
        $delivery_type = $order->get_meta('_ddm_delivery_type');
        $delivery_fee = $order->get_meta('_ddm_delivery_fee');
        
        if ($zone_name || $delivery_date) {
            echo '<section class="woocommerce-delivery-details">';
            echo '<h2 class="woocommerce-column__title">' . esc_html__('Delivery Details', 'delivery-dates-manager') . '</h2>';
            echo '<table class="woocommerce-table shop_table delivery_details">';
            
            if ($zone_name) {
                echo '<tr>';
                echo '<th>' . esc_html__('Delivery Zone', 'delivery-dates-manager') . '</th>';
                echo '<td>' . esc_html($zone_name) . '</td>';
                echo '</tr>';
            }
            
            if ($delivery_date) {
                $formatted_date = date_i18n(get_option('date_format'), strtotime($delivery_date));
                echo '<tr>';
                echo '<th>' . esc_html__('Delivery Date', 'delivery-dates-manager') . '</th>';
                echo '<td>' . esc_html($formatted_date) . '</td>';
                echo '</tr>';
            }
            
            if ($delivery_type) {
                $type_label = $delivery_type === 'same_day' ? __('Same-Day Delivery', 'delivery-dates-manager') : __('Standard Delivery', 'delivery-dates-manager');
                echo '<tr>';
                echo '<th>' . esc_html__('Delivery Type', 'delivery-dates-manager') . '</th>';
                echo '<td>' . esc_html($type_label) . '</td>';
                echo '</tr>';
            }
            
            if ($delivery_fee !== '') {
                echo '<tr>';
                echo '<th>' . esc_html__('Delivery Fee', 'delivery-dates-manager') . '</th>';
                echo '<td>' . wc_price($delivery_fee) . '</td>';
                echo '</tr>';
            }
            
            echo '</table></section>';
        }
    }
    
    public function display_email_order_meta($order, $sent_to_admin, $plain_text, $email) {
        $zone_name = $order->get_meta('_ddm_delivery_zone_name');
        $delivery_date = $order->get_meta('_ddm_delivery_date');
        $delivery_type = $order->get_meta('_ddm_delivery_type');
        $delivery_fee = $order->get_meta('_ddm_delivery_fee');
        
        if (!$zone_name && !$delivery_date) {
            return;
        }
        
        if ($plain_text) {
            echo "\n" . esc_html__('DELIVERY INFORMATION', 'delivery-dates-manager') . "\n";
            echo "================================\n";
            
            if ($zone_name) {
                echo esc_html__('Delivery Zone:', 'delivery-dates-manager') . ' ' . esc_html($zone_name) . "\n";
            }
            
            if ($delivery_date) {
                $formatted_date = date_i18n(get_option('date_format'), strtotime($delivery_date));
                echo esc_html__('Delivery Date:', 'delivery-dates-manager') . ' ' . esc_html($formatted_date) . "\n";
            }
            
            if ($delivery_type) {
                $type_label = $delivery_type === 'same_day' ? __('Same-Day Delivery', 'delivery-dates-manager') : __('Standard Delivery', 'delivery-dates-manager');
                echo esc_html__('Delivery Type:', 'delivery-dates-manager') . ' ' . esc_html($type_label) . "\n";
            }
            
            if ($delivery_fee !== '') {
                echo esc_html__('Delivery Fee:', 'delivery-dates-manager') . ' ' . strip_tags(wc_price($delivery_fee)) . "\n";
            }
            
            echo "\n";
        } else {
            echo '<div style="margin-bottom: 20px; padding: 15px; background: #f5f5f5; border-radius: 4px;">';
            echo '<h2 style="color: #333; margin: 0 0 10px;">' . esc_html__('Delivery Information', 'delivery-dates-manager') . '</h2>';
            echo '<table cellspacing="0" cellpadding="6" style="width: 100%; border: none;">';
            
            if ($zone_name) {
                echo '<tr><td style="padding: 6px 0; border: none;"><strong>' . esc_html__('Delivery Zone:', 'delivery-dates-manager') . '</strong></td>';
                echo '<td style="padding: 6px 0; border: none;">' . esc_html($zone_name) . '</td></tr>';
            }
            
            if ($delivery_date) {
                $formatted_date = date_i18n(get_option('date_format'), strtotime($delivery_date));
                echo '<tr><td style="padding: 6px 0; border: none;"><strong>' . esc_html__('Delivery Date:', 'delivery-dates-manager') . '</strong></td>';
                echo '<td style="padding: 6px 0; border: none;">' . esc_html($formatted_date) . '</td></tr>';
            }
            
            if ($delivery_type) {
                $type_label = $delivery_type === 'same_day' ? __('Same-Day Delivery', 'delivery-dates-manager') : __('Standard Delivery', 'delivery-dates-manager');
                echo '<tr><td style="padding: 6px 0; border: none;"><strong>' . esc_html__('Delivery Type:', 'delivery-dates-manager') . '</strong></td>';
                echo '<td style="padding: 6px 0; border: none;">' . esc_html($type_label) . '</td></tr>';
            }
            
            if ($delivery_fee !== '') {
                echo '<tr><td style="padding: 6px 0; border: none;"><strong>' . esc_html__('Delivery Fee:', 'delivery-dates-manager') . '</strong></td>';
                echo '<td style="padding: 6px 0; border: none;">' . wc_price($delivery_fee) . '</td></tr>';
            }
            
            echo '</table></div>';
        }
    }
    
    public function add_order_preview_meta($data, $order) {
        $zone_name = $order->get_meta('_ddm_delivery_zone_name');
        $delivery_date = $order->get_meta('_ddm_delivery_date');
        
        if ($delivery_date) {
            $formatted_date = date_i18n(get_option('date_format'), strtotime($delivery_date));
            $data['delivery_info'] = sprintf(
                '%s - %s',
                $zone_name ? $zone_name : __('N/A', 'delivery-dates-manager'),
                $formatted_date
            );
        }
        
        return $data;
    }
    
    public function add_delivery_column($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            
            if ($key === 'order_date') {
                $new_columns['ddm_delivery_date'] = __('Delivery Date', 'delivery-dates-manager');
            }
        }
        
        return $new_columns;
    }
    
    public function add_delivery_column_content($column, $post_id) {
        if ($column === 'ddm_delivery_date') {
            $order = wc_get_order($post_id);
            if ($order) {
                $delivery_date = $order->get_meta('_ddm_delivery_date');
                $zone_name = $order->get_meta('_ddm_delivery_zone_name');
                
                if ($delivery_date) {
                    $formatted_date = date_i18n(get_option('date_format'), strtotime($delivery_date));
                    echo '<strong>' . esc_html($formatted_date) . '</strong>';
                    
                    if ($zone_name) {
                        echo '<br><small>' . esc_html($zone_name) . '</small>';
                    }
                } else {
                    echo '<span class="na">&ndash;</span>';
                }
            }
        }
    }
    
    public function make_delivery_column_sortable($columns) {
        $columns['ddm_delivery_date'] = 'ddm_delivery_date';
        return $columns;
    }
    
    public function sort_by_delivery_date($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        
        if ($query->get('orderby') === 'ddm_delivery_date') {
            $query->set('meta_key', '_ddm_delivery_date');
            $query->set('orderby', 'meta_value');
        }
    }
    
    public static function get_order_delivery_info($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return false;
        }
        
        return array(
            'zone_id' => $order->get_meta('_ddm_delivery_zone'),
            'zone_name' => $order->get_meta('_ddm_delivery_zone_name'),
            'delivery_date' => $order->get_meta('_ddm_delivery_date'),
            'delivery_type' => $order->get_meta('_ddm_delivery_type'),
            'delivery_fee' => $order->get_meta('_ddm_delivery_fee'),
        );
    }
}

new DDM_Order();
