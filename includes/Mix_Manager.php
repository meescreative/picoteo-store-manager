<?php

namespace Picoteo\StoreManager;

if (!defined('ABSPATH')) {
    exit;
}

class Mix_Manager extends Base
{

    public function register_hooks()
    {
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_cart_item'], 10, 4);
        add_filter('woocommerce_get_item_data', [$this, 'render_cart_content'], 10, 2);
    }

    public function register_rest_routes()
    {
        register_rest_route('psm/v1', '/picomix/add-to-cart', [
            'methods'             => 'POST',
            'permission_callback' => '__return_true',
            'callback'            => [$this, 'psm_handle_picomix_add_to_cart'],
        ]);
    }

    function psm_resolve_parent_variation_id(\WC_Product $product, string $size): int
    {
        if (!$product instanceof \WC_Product_Variable) {
            return 0;
        }

        $target_size = strtolower(trim($size));
        foreach ($product->get_children() as $child_id) {
            $variation = wc_get_product($child_id);
            if (!$variation instanceof \WC_Product_Variation || !$variation->exists()) {
                continue;
            }
            $attr_size = strtolower(trim((string) $variation->get_attribute('pa_grootte')));
            if ($attr_size === $target_size) {
                return (int) $variation->get_id();
            }
        }

        return 0;
    }

    public function psm_handle_picomix_add_to_cart(\WP_REST_Request $request)
    {
        if (!function_exists('WC')) {
            return new \WP_REST_Response(['message' => 'WooCommerce niet beschikbaar'], 500);
        }

        // 1. Laad cart-functies en frontend-includes indien nog niet geladen in REST-context
        if (defined('WC_ABSPATH') && !function_exists('wc_load_cart')) {
            include_once WC_ABSPATH . 'includes/wc-cart-functions.php';
            include_once WC_ABSPATH . 'includes/class-wc-cart.php';
        }

        if (method_exists(WC(), 'frontend_includes') && !did_action('woocommerce_init')) {
            WC()->frontend_includes();
        }

        // 2. Initialiseer klant-sessie, customer en cart
        if (is_null(WC()->session)) {
            $session_class = apply_filters('woocommerce_session_handler', \WC_Session_Handler::class);
            WC()->session  = new $session_class();
            WC()->session->init();
        }

        if (is_null(WC()->customer)) {
            WC()->customer = new \WC_Customer(get_current_user_id(), true);
        }

        if (is_null(WC()->cart)) {
            WC()->cart = new \WC_Cart();
        }

        if (method_exists(WC()->session, 'set_customer_session_cookie')) {
            WC()->session->set_customer_session_cookie(true);
        }

        if (!WC()->cart) {
            return new \WP_REST_Response(['message' => 'WooCommerce cart niet beschikbaar'], 500);
        }

        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_params();
        }

        $parent_id = isset($params['parent_product_id']) ? absint($params['parent_product_id']) : 0;
        $quantity  = isset($params['quantity']) ? max(1, absint($params['quantity'])) : 1;
        $size      = isset($params['size']) ? sanitize_key((string) $params['size']) : 'small';
        $inhoud    = isset($params['inhoud']) && is_array($params['inhoud']) ? $params['inhoud'] : [];

        if ($parent_id <= 0) {
            return new \WP_REST_Response(['message' => 'Ongeldig parent_product_id'], 400);
        }

        $product = wc_get_product($parent_id);
        if (!$product || !$product->exists()) {
            return new \WP_REST_Response(['message' => 'Hoofdproduct niet gevonden'], 404);
        }

        // Sanitize inhoud-array
        $clean_inhoud = [];
        foreach ($inhoud as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $item_qty = isset($entry['quantity']) ? absint($entry['quantity']) : 0;
            if ($item_qty <= 0) {
                continue;
            }

            $clean_inhoud[] = [
                'variation_id'  => isset($entry['variation_id']) ? absint($entry['variation_id']) : 0,
                'product_id'    => isset($entry['product_id']) ? absint($entry['product_id']) : 0,
                'category_slug' => isset($entry['category_slug']) ? sanitize_title((string) $entry['category_slug']) : '',
                'name'          => isset($entry['name']) ? sanitize_text_field((string) $entry['name']) : '',
                'quantity'      => $item_qty,
            ];
        }

        $variation_id    = self::psm_resolve_parent_variation_id($product, $size);
        $variation_attrs = [];

        if ($variation_id > 0) {
            $var_obj = \wc_get_product($variation_id);
            if ($var_obj instanceof \WC_Product_Variation) {
                $variation_attrs = $var_obj->get_variation_attributes();
            }
        }

        $cart_item_data = [
            'picomix_custom' => [
                'size'   => $size,
                'inhoud' => $clean_inhoud,
            ],
            'picomix_unique_key' => md5(wp_json_encode([
                'parent_id'    => $parent_id,
                'variation_id' => $variation_id,
                'size'         => $size,
                'inhoud'       => $clean_inhoud,
            ])),
        ];

        $cart_item_key = \WC()->cart->add_to_cart(
            $parent_id,
            $quantity,
            $variation_id,
            $variation_attrs,
            $cart_item_data
        );

        if (!$cart_item_key) {
            return new \WP_REST_Response(['message' => 'Kon item niet toevoegen aan winkelwagen'], 400);
        }

        \WC()->cart->calculate_totals();

        return new \WP_REST_Response([
            'success'       => true,
            'cart_item_key' => $cart_item_key,
            'cart_count'    => \WC()->cart->get_cart_contents_count(),
            'cart_subtotal' => \WC()->cart->get_cart_subtotal(),
        ], 200);
    }

    public function render_cart_content(array $item_data, array $cart_item): array
    {
        if (empty($cart_item['picomix_custom']) || !is_array($cart_item['picomix_custom'])) {
            return $item_data;
        }

        $size   = $cart_item['picomix_custom']['size'] ?? '';
        $inhoud = $cart_item['picomix_custom']['inhoud'] ?? [];

        if ($size) {
            $item_data[] = [
                'key'   => 'Formaat',
                'value' => ucfirst(esc_html($size)),
            ];
        }

        if (!empty($inhoud)) {
            $lines = [];
            foreach ($inhoud as $row) {
                $label   = $row['name'] ?: ('Product #' . (int) ($row['product_id'] ?? 0));
                $qty     = (int) ($row['quantity'] ?? 0);
                $lines[] = sprintf('%s × %d', esc_html($label), $qty);
            }

            $item_data[] = [
                'key'   => 'Inhoud',
                'value' => implode(', ', $lines),
            ];
        }

        return $item_data;
    }

    // 2. Sla de inhoud ook op in de order line item meta bij afrekenen
    public function save_cart_item($item, string $cart_item_key, array $values, $order): void
    {
        if (empty($values['picomix_custom']) || !is_array($values['picomix_custom'])) {
            return;
        }

        if (!empty($values['picomix_custom']['size'])) {
            $item->add_meta_data('Formaat', ucfirst(sanitize_text_field($values['picomix_custom']['size'])), true);
        }

        if (!empty($values['picomix_custom']['inhoud']) && is_array($values['picomix_custom']['inhoud'])) {
            $formatted = array_map(function ($row) {
                $name = $row['name'] ?? ('Item #' . (int) ($row['product_id'] ?? 0));
                $qty  = (int) ($row['quantity'] ?? 0);
                return "{$name} (x{$qty})";
            }, $values['picomix_custom']['inhoud']);

            $item->add_meta_data('Inhoud', implode(', ', $formatted), true);
            $item->add_meta_data('_picomix_inhoud_raw', $values['picomix_custom']['inhoud'], true);
        }
    }
}
