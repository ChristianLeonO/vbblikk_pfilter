<?php
// === BD Prisfilter – helpers (ingen fallbacks) ===

if (!defined('ABSPATH')) exit;

/**
 * Kategoritre: foreldre -> (barne-liste) ELLER leaf-knapp
 * Returnerer HTML som matcher klassene JS/CSS forventer.
 */
function bdpf_category_tree_html(): string {
    $parents = get_terms([
        'taxonomy'   => 'product_cat',
        'hide_empty' => true,
        'parent'     => 0,
        'orderby'    => 'menu_order',
        'order'      => 'ASC',
    ]);

    if (empty($parents) || is_wp_error($parents)) {
        return '<ul class="bdpf-category-list"><li class="bdpf-empty">Ingen kategorier</li></ul>';
    }

    $skip = ['uncategorized','ukategorisert','gaveartikler'];

    ob_start(); ?>
    <ul class="bdpf-category-list">
      <?php foreach ($parents as $parent):
        if (in_array(strtolower($parent->name), $skip, true)) continue;

        $children = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'parent'     => (int) $parent->term_id,
            'orderby'    => 'menu_order',
            'order'      => 'ASC',
        ]);
        $has_children = (!empty($children) && !is_wp_error($children));
      ?>
        <li class="bdpf-cat-item<?php echo $has_children ? '' : ' is-leaf'; ?>">
          <?php if ($has_children): ?>
            <button class="sub-collapsible" type="button" aria-expanded="false">
              <span class="bdpf-caret">
                <svg width="8" height="10" viewBox="0 0 8 10" aria-hidden="true">
                  <path d="M0 9.1075V0.8936C0 0.6223 0.0873 0.4055 0.2619 0.2433 0.4359 0.0811 0.6391 0 0.8717 0c.073 0 .1492.0103.2286.031 .0787.0199.1542.0505.2265.0918L7.5996 4.2513c.1331.0959.2333.2058.3005.3297C7.9667 4.704 8 4.8437 8 5s-.0333.296-.1004.4191c-.0666.1232-.1668.2331-.3006.3297L1.3269 9.8772c-.0723.0405-.1485.0711-.2287.0917C1.0181 9.9897 0.9419 10 0.8696 10c-.2333 0-.4366-.0811-.6098-.2433C0.0866 9.5945 0 9.3781 0 9.1075Z" fill="#6E82D0" fill-opacity="0.24"/>
                </svg>
              </span>
              <span class="bdpf-cat-label"><?php echo esc_html($parent->name); ?></span>
            </button>

            <ul class="bdpf-subcategory-list">
              <?php foreach ($children as $child): ?>
                <li class="bdpf-subcat-item">
                  <label class="category-checkbox" data-filter="category" data-term-id="<?php echo (int) $child->term_id; ?>">
                    <input type="checkbox" class="bdpf-term" data-tax="product_cat" value="<?php echo (int) $child->term_id; ?>">
                    <span class="fake-box" aria-hidden="true">
                      <span class="svg svg--unchecked">
                        <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">
                          <rect x="0.125" y="0.125" width="15.75" height="15.75" rx="3.875" fill="none" stroke="black" stroke-width="0.25"/>
                        </svg>
                      </span>
                      <span class="svg svg--checked">
                        <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">
                          <defs>
                            <linearGradient id="bdpfChkGrad" x1="0" y1="0" x2="16" y2="16">
                              <stop offset="0" stop-color="#5A5A5A"/><stop offset="1" stop-color="#2F2F2F"/>
                            </linearGradient>
                            <filter id="bdpfChkShadow" x="-20%" y="-20%" width="140%" height="140%">
                              <feDropShadow dx="0" dy="1" stdDeviation="0.4" flood-color="#000" flood-opacity="0.25"/>
                            </filter>
                          </defs>
                          <rect x="0.125" y="0.125" width="15.75" height="15.75" rx="3.875"
                                fill="url(#bdpfChkGrad)" stroke="#333" stroke-width="0.25" filter="url(#bdpfChkShadow)"/>
                          <rect x="0.375" y="0.375" width="15.25" height="15.25" rx="3.5"
                                fill="none" stroke="#FFFFFF" stroke-opacity="0.12"/>
                        </svg>
                      </span>
                    </span>
                    <span class="txt Underkategori"><?php echo esc_html($child->name); ?></span>
                  </label>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <button type="button"
                    class="bdpf-parent-leaf bdpf-term"
                    data-tax="product_cat"
                    data-term-id="<?php echo (int) $parent->term_id; ?>"
                    aria-pressed="false">
              <span class="txt bdpf-parent-leaf-label"><?php echo esc_html($parent->name); ?></span>
            </button>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php
    return ob_get_clean();
}

/**
 * Generisk attributt-sjekkliste (pa_materiale, product_brand, pa_farge, …)
 * Returnerer <ul class="bdpf-attr-list" data-tax="..."> med samme item-markup.
 */
function bdpf_attribute_checklist(string $taxonomy): string {
    if (!$taxonomy || !taxonomy_exists($taxonomy)) {
        return '<ul class="bdpf-subcategory-list bdpf-attr-list" data-tax="'.esc_attr($taxonomy).'"><li class="bdpf-empty">Ugyldig taksonomi</li></ul>';
    }

    $terms = get_terms([
        'taxonomy'   => $taxonomy,
        'hide_empty' => true,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ]);

    if (empty($terms) || is_wp_error($terms)) {
        return '<ul class="bdpf-subcategory-list bdpf-attr-list" data-tax="'.esc_attr($taxonomy).'"><li class="bdpf-empty">Ingen valg</li></ul>';
    }

    ob_start(); ?>
    <ul class="bdpf-subcategory-list bdpf-attr-list" data-tax="<?php echo esc_attr($taxonomy); ?>">
      <?php foreach ($terms as $t): ?>
        <li class="bdpf-subcat-item">
          <label class="category-checkbox" data-filter="attribute" data-term-id="<?php echo (int) $t->term_id; ?>">
            <input type="checkbox" class="bdpf-term" data-tax="<?php echo esc_attr($taxonomy); ?>" value="<?php echo (int) $t->term_id; ?>">
            <span class="fake-box" aria-hidden="true">
              <span class="svg svg--unchecked">
                <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">
                  <rect x="0.125" y="0.125" width="15.75" height="15.75" rx="3.875" fill="none" stroke="black" stroke-width="0.25"/>
                </svg>
              </span>
              <span class="svg svg--checked">
                <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">
                  <use href="#bdpfChkIcon" />
                </svg>
              </span>
            </span>
            <span class="txt Underkategori"><?php echo esc_html($t->name); ?></span>
          </label>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php
    return ob_get_clean();
}