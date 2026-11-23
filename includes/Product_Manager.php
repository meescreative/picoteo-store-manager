<?php

namespace Picoteo\StoreManager;

if (!defined('ABSPATH')) {
    exit;
}

class Product_Manager extends Base
{

    public function register_hooks()
    {
        add_action('init', [$this, 'add_custom_rewrite_rules']);
        add_filter('query_vars', [$this, 'add_custom_query_vars']);
        add_action('parse_query', [$this, 'set_virtual_page_query']);
        add_action('template_redirect', [$this, 'prevent_canonical_redirect'], 0);
        add_action('init', [$this, 'create_psm_product_categories']);
        add_action('wp_ajax_psm_update_product_gallery', [$this, 'ajax_update_product_gallery']);
        add_action('wp_ajax_psm_update_product_thumbnail', [$this, 'ajax_update_product_thumbnail']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    public function ajax_update_product_thumbnail()
    {
        check_ajax_referer('psm_gallery_nonce', 'nonce');

        $product_id    = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        $attachment_id = isset($_POST['attachment_id']) ? (int) $_POST['attachment_id'] : 0;

        if ($product_id <= 0 || $attachment_id <= 0 || !function_exists('wc_get_product')) {
            wp_send_json_error(['message' => 'Ongeldig product-ID of afbeelding-ID.'], 400);
        }

        if (!current_user_can('edit_post', $product_id)) {
            wp_send_json_error(['message' => 'Geen rechten om dit product te bewerken.'], 403);
        }

        $attachment = get_post($attachment_id);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            wp_send_json_error(['message' => 'Geselecteerde afbeelding bestaat niet.'], 404);
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(['message' => 'Product niet gevonden.'], 404);
        }

        $product->set_image_id($attachment_id);
        $product->save();

        wp_send_json_success([
            'product_id'    => $product_id,
            'thumbnail_id'  => $attachment_id,
            'thumbnail_url' => wp_get_attachment_image_url($attachment_id, 'woocommerce_thumbnail') ?: '',
        ]);
    }


    public function register_rest_routes()
    {
        register_rest_route('psm/v1', '/product/(?P<id>\d+)', [
            'methods'             => \WP_REST_Server::READABLE, // GET
            'callback'            => [$this, 'rest_get_product_details'],
            'permission_callback' => function ($request) {
                $product_id = (int) $request['id'];
                return is_user_logged_in() && current_user_can('edit_post', $product_id);
            },
            'args'                => [
                'id' => [
                    'validate_callback' => fn($param) => is_numeric($param) && (int) $param > 0,
                ],
            ],
        ]);

        register_rest_route('psm/v1', '/product/(?P<id>\d+)/variations/(?P<variation_id>\d+)', [
            'methods'             => \WP_REST_Server::READABLE, // GET
            'callback'            => [$this, 'rest_get_product_variation'],
            'permission_callback' => function ($request) {
                $product_id   = (int) $request['id'];
                $variation_id = (int) $request['variation_id'];

                return is_user_logged_in()
                    && current_user_can('edit_post', $product_id)
                    && current_user_can('edit_post', $variation_id);
            },
            'args'                => [
                'id' => [
                    'validate_callback' => fn($param) => is_numeric($param) && (int) $param > 0,
                ],
                'variation_id' => [
                    'validate_callback' => fn($param) => is_numeric($param) && (int) $param > 0,
                ],
            ],
        ]);

        register_rest_route('psm/v1', '/products', [
            'methods'             => \WP_REST_Server::READABLE, // GET
            'callback'            => [$this, 'rest_get_published_products'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function rest_get_published_products($request)
    {
        try {
            if (!function_exists('wc_get_products')) {
                return new \WP_Error('wc_unavailable', 'WooCommerce is niet beschikbaar.', ['status' => 500]);
            }

            $products = \wc_get_products([
                'status' => 'publish',
                'limit'  => -1,
                'type'   => ['simple', 'variable'], // optioneel: beperk tot gewenste producttypes
            ]);

            $items = [];

            foreach ($products as $product) {
                if (!$product || !is_object($product)) {
                    continue;
                }

                $product_id = (int) $product->get_id();

                // Categorieën ophalen
                $terms = \get_the_terms($product_id, 'product_cat');
                $categories = [];
                if (!empty($terms) && !\is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        $categories[] = [
                            'id'   => (int) $term->term_id,
                            'slug' => (string) $term->slug,
                            'name' => (string) $term->name,
                        ];
                    }
                }

                // Variaties ophalen (indien variabel product)
                $variations_data = [];
                if ($product->is_type('variable')) {
                    foreach ($product->get_children() as $variation_id) {
                        $variation = \wc_get_product($variation_id);
                        if (!$variation || !$variation->exists() || $variation->get_status() !== 'publish') {
                            continue;
                        }

                        // Bijv. ['pa_formaat' => 'small'] -> 'small'
                        $raw_attrs = $variation->get_attributes();
                        $attr_values = array_values(array_filter(array_map(
                            fn($val) => \sanitize_title((string) $val),
                            \is_array($raw_attrs) ? $raw_attrs : []
                        )));
                        $attribute_key = implode('-', $attr_values);

                        $raw_regular = (string) $variation->get_regular_price();

                        $variations_data[] = [
                            'id'            => (int) $variation->get_id(),
                            'attribute_key' => $attribute_key,
                            'attributes'    => $raw_attrs,
                            'description'   => (string) $variation->get_description(),
                            'regular_price' => $raw_regular,
                        ];
                    }
                }

                $items[] = [
                    'id'            => $product_id,
                    'product_cat'   => $categories,
                    'product_title' => (string) $product->get_name(),
                    'description'   => (string) $product->get_description(),
                    'permalink'     => (string) \get_permalink($product_id),
                    'variations'    => $variations_data,
                ];
            }

            return \rest_ensure_response([
                'count'    => \count($items),
                'products' => $items,
            ]);
        } catch (\Throwable $e) {
            return new \WP_Error('rest_internal_error', $e->getMessage(), ['status' => 500]);
        }
    }

    public function rest_get_product_variation($request)
    {
        try {
            $product_id   = isset($request['id']) ? (int) $request['id'] : 0;
            $variation_id = isset($request['variation_id']) ? (int) $request['variation_id'] : 0;

            if ($product_id <= 0 || $variation_id <= 0 || !\function_exists('\wc_get_product')) {
                return new \WP_Error('invalid_params', 'Ongeldig product-ID, variatie-ID of WooCommerce ontbreekt.', ['status' => 400]);
            }

            $product = \wc_get_product($product_id);
            if (!$product || !\is_object($product)) {
                return new \WP_Error('product_not_found', 'Product niet gevonden.', ['status' => 404]);
            }

            $variation = \wc_get_product($variation_id);
            if (!$variation || !$variation->exists() || !$variation->is_type('variation')) {
                return new \WP_Error('variation_not_found', 'Variatie niet gevonden.', ['status' => 404]);
            }

            // Controleer dat deze variatie ook echt onder dit hoofdproduct valt
            if ((int) $variation->get_parent_id() !== $product_id) {
                return new \WP_Error('variation_mismatch', 'Deze variatie behoort niet tot dit product.', ['status' => 404]);
            }

            $image_id = (int) $variation->get_image_id();

            return \rest_ensure_response([
                'product_id'     => $product_id,
                'variation_id'   => $variation_id,
                'status'         => (string) $variation->get_status(),
                'sku'            => (string) $variation->get_sku(),
                'regular_price'  => (string) $variation->get_regular_price(),
                'description'    => (string) $variation->get_description(),
                'sale_price'     => (string) $variation->get_sale_price(),
                'promo_start'    => (string) $variation->get_date_on_sale_from(),
                'promo_end'      => (string) $variation->get_date_on_sale_to(),
                'price'          => (string) $variation->get_price(),
                'manage_stock'   => (bool) $variation->get_manage_stock(),
                'stock_quantity' => $variation->get_stock_quantity(),
                'stock_status'   => (string) $variation->get_stock_status(),
                'attributes'     => $variation->get_attributes(),
                'image_id'       => $image_id,
                'image_url'      => $image_id ? (\wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail') ?: '') : '',
            ]);
        } catch (\Throwable $e) {
            return new \WP_Error('rest_internal_error', $e->getMessage(), ['status' => 500]);
        }
    }

    public function rest_get_product_details(\WP_REST_Request $request)
    {
        $product_id = (int) $request['id'];
        $product    = function_exists('wc_get_product') ? \wc_get_product($product_id) : false;

        if (!$product) {
            return new \WP_Error('not_found', 'Product niet gevonden.', ['status' => 404]);
        }

        $raw_tax_class = (string) $product->get_tax_class();

        $categories = get_the_terms($product_id, 'product_cat');

        $status = get_post_status($product_id);

        $tax_map = [
            ''         => 'tax-6',
            'standard' => 'tax-6',
            '6'        => 'tax-6',
            'tax-6'    => 'tax-6',
            '12'       => 'tax-12',
            'tax-12'   => 'tax-12',
            '21'       => 'tax-21',
            'tax-21'   => 'tax-21',
        ];

        return rest_ensure_response([
            'product_id'        => $product_id,
            'title'             => $product->get_title(),
            'description'       => $product->get_description(),
            'status'            => $status,
            'category'          => $categories[0],
            'tax_class_raw'     => $raw_tax_class,
            'tax_class_value'   => $tax_map[$raw_tax_class] ?? $raw_tax_class,
            'available_classes' => class_exists('WC_Tax') ? \WC_Tax::get_tax_classes() : [],
        ]);
    }

    public static function ajax_update_product_gallery()
    {
        check_ajax_referer('psm_gallery_nonce', 'nonce');

        $product_id     = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        $attachment_id  = isset($_POST['attachment_id']) ? (int) $_POST['attachment_id'] : 0;
        $gallery_action = isset($_POST['gallery_action']) ? sanitize_key(wp_unslash($_POST['gallery_action'])) : '';

        if (!$product_id || !$attachment_id) {
            wp_send_json_error(['message' => 'Ongeldig product- of afbeelding-ID.'], 400);
        }

        if (!in_array($gallery_action, ['add', 'remove'], true)) {
            wp_send_json_error(['message' => 'Ongeldige galerij-actie.'], 400);
        }

        if (!current_user_can('edit_product', $product_id)) {
            wp_send_json_error(['message' => 'Geen rechten om dit product te bewerken.'], 403);
        }

        if ('add' === $gallery_action && 'attachment' !== get_post_type($attachment_id)) {
            wp_send_json_error(['message' => 'Geselecteerd item is geen geldige bijlage.'], 400);
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(['message' => 'Product niet gevonden.'], 404);
        }

        $gallery_ids = array_map('intval', $product->get_gallery_image_ids());
        $changed     = false;

        if ('add' === $gallery_action) {
            if (!in_array($attachment_id, $gallery_ids, true)) {
                $gallery_ids[] = $attachment_id;
                $changed       = true;
            }
        } elseif ('remove' === $gallery_action) {
            $filtered = array_values(array_filter(
                $gallery_ids,
                static function ($id) use ($attachment_id) {
                    return $id !== $attachment_id;
                }
            ));

            if (count($filtered) !== count($gallery_ids)) {
                $gallery_ids = $filtered;
                $changed     = true;
            }
        }

        if ($changed) {
            $product->set_gallery_image_ids($gallery_ids);
            $product->save();
        }

        wp_send_json_success([
            'gallery_ids'    => $gallery_ids,
            'attachment_id'  => $attachment_id,
            'gallery_action' => $gallery_action,
            'changed'        => $changed,
            'message'        => 'add' === $gallery_action
                ? 'Afbeelding toegevoegd aan galerij.'
                : 'Afbeelding verwijderd uit galerij.',
        ]);
    }

    /**
     * Voeg custom rewrite rules toe voor de virtuele beheerpagina
     */
    public static function add_custom_rewrite_rules()
    {
        add_rewrite_rule('^psm/product/([0-9]+)/?$', 'index.php?psm_manage_product=1&product_id=$matches[1]', 'top');
    }

    /**
     * Registreer custom query vars
     */
    public static function add_custom_query_vars($vars)
    {
        $vars[] = 'psm_manage_product';
        $vars[] = 'product_id';
        return $vars;
    }

    /**
     * Vang de virtuele beheer-URL op en laad de template
     */
    public function set_virtual_page_query($query)
    {
        if (!is_admin() && $query->is_main_query() && (int) $query->get('psm_manage_product') === 1) {
            $product_id = (int) $query->get('product_id');

            $query->is_home     = false;
            $query->is_archive  = false;
            $query->is_singular = true;
            $query->is_page     = true;
            $query->is_single   = false;
            $query->is_404      = false;

            status_header(200);

            if ($product_id > 0) {
                add_action('wp', function () use ($product_id) {
                    global $post, $product;

                    $post = get_post($product_id);
                    if ($post) {
                        setup_postdata($post);
                        $product = wc_get_product($product_id);
                    }

                    wp_enqueue_media();

                    add_action('wp_footer', function () use ($product_id) {
?>
                        <script>
                            window.psmProductId = <?php echo (int) $product_id; ?>;
                            window.psmGalleryNonce = <?php echo wp_json_encode(wp_create_nonce('psm_gallery_nonce')); ?>;
                            window.psmRestUrl = <?php echo wp_json_encode(esc_url_raw(rest_url('psm/v1/'))); ?>;
                            window.psmRestNonce = <?php echo wp_json_encode(wp_create_nonce('wp_rest')); ?>;
                        </script>
<?php
                    }, 5);
                });
            }
        }
    }

    public function prevent_canonical_redirect()
    {
        if (get_query_var('psm_manage_product') == 1) {
            remove_action('template_redirect', 'redirect_canonical');
        }
    }

    public static function create_variable_product($product_name, $product_code, $category)
    {
        try {
            // Maak een nieuw WooCommerce Variable Product aan
            $product = new \WC_Product_Variable();
            $product->set_name($product_name);
            $product->set_status('publish');
            $product->set_catalog_visibility('visible');

            $product_id = $product->save();

            if (!$product_id || is_wp_error($product_id)) {
                return false; // Geef netjes false terug bij falen
            }

            // Koppel de WooCommerce productcategorie (product_cat taxonomie)
            $term = term_exists($category, 'product_cat');
            if (!$term) {
                $term = wp_insert_term(ucfirst($category), 'product_cat');
            }
            if (!is_wp_error($term)) {
                $term_id = is_array($term) ? $term['term_id'] : $term;
                wp_set_object_terms($product_id, [(int)$term_id], 'product_cat');
            }

            // Stel het attribuut 'Grootte' (pa_grootte) in
            $attribute_slug = 'grootte';
            $attribute_name = 'Grootte';
            $taxonomy_name  = wc_attribute_taxonomy_name($attribute_slug);

            if (!taxonomy_exists($taxonomy_name)) {
                wc_create_attribute([
                    'name'         => $attribute_name,
                    'slug'         => $attribute_slug,
                    'type'         => 'select',
                    'order_by'     => 'menu_order',
                    'has_archives' => false,
                ]);
                delete_transient('wc_attribute_taxonomies');
                register_taxonomy($taxonomy_name, ['product'], []);
            }

            $terms = ['small', 'large'];
            $term_ids = [];

            foreach ($terms as $term_text) {
                if (!term_exists($term_text, $taxonomy_name)) {
                    $t = wp_insert_term($term_text, $taxonomy_name);
                    if (!is_wp_error($t)) {
                        $term_ids[] = $t['term_id'];
                    }
                } else {
                    $t = get_term_by('name', $term_text, $taxonomy_name);
                    if ($t) {
                        $term_ids[] = $t->term_id;
                    }
                }
            }

            $attribute_id = wc_attribute_taxonomy_id_by_name($attribute_slug);

            $attribute = new \WC_Product_Attribute();
            $attribute->set_id($attribute_id);
            $attribute->set_name($taxonomy_name);
            $attribute->set_options($term_ids);
            $attribute->set_position(0);
            $attribute->set_visible(true);
            $attribute->set_variation(true);

            $product->set_attributes([$attribute]);
            $product->save();

            // Genereer automatisch de variaties
            $variation_data = [
                ['name' => 'Small', 'slug' => 'small', 'sku_suffix' => 'SM'],
                ['name' => 'Large', 'slug' => 'large', 'sku_suffix' => 'LG'],
            ];

            foreach ($variation_data as $v_info) {
                $variation = new \WC_Product_Variation();
                $variation->set_parent_id($product_id);

                $variation->set_attributes([
                    $taxonomy_name => $v_info['slug']
                ]);

                $sku = "PRE-{$product_code}-{$v_info['sku_suffix']}";
                $variation->set_sku($sku);

                $variation->set_status('publish');
                $variation->set_manage_stock(false);
                $variation->save();
            }

            $parent_product = wc_get_product($product_id);
            if ($parent_product && $parent_product->is_type('variable')) {
                $default_attributes = [
                    $taxonomy_name => $variation_data[0]['slug']
                ];
                $parent_product->set_default_attributes($default_attributes);
                $parent_product->save();
            }

            // BELANGRIJK: Geef het ID terug zodat de handler de redirect kan bouwen!
            return $product_id;
        } catch (\Throwable $e) {
            // Log eventueel de fout naar debug.log
            error_log('PSM Exception: ' . $e->getMessage());
            return false;
        }
    }

    public static function create_simple_product($product_name, $product_code, $category)
    {
        // 1. Maak een nieuw WC_Product_Simple object aan
        $product = new \WC_Product_Simple();

        // 2. Vul de basisgegevens in
        $product->set_name($product_name);
        $product->set_sku($product_code);
        $product->set_status('publish');
        $product->set_catalog_visibility('visible');

        // 3. Sla het product op in de database (dit genereert het product_id)
        $product_id = $product->save();

        if ($product_id && !is_wp_error($product_id)) {
            if (!empty($category)) {
                $term = get_term_by('slug', $category, 'product_cat');
                if ($term && !is_wp_error($term)) {
                    wp_set_object_terms($product_id, [(int) $term->term_id], 'product_cat');
                }
            }
            return $product_id;
        }
        return false;
    }

    /**
     * @param array $fields
     */
    public static function process_form_submission($fields)
    {
        // Rechten & CSRF check
        $csrf_token = isset($fields['csrfToken']) ? sanitize_text_field($fields['csrfToken']) : '';
        if (!current_user_can('manage_options') || empty($csrf_token)) {
            wp_send_json_error(['message' => 'Geen toestemming.'], 403);
        }

        $product_name = isset($fields['psm_product_name']) ? sanitize_text_field($fields['psm_product_name']) : '';
        $product_code = isset($fields['psm_product_code']) ? strtoupper(sanitize_text_field($fields['psm_product_code'])) : '';
        $category     = isset($fields['psm_product_category']) ? sanitize_text_field($fields['psm_product_category']) : '';

        if (empty($product_name) || empty($product_code) || empty($category)) {
            wp_send_json_error(['message' => 'Niet alle verplichte velden zijn ingevuld.'], 400);
        }

        $product_id = 0;

        if ($category === 'drank') {
            //Enkel drank is momenteel een simple_product
            $product_id = self::create_simple_product($product_name, $product_code, $category);
        } else {
            $product_id = self::create_variable_product($product_name, $product_code, $category);
        }

        if ($product_id && !is_wp_error($product_id)) {
            $term = get_term_by('slug', $category, 'product_cat');
            if ($term && !is_wp_error($term)) {
                wp_set_object_terms($product_id, [(int) $term->term_id], 'product_cat');
            }

            wp_send_json_success([
                'post_id'      => $product_id,
                'redirect_url' => home_url("/psm/product/{$product_id}"),
                'message'      => 'Product succesvol aangemaakt!'
            ]);
        }

        wp_send_json_error(['message' => 'Het product kon niet worden aangemaakt of de categorie is ongeldig.'], 400);
    }

    public static function create_psm_product_categories()
    {
        $categories = [
            ['name' => 'Premade Aperobox', 'slug' => 'premade'],
            ['name' => 'Drank', 'slug' => 'drank'],
            ['name' => 'Amuses', 'slug' => 'amuse'],
            ['name' => 'Brood & Crackers', 'slug' => 'brood'],
            ['name' => 'Dips & Tapenades', 'slug' => 'dips'],
            ['name' => 'Kaas', 'slug' => 'kaas'],
            ['name' => 'Charcuterie', 'slug' => 'charcuterie'],
            ['name' => 'Confituur, Chutney & Honing', 'slug' => 'confituur'],
            ['name' => 'Fruit & Noten', 'slug' => 'fruit'],
            ['name' => 'Antipasti', 'slug' => 'antipasti'],
        ];

        foreach ($categories as $cat) {
            // Controleer of de categorie op basis van de slug al bestaat
            if (!term_exists($cat['slug'], 'product_cat')) {
                wp_insert_term(
                    $cat['name'],
                    'product_cat',
                    [
                        'slug' => $cat['slug']
                    ]
                );
            }
        }
    }

    /**
     * Haal de deadline op voor een specifieke locatie en weeknummer
     *
     * @param int      $location_id De ID van de psm_order_location
     * @param int      $week_number Het ISO-weeknummer (bijv. 45)
     * @param int|null $year        Optioneel jaartal (standaard huidig jaar)
     * @return array|null Array met deadline details of null als niets is gevonden
     */
    public static function get_deadline($location_id, $week_number, $year = null)
    {
        if (!$year) {
            $year = (int) (new \DateTime('today'))->format('o');
        }

        $query = new \WP_Query([
            'post_type'      => 'psm_order_deadline',
            'posts_per_page' => 1,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => 'deadline_pickup_location_id',
                    'value'   => $location_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ],
                [
                    'key'     => 'deadline_for_week',
                    'value'   => $week_number,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ],
            ],
        ]);

        if (!empty($query->posts)) {
            $post_id = $query->posts[0]->ID;
            return [
                'post_id'     => $post_id,
                'title'       => get_the_title($post_id),
                'date'        => get_post_meta($post_id, 'deadline_date', true),
                'hour'        => get_post_meta($post_id, 'deadline_hour', true),
                'location_id' => get_post_meta($post_id, 'deadline_pickup_location_id', true),
                'week'        => get_post_meta($post_id, 'deadline_for_week', true),
            ];
        }

        return null;
    }

    /**
     * AJAX-handler: Haal bestaande deadline op basis van location_id en week
     */
    public function handle_get_deadline()
    {
        // Optioneel: Beveiliging of nonce-controle indien gewenst
        $location_id = isset($_REQUEST['location_id']) ? intval($_REQUEST['location_id']) : 0;
        $week_number = isset($_REQUEST['week']) ? intval($_REQUEST['week']) : 0;

        if (!$location_id || !$week_number) {
            wp_send_json_error(['message' => 'Ongeldige parameters.'], 400);
        }

        // Gebruik je reeds bestaande helper-methode in Deadline_Manager
        $deadline = self::get_deadline($location_id, $week_number);

        if ($deadline) {
            wp_send_json_success([
                'exists' => true,
                'data'   => $deadline // Bevat o.a. 'date' (Y-m-d) en 'hour' (HH:MM)
            ]);
        }

        wp_send_json_success([
            'exists' => false,
            'data'   => null
        ]);
    }

    public static function update_variation($fields)
    {
        // Rechten & CSRF check
        $csrf_token = isset($fields['csrfToken']) ? sanitize_text_field($fields['csrfToken']) : '';
        if (!current_user_can('manage_options') || empty($csrf_token)) {
            wp_send_json_error(['message' => 'Geen toestemming.'], 403);
        }

        //fields die worden meegegeven zijn

    }

    public static function update_product_info($fields)
    {
        // Rechten & CSRF check
        $csrf_token = isset($fields['csrfToken']) ? sanitize_text_field($fields['csrfToken']) : '';
        if (!current_user_can('manage_options') || empty($csrf_token)) {
            wp_send_json_error(['message' => 'Geen toestemming.'], 403);
        }

        $product_id     = isset($fields['product_id']) ? sanitize_text_field($fields['product_id']) : '';

        $product = \wc_get_product($product_id);
        if (!$product || !\is_object($product)) {
            return [
                'success' => false,
                'message' => 'Product niet gevonden.',
                'status'  => 404,
            ];
        }

        if (isset($fields['title'])) {
            $title = sanitize_text_field((string) $fields['title']);
            if ($title !== '') {
                $product->set_name($title);
            }
        }

        // 2. Post status (whitelist van toegestane statussen)
        if (!empty($fields['status'])) {
            $raw_status      = sanitize_key((string) $fields['status']);
            $allowed_statuses = ['publish', 'draft', 'pending', 'private'];
            if (in_array($raw_status, $allowed_statuses, true)) {
                $product->set_status($raw_status);
            }
        }

        // 3. Beschrijving
        if (isset($fields['description'])) {
            $description = wp_kses_post((string) $fields['description']);
            $product->set_description($description);
        }

        // 4. Productcategorie op basis van slug
        if (!empty($fields['product_cat'])) {
            $cat_slug = sanitize_title((string) $fields['product_cat']);
            $term     = get_term_by('slug', $cat_slug, 'product_cat');
            if ($term && !is_wp_error($term)) {
                $product->set_category_ids([(int) $term->term_id]);
            } else {
                return [
                    'success' => false,
                    'message' => 'Geselecteerde productcategorie bestaat niet.',
                    'status'  => 400,
                ];
            }
        }

        // 5. Belastingklasse (van form-key 'tax-6' / 'tax-12' / 'tax-21' naar WC tax_class slug)
        if (isset($fields['tax-class']) || isset($fields['tax_class'])) {
            $raw_tax = (string) ($fields['tax-class'] ?? $fields['tax_class'] ?? '');
            $tax_key = sanitize_key($raw_tax);

            $reverse_tax_map = [
                'tax-6'  => '', // pas aan naar '6' of 'standard' als 6% in WC geen lege slug heeft
                'tax-12' => '12',
                'tax-21' => '21',
                ''       => '',
            ];

            if ($tax_key !== '') {
                $wc_tax_class = $reverse_tax_map[$tax_key] ?? sanitize_text_field($raw_tax);
                $product->set_tax_class($wc_tax_class);
            }
        }

        $product->save();

        return [
            'success'    => true,
            'message'    => 'Product succesvol bijgewerkt.',
            'product_id' => $product_id,
        ];
    }

    public static function update_variation_info($fields)
    {
        // Rechten & CSRF check
        $csrf_token = isset($fields['csrfToken']) ? sanitize_text_field($fields['csrfToken']) : '';
        if (!current_user_can('manage_options') || empty($csrf_token)) {
            wp_send_json_error(['message' => 'Geen toestemming.'], 403);
        }

        $variation_id = isset($fields['variation_id']) ? (int) $fields['variation_id'] : 0;
        $variation = \wc_get_product($variation_id);

        if (!$variation || !\is_object($variation) || !$variation->is_type('variation')) {
            return [
                'success' => false,
                'message' => 'Variatie niet gevonden.',
                'status'  => 404,
            ];
        }

        // 1. SKU
        if (array_key_exists('sku', $fields)) {
            $sku = \sanitize_text_field((string) $fields['sku']);
            $variation->set_sku($sku);
        }

        // 2. Post status (whitelist)
        if (!empty($fields['status'])) {
            $raw_status       = \sanitize_key((string) $fields['status']);
            $allowed_statuses = ['publish', 'draft', 'pending', 'private'];
            if (\in_array($raw_status, $allowed_statuses, true)) {
                $variation->set_status($raw_status);
            }
        }

        // 3. Beschrijving
        if (array_key_exists('description', $fields)) {
            $description = \wp_kses_post((string) $fields['description']);
            $variation->set_description($description);
        }

        // 4. Regular price (WooCommerce verwacht '.' als decimaalteken)
        if (array_key_exists('regular_price', $fields)) {
            $regular_price = \wc_format_decimal(\sanitize_text_field((string) $fields['regular_price']));
            $variation->set_regular_price($regular_price);
        }

        // 5. Promo price (sale_price)
        if (array_key_exists('promo_price', $fields)) {
            $raw_sale   = \sanitize_text_field((string) $fields['promo_price']);
            $sale_price = ($raw_sale === '') ? '' : \wc_format_decimal($raw_sale);
            $variation->set_sale_price($sale_price);
        }

        // 6. Date on sale from
        if (array_key_exists('promo_start', $fields)) {
            $raw_start = trim((string) $fields['promo_start']);
            if ($raw_start === '') {
                $variation->set_date_on_sale_from(null);
            } else {
                $timestamp = \strtotime($raw_start);
                $variation->set_date_on_sale_from($timestamp ? $timestamp : null);
            }
        }

        // 7. Date on sale to
        if (array_key_exists('promo_end', $fields)) {
            $raw_end = trim((string) $fields['promo_end']);
            if ($raw_end === '') {
                $variation->set_date_on_sale_to(null);
            } else {
                // Als alleen YYYY-MM-DD is meegegeven, einde van die dag aanhouden
                if (\preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw_end)) {
                    $raw_end .= ' 23:59:59';
                }
                $timestamp = \strtotime($raw_end);
                $variation->set_date_on_sale_to($timestamp ? $timestamp : null);
            }
        }

        $variation->save();

        $parent_id = (int) $variation->get_parent_id();
        if ($parent_id > 0 && \class_exists('\WC_Product_Variable')) {
            \WC_Product_Variable::sync($parent_id);
        }

        return [
            'success'      => true,
            'message'      => 'Variatie succesvol bijgewerkt.',
            'variation_id' => $variation_id,
            'product_id'   => $parent_id,
        ];
    }

    public static function ajax_save_product_gallery()
    {
        $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        check_ajax_referer('psm_save_gallery_' . $product_id, 'nonce');

        if (!$product_id || !current_user_can('edit_product', $product_id)) {
            wp_send_json_error(['message' => 'Geen rechten om dit product te bewerken.'], 403);
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(['message' => 'Product niet gevonden.'], 404);
        }

        $raw_ids = isset($_POST['gallery_ids']) ? sanitize_text_field(wp_unslash($_POST['gallery_ids'])) : '';
        $ids     = array_filter(array_map('intval', explode(',', $raw_ids)));

        // Optioneel: enkel geldige attachments bewaren
        $valid_ids = array_values(array_filter($ids, function ($id) {
            return $id > 0 && 'attachment' === get_post_type($id);
        }));

        $product->set_gallery_image_ids($valid_ids);
        $product->save();

        wp_send_json_success([
            'gallery_ids' => $valid_ids,
            'message'     => 'Productgalerij opgeslagen.',
        ]);
    }
}
