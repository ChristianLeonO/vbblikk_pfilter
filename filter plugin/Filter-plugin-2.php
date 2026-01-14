<?php
/**
 * Plugin Name: BD – Prisfilter (Breakdance-integrert, auto-discovery)
 * Description: AJAX-filtrering som rendrer via Breakdance Woo loop uten hardkodet element-ID.
 * Version:     2.1.0
 */

if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/bdpf-helpers.php';

use Breakdance\WooCommerce\WooActions;
use function Breakdance\Data\get_tree;

function bdpf_load_shop_properties_once() {
    $candidateIds = bdpf_get_candidate_template_ids();

    foreach ($candidateIds as $templateId) {
        $tree = get_tree($templateId);
        if (!is_array($tree)) {
            continue;
        }

        $tree = bdpf_tree_to_array($tree);
        $props = bdpf_find_shop_props_in_tree($tree);

        if ($props !== null) {
            $GLOBALS['bdpf_shop_properties'] = [
                'template_id' => $templateId,
                'properties' => $props,
            ];
            return;
        }
    }

    $GLOBALS['bdpf_shop_properties'] = false;
}
// Kjør når Breakdance er lastet, så helperne finnes
add_action('breakdance_loaded', 'bdpf_load_shop_properties_once');

add_action('wp_ajax_bdpf_has_shop_element', 'bdpf_has_shop_element');
add_action('wp_ajax_nopriv_bdpf_has_shop_element', 'bdpf_has_shop_element');

/**
 * AJAX: Filtrer produkter og rendre LI’ene via Breakdance + Woo hooks.
 */
add_action('wp_ajax_bdpf_filter_products',        'bdpf_filter_products');
add_action('wp_ajax_nopriv_bdpf_filter_products', 'bdpf_filter_products');

function bdpf_filter_products() {
    check_ajax_referer('bdpf', 'nonce');

    bdpf_load_shop_properties_once();

    $cached = $GLOBALS['bdpf_shop_properties'] ?? null;
    if (!$cached || !is_array($cached) || empty($cached['properties'])) {
        wp_send_json_error([
            'message' => 'Shop Page properties er tom – transient ikke funnet',
        ], 500);
    }

    $props = $cached['properties'];

    $cats  = (array) json_decode(stripslashes($_POST['categories'] ?? '[]'), true);
    $attrs = (array) json_decode(stripslashes($_POST['attributes'] ?? '[]'), true);
    $paged = max(1, (int) ($_POST['paged'] ?? 1));
    $min   = isset($_POST['min_price']) && $_POST['min_price'] !== '' ? (float) $_POST['min_price'] : null;
    $max   = isset($_POST['max_price']) && $_POST['max_price'] !== '' ? (float) $_POST['max_price'] : null;
    $orderby = isset($_POST['orderby']) ? sanitize_text_field(wp_unslash($_POST['orderby'])) : '';

    $filter = function ($args) use ($cats, $attrs, $min, $max, $paged, $orderby) {
        $args['paged'] = $paged;

        // Viktig: Hold samme sortering som katalogen (og gjør den deterministisk) for at paginering
        // ikke skal "hoppe" produkter mellom sider.
        $requestedOrderby = strtolower(trim((string) $orderby));
        if ($requestedOrderby === '' || $requestedOrderby === 'default') {
            $requestedOrderby = 'menu_order';
        }

        // Custom mapping fra frontend-integrasjoner.
        if ($requestedOrderby === 'title') {
            $args['orderby'] = 'title';
            $args['order'] = 'ASC';
        } elseif ($requestedOrderby === 'title-desc') {
            $args['orderby'] = 'title';
            $args['order'] = 'DESC';
        } elseif ($requestedOrderby === 'sku') {
            $args['meta_key'] = '_sku';
            $args['orderby'] = 'meta_value title';
            $args['order'] = 'ASC';
        } elseif (function_exists('woocommerce_get_catalog_ordering_args')) {
            $orderingArgs = woocommerce_get_catalog_ordering_args($requestedOrderby);
            if (is_array($orderingArgs)) {
                $args = array_merge($args, $orderingArgs);
            }
        }

        // Stabil "tie-breaker" for paginering: Hvis flere produkter har lik sorteringsverdi
        // (f.eks. samme dato/menu_order), kan de ellers flytte seg mellom sider.
        $order = strtoupper((string) ($args['order'] ?? 'ASC'));
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'ASC';
        }

        if (isset($args['orderby']) && is_array($args['orderby'])) {
            if (!array_key_exists('ID', $args['orderby'])) {
                $args['orderby']['ID'] = $order;
            }
        } else {
            $orderbyStr = isset($args['orderby']) && is_string($args['orderby']) ? trim($args['orderby']) : '';
            if ($orderbyStr === '') {
                $args['orderby'] = 'ID';
                $args['order'] = $order;
            } elseif (!preg_match('/\\bID\\b/i', $orderbyStr) && strtolower($orderbyStr) !== 'rand') {
                $args['orderby'] = $orderbyStr . ' ID';
            }
        }

        if ($min !== null && $max !== null) {
            $args['meta_query'][] = [
                'key'     => '_price',
                'value'   => [$min, $max],
                'compare' => 'BETWEEN',
                'type'    => 'DECIMAL(10,2)',
            ];
        }

        $tax_query = $args['tax_query'] ?? ['relation' => 'AND'];

        if ($cats) {
            $tax_query[] = [
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => array_map('intval', $cats),
            ];
        }

        foreach ($attrs as $tax => $ids) {
            $ids = array_filter(array_map('intval', (array) $ids));
            if ($ids) {
                $tax_query[] = [
                    'taxonomy' => $tax,
                    'field'    => 'term_id',
                    'terms'    => $ids,
                ];
            }
        }

        $args['tax_query'] = $tax_query;

        return $args;
    };
    add_filter('breakdance_woocommerce_get_products_query', $filter);

    $elements = $props['design']['products_list']['elements'] ?? [];

    WooActions::filterCatalog($elements)
        ->then(function () use ($props, $paged, $filter) {
            try {
                $queryArgs = $props['content']['content'] ?? [];

                if (!is_array($queryArgs)) {
                    $queryArgs = [];
                }

                // Sikrer at vi respekterer Breakdance sitt default-oppsett når props mangler
                $queryArgs = array_merge([
                    'show_products' => $queryArgs['show_products'] ?? 'woocommerce',
                ], $queryArgs);

                $query = \Breakdance\WooCommerce\getProducts($queryArgs);

                $markup = bdpf_render_catalog_markup($query, $paged);

                remove_filter('breakdance_woocommerce_get_products_query', $filter);

                wp_send_json_success([
                    'markup'      => $markup,
                    'found'       => $query->found_posts,
                    'page'        => $paged,
                    'max_pages'   => $query->max_num_pages,
                    'per_page'    => $query->get('posts_per_page'),
                ]);
            } catch (\Throwable $t) {
                remove_filter('breakdance_woocommerce_get_products_query', $filter);

                wp_send_json_error([
                    'message' => $t->getMessage(),
                ], 500);
            }
        });

    wp_die();
}

function bdpf_has_shop_element()
{
    check_ajax_referer('bdpf', 'nonce');

    $candidateIds = bdpf_get_candidate_template_ids();

    foreach ($candidateIds as $templateId) {
        $tree = get_tree($templateId);
        if ($tree === false) {
            continue;
        }

        $props = bdpf_find_shop_props_in_tree(bdpf_tree_to_array($tree));

        if ($props !== null) {
            $GLOBALS['bdpf_shop_properties'] = [
                'template_id' => $templateId,
                'properties' => $props,
            ];

            wp_send_json_success([
                'hasShop' => true,
                'template_id' => $templateId,
                'properties' => $props,
            ]);
        }
    }

    wp_send_json_success([
        'hasShop' => false,
        'template_id' => null,
    ]);
}

function bdpf_render_catalog_markup(\WP_Query $query, $currentPage = 1)
{
    $currentPage = max(1, (int) $currentPage);
    $oldWpQuery = $GLOBALS['wp_query'] ?? null;
    $oldPost    = $GLOBALS['post'] ?? null;

    $GLOBALS['wp_query'] = $query;

    $total     = (int) $query->found_posts;
    $perPage   = (int) ($query->get('posts_per_page') ?: get_option('posts_per_page'));
    $totalPages = max(1, (int) $query->max_num_pages);

    wc_setup_loop([
        'name'          => 'products',
        'is_shortcode'  => false,
        'is_search'     => false,
        'is_paginated'  => $totalPages > 1,
        'total'         => $total,
        'total_pages'   => $totalPages,
        'per_page'      => $perPage,
        'current_page'  => $currentPage,
    ]);

    ob_start();

    if ($query->have_posts()) {
        do_action('woocommerce_before_shop_loop');

        woocommerce_product_loop_start();

        while ($query->have_posts()) {
            $query->the_post();
            do_action('woocommerce_shop_loop');
            wc_get_template_part('content', 'product');
        }

        woocommerce_product_loop_end();

        do_action('woocommerce_after_shop_loop');
    } else {
        do_action('woocommerce_no_products_found');
    }

    $output = ob_get_clean();

    wp_reset_postdata();
    wc_reset_loop();

    if ($oldWpQuery !== null) {
        $GLOBALS['wp_query'] = $oldWpQuery;
    }

    if ($oldPost !== null) {
        $GLOBALS['post'] = $oldPost;
    }

    return '<div class="woocommerce breakdance-ajax-catalog">' . $output . '</div>';
}


add_action('save_post', function ($post_id, $post, $update) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;

    $tpl_types = ['breakdance_template', 'bd-template', 'breakdance-template'];

    if (isset($post->post_type) && in_array($post->post_type, $tpl_types, true)) {
        unset($GLOBALS['bdpf_shop_properties']);
    }
}, 10, 3);
add_action('after_switch_theme', function () {
    unset($GLOBALS['bdpf_shop_properties']);
});

/**
 * AJAX: Attributt-termer per kontekst (self-exclude per facet, inkl. variasjoner)
 */
add_action('wp_ajax_bdpf_get_attribute_terms',        'bdpf_get_attribute_terms');
add_action('wp_ajax_nopriv_bdpf_get_attribute_terms', 'bdpf_get_attribute_terms');

function bdpf_get_attribute_terms() {
    check_ajax_referer('bdpf', 'nonce');
	
	if (function_exists('bdpf_load_shop_properties_once')) {
        bdpf_load_shop_properties_once();
    }

    $cats  = array_filter(array_map('intval', (array) (json_decode(stripslashes((string)($_POST['categories'] ?? '[]')), true) ?: [])));
    $attrs = (array) (json_decode(stripslashes((string)($_POST['attributes'] ?? '[]')), true) ?: []);

    $supported = array_values(array_filter([
        taxonomy_exists('pa_materiale')  ? 'pa_materiale'  : null,
        taxonomy_exists('product_brand') ? 'product_brand' : null,
        taxonomy_exists('pa_farge')      ? 'pa_farge'      : null,
        taxonomy_exists('pa_bredde')     ? 'pa_bredde'     : null,
        taxonomy_exists('pa_lengde')     ? 'pa_lengde'     : null,
    ]));

    $out = array_fill_keys($supported, []);
    if (!$cats) wp_send_json($out);

    $base_tax = [];
    if ($cats) {
        $base_tax[] = ['taxonomy'=>'product_cat','field'=>'term_id','terms'=>$cats,'operator'=>'IN'];
    }

    foreach ($supported as $context_tax) {
        $tax_query = $base_tax ? array_merge(['relation'=>'AND'], $base_tax) : ['relation'=>'AND'];
        foreach ($attrs as $tx => $ids) {
            if ($tx === $context_tax) continue;
            if (!taxonomy_exists($tx)) continue;
            $ids = array_filter(array_map('intval', (array) $ids));
            if ($ids) $tax_query[] = ['taxonomy'=>$tx,'field'=>'term_id','terms'=>$ids,'operator'=>'IN'];
        }

        $q = new WP_Query([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            'tax_query'      => count($tax_query) > 1 ? $tax_query : [],
        ]);
        $product_ids = array_map('intval', (array) $q->posts);

        $variation_ids = get_posts([
            'post_type'       => 'product_variation',
            'post_parent__in' => $product_ids ?: [0],
            'fields'          => 'ids',
            'numberposts'     => -1,
            'no_found_rows'   => true,
        ]);
        $object_ids = array_values(array_unique(array_merge($product_ids, array_map('intval', (array) $variation_ids))));
        if (!$object_ids) { $out[$context_tax] = []; continue; }

        $term_ids = wp_get_object_terms($object_ids, $context_tax, ['fields'=>'ids']);
        $term_ids = array_values(array_unique(array_map('intval', (array) $term_ids)));
        if (!$term_ids) { $out[$context_tax] = []; continue; }

        $terms = get_terms([
            'taxonomy'   => $context_tax,
            'hide_empty' => true,
            'include'    => $term_ids,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]);

        $out[$context_tax] = array_map(function($t){
            return ['id'=>(int)$t->term_id,'name'=>$t->name];
        }, (array) $terms);
    }

    wp_send_json($out);
}


/**
 * AJAX: Prisgrenser for gjeldende kontekst (uten prisfilter)
 */
add_action('wp_ajax_bdpf_get_price_bounds',        'bdpf_get_price_bounds');
add_action('wp_ajax_nopriv_bdpf_get_price_bounds', 'bdpf_get_price_bounds');

function bdpf_get_price_bounds() {
    check_ajax_referer('bdpf', 'nonce');
	
	if (function_exists('bdpf_load_shop_properties_once')) {
        bdpf_load_shop_properties_once();
    }
	
    $cats  = array_filter(array_map('intval', (array) (json_decode(stripslashes((string)($_POST['categories'] ?? '[]')), true) ?: [])));
    $attrs = (array) (json_decode(stripslashes((string)($_POST['attributes'] ?? '[]')), true) ?: []);

    $tax_query = [];
    if ($cats) $tax_query[] = ['taxonomy'=>'product_cat','field'=>'term_id','terms'=>$cats,'operator'=>'IN'];
    foreach ($attrs as $tax => $ids) {
        if (!taxonomy_exists($tax)) continue;
        $ids = array_filter(array_map('intval', (array) $ids));
        if ($ids) $tax_query[] = ['taxonomy'=>$tax,'field'=>'term_id','terms'=>$ids,'operator'=>'IN'];
    }
    if (count($tax_query) > 1) $tax_query = array_merge(['relation'=>'AND'], $tax_query);

    $q = new WP_Query([
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'fields'         => 'ids',
        'posts_per_page' => -1,
        'no_found_rows'  => true,
        'tax_query'      => $tax_query,
    ]);

    $min = null; $max = null;
    foreach ((array) $q->posts as $pid) {
        $p = wc_get_product($pid); if (!$p) continue;
        if ($p->is_type('variable')) {
            $lo = (float) $p->get_variation_price('min', true);
            $hi = (float) $p->get_variation_price('max', true);
        } else {
            $price = (float) $p->get_price();
            $lo = $price; $hi = $price;
        }
        if ($lo > 0 || $hi > 0) {
            if ($min === null || $lo < $min) $min = $lo;
            if ($max === null || $hi > $max) $max = $hi;
        }
    }

    wp_send_json([
        'price_min' => (float) ($min ?? 0.0),
        'price_max' => (float) ($max ?? 0.0),
    ]);
}

/**
 * @return int[]
 */
function bdpf_get_candidate_template_ids()
{
    static $ids = null;

    if ($ids !== null) {
        return $ids;
    }

    $ids = [];

    $postTypes = ['breakdance_template', 'bd-template', 'breakdance-template'];

    foreach ($postTypes as $postType) {
        $posts = get_posts([
            'post_type'      => $postType,
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'nopaging'       => true,
        ]);

        if ($posts) {
            $ids = array_merge($ids, array_map('intval', $posts));
        }
    }

    $shopPageId = function_exists('wc_get_page_id') ? wc_get_page_id('shop') : 0;
    if ($shopPageId) {
        $ids[] = (int) $shopPageId;
    }

    $ids = array_values(array_unique(array_filter($ids)));

    return $ids;
}

/**
 * @param array|false $tree
 * @return array|null
 */
function bdpf_find_shop_props_in_tree($tree)
{
    if (!is_array($tree)) {
        return null;
    }

    $tree = bdpf_tree_to_array($tree);

    $children = $tree['root']['children'] ?? [];
    if (!is_array($children) || !$children) {
        return null;
    }

    $props = null;

    bdpf_walk_tree($children, function (array $node) use (&$props) {
        if ($props !== null) {
            return false;
        }

        if (($node['data']['type'] ?? '') === 'EssentialElements\\Wooshoppage') {
            $props = $node['data']['properties'] ?? [];
            return false;
        }

        return null;
    });

    return $props;
}

function bdpf_tree_contains_shop_element($tree)
{
    if (!is_array($tree)) {
        return false;
    }

    $tree = bdpf_tree_to_array($tree);

    $children = $tree['root']['children'] ?? [];
    if (!is_array($children) || !$children) {
        return false;
    }

    $found = false;

    bdpf_walk_tree($children, function (array $node) use (&$found) {
        if ($found) {
            return false;
        }

        if (($node['data']['type'] ?? '') === 'EssentialElements\\Wooshoppage') {
            $found = true;
            return false;
        }

        return null;
    });

    return $found;
}

function bdpf_tree_to_array($value)
{
    if (is_array($value)) {
        foreach ($value as $key => $item) {
            $value[$key] = bdpf_tree_to_array($item);
        }

        return $value;
    }

    if ($value instanceof \ArrayObject) {
        return bdpf_tree_to_array($value->getArrayCopy());
    }

    if ($value instanceof \Traversable) {
        return bdpf_tree_to_array(iterator_to_array($value, true));
    }

    return $value;
}

function bdpf_walk_tree(array $nodes, callable $callback)
{
    foreach ($nodes as $node) {
        if (!is_array($node)) {
            continue;
        }

        $result = $callback($node);
        if ($result === false) {
            return false;
        }

        if (!empty($node['children']) && is_array($node['children'])) {
            $childResult = bdpf_walk_tree($node['children'], $callback);
            if ($childResult === false) {
                return false;
            }
        }
    }

    return true;
}
