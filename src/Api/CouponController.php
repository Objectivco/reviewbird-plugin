<?php

namespace reviewbird\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class CouponController {
    /**
     * Register REST API routes
     */
    public function register_routes() {
        register_rest_route('reviewbird/v1', '/coupons/create', [
            'methods' => 'POST',
            'callback' => [$this, 'create_coupon'],
            'permission_callback' => [$this, 'check_store_token'],
        ]);
    }

    /**
     * Permission callback using WooCommerce authentication (consumer key/secret).
     * Uses wc_rest_check_post_permissions for shop_coupon post type.
     */
    public function check_store_token(WP_REST_Request $request) {
        return wc_rest_check_post_permissions('shop_coupon', 'create', 0);
    }

    /**
     * Create a WooCommerce coupon
     */
    public function create_coupon(WP_REST_Request $request) {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return new WP_Error(
                'woocommerce_not_active',
                'WooCommerce is not active',
                ['status' => 400]
            );
        }

        $code = $request->get_param('code');
        $expiry_date = $request->get_param('expiry_date');
        $template_code = $request->get_param('template_code');
        $discount_type = $request->get_param('discount_type');
        $amount = $request->get_param('amount');

        // Validate required parameters
        if (empty($code)) {
            return new WP_Error(
                'missing_code',
                'Coupon code is required',
                ['status' => 400]
            );
        }

        try {
            // If template code provided, clone the template coupon
            if (!empty($template_code)) {
                $coupon_id = $this->clone_template_coupon($template_code, $code, $expiry_date);
            } else {
                // Create new coupon with provided parameters
                $coupon_id = $this->create_new_coupon($code, $discount_type, $amount, $expiry_date);
            }

            if (is_wp_error($coupon_id)) {
                return $coupon_id;
            }

            return new WP_REST_Response([
                'coupon_id' => $coupon_id,
                'code' => $code,
            ], 200);
        } catch (\Exception $e) {
            return new WP_Error(
                'coupon_creation_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Clone a template coupon with new code and expiry
     */
    private function clone_template_coupon($template_code, $new_code, $expiry_date) {
        // Find template coupon
        $template_coupons = get_posts([
            'post_type' => 'shop_coupon',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'title' => $template_code,
        ]);

        if (empty($template_coupons)) {
            return new WP_Error(
                'template_not_found',
                "Template coupon '{$template_code}' not found",
                ['status' => 404]
            );
        }

        $template_coupon_id = $template_coupons[0]->ID;
        $template_coupon = new \WC_Coupon($template_coupon_id);

        // Create new coupon with same settings as template
        $coupon = new \WC_Coupon();
        $coupon->set_code(strtoupper($new_code));

        // Clone all settings from template
        $coupon->set_discount_type($template_coupon->get_discount_type());
        $coupon->set_amount($template_coupon->get_amount());
        $coupon->set_individual_use($template_coupon->get_individual_use());
        $coupon->set_product_ids($template_coupon->get_product_ids());
        $coupon->set_excluded_product_ids($template_coupon->get_excluded_product_ids());
        $coupon->set_usage_limit($template_coupon->get_usage_limit());
        $coupon->set_usage_limit_per_user($template_coupon->get_usage_limit_per_user());
        $coupon->set_limit_usage_to_x_items($template_coupon->get_limit_usage_to_x_items());
        $coupon->set_free_shipping($template_coupon->get_free_shipping());
        $coupon->set_product_categories($template_coupon->get_product_categories());
        $coupon->set_excluded_product_categories($template_coupon->get_excluded_product_categories());
        $coupon->set_exclude_sale_items($template_coupon->get_exclude_sale_items());
        $coupon->set_minimum_amount($template_coupon->get_minimum_amount());
        $coupon->set_maximum_amount($template_coupon->get_maximum_amount());
        $coupon->set_email_restrictions($template_coupon->get_email_restrictions());

        // Set new expiry date (override template expiry)
        if (!empty($expiry_date)) {
            $coupon->set_date_expires($expiry_date);
        }

        // Add metadata to track this is from reviewbird
        $coupon->add_meta_data('_reviewbird_generated', true);
        $coupon->add_meta_data('_reviewbird_template', $template_code);

        $coupon->save();

        return $this->force_uppercase_code($coupon->get_id(), $new_code);
    }

    /**
     * Create a new coupon with specified parameters
     */
    private function create_new_coupon($code, $discount_type, $amount, $expiry_date) {
        // Validate discount type and amount
        if (empty($discount_type) || empty($amount)) {
            return new WP_Error(
                'missing_parameters',
                'discount_type and amount are required when not using template',
                ['status' => 400]
            );
        }

        $coupon = new \WC_Coupon();
        $coupon->set_code(strtoupper($code));
        $coupon->set_discount_type($discount_type); // 'percent' or 'fixed_cart'
        $coupon->set_amount(floatval($amount));

        // Set usage limits
        $coupon->set_usage_limit(1); // One use per coupon
        $coupon->set_usage_limit_per_user(1); // One use per user

        // Set expiry date
        if (!empty($expiry_date)) {
            $coupon->set_date_expires($expiry_date);
        }

        // Add metadata to track this is from reviewbird
        $coupon->add_meta_data('_reviewbird_generated', true);

        $coupon->save();

        return $this->force_uppercase_code($coupon->get_id(), $code);
    }

    /**
     * Force coupon code to uppercase by directly updating post_title
     *
     * WooCommerce's set_code() uses sanitize_title() which converts to lowercase.
     * This bypasses that sanitization to preserve uppercase codes.
     */
    private function force_uppercase_code($coupon_id, $code) {
        wp_update_post([
            'ID' => $coupon_id,
            'post_title' => strtoupper($code),
        ]);

        return $coupon_id;
    }
}
