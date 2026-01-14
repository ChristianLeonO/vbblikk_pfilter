/**
 * BDPF Sort Integration
 * Kobler custom frontend-sortering med BDPF AJAX-backend
 */
(function(){
    'use strict';
    
    const LS_KEY = 'bd-sort';
    
    // Map frontend verdier til WooCommerce orderby verdier
    const sortMap = {
        'default': '',          // Tom streng = bruk WooCommerce standard
        'price-asc': 'price',   // WooCommerce: pris lav til høy
        'price-desc': 'price-desc', // WooCommerce: pris høy til lav
        'name-asc': 'title',    // WooCommerce: tittel A-Å
        'name-desc': 'title-desc', // WooCommerce: tittel Å-A
        'sku': 'sku'            // Custom: varenummer
    };
    
    /**
     * Trigger BDPF AJAX filtrering med ny sortering
     */
    function triggerBdpfFilter(sortValue) {
        // Hvis bdpfFilterProducts finnes (global function fra BDPF)
        if (typeof window.bdpfFilterProducts === 'function') {
            window.bdpfFilterProducts();
            return;
        }
        
        // Alternativt: Trigger custom event som BDPF lytter på
        const event = new CustomEvent('bdpf:sort-changed', {
            detail: { orderby: sortMap[sortValue] || '' }
        });
        document.dispatchEvent(event);
        
        // Fallback: Trigger en re-filter manuelt
        triggerManualFilter(sortValue);
    }
    
    /**
     * Manuell AJAX-filtrering hvis BDPF ikke har expose funksjon
     */
    function triggerManualFilter(sortValue) {
        const orderby = sortMap[sortValue] || '';
        
        // Finn alle aktive filtre
        const categories = getActiveCategories();
        const attributes = getActiveAttributes();
        const priceRange = getActivePriceRange();
        
        // Send AJAX request til backend
        fetch(bdpfData.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'bdpf_filter_products',
                nonce: bdpfData.nonce,
                orderby: orderby,
                categories: JSON.stringify(categories),
                attributes: JSON.stringify(attributes),
                min_price: priceRange.min || '',
                max_price: priceRange.max || '',
                paged: 1
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.markup) {
                updateProductList(data.data.markup);
            }
        })
        .catch(error => {
            console.error('BDPF Sort Error:', error);
        });
    }
    
    /**
     * Hent aktive kategorier fra checkboxes
     */
    function getActiveCategories() {
        const cats = [];
        document.querySelectorAll('input.bdpf-term[data-tax="product_cat"]:checked').forEach(cb => {
            cats.push(parseInt(cb.value));
        });
        // Inkluder også parent-leaf knapper som er aktive
        document.querySelectorAll('.bdpf-parent-leaf[aria-pressed="true"]').forEach(btn => {
            const tid = btn.getAttribute('data-term-id');
            if (tid) cats.push(parseInt(tid));
        });
        return cats;
    }
    
    /**
     * Hent aktive attributter fra checkboxes
     */
    function getActiveAttributes() {
        const attrs = {};
        document.querySelectorAll('input.bdpf-term:checked').forEach(cb => {
            const tax = cb.getAttribute('data-tax');
            if (!tax || tax === 'product_cat') return;
            if (!attrs[tax]) attrs[tax] = [];
            attrs[tax].push(parseInt(cb.value));
        });
        return attrs;
    }
    
    /**
     * Hent aktiv prisrange (hvis du har pricefilter)
     */
    function getActivePriceRange() {
        return {
            min: document.querySelector('input[name="min_price"]')?.value || '',
            max: document.querySelector('input[name="max_price"]')?.value || ''
        };
    }
    
    /**
     * Oppdater produktlisten med ny HTML fra backend
     */
    function updateProductList(markup) {
        const productsContainer = document.querySelector('ul.products');
        if (!productsContainer) return;
        
        // Parse markup og erstatt innholdet
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = markup;
        const newProducts = tempDiv.querySelector('ul.products');
        
        if (newProducts) {
            productsContainer.innerHTML = newProducts.innerHTML;
            
            // Trigger event for at andre scripts kan reagere
            const event = new CustomEvent('bdpf:products-updated');
            document.dispatchEvent(event);
        }
    }
    
    /**
     * Overvåk localStorage endringer for sortering
     */
    function watchSortChanges() {
        let lastSort = localStorage.getItem(LS_KEY) || 'default';
        
        // Poll localStorage hvert 100ms (siden vi ikke får direkte events)
        setInterval(() => {
            const currentSort = localStorage.getItem(LS_KEY) || 'default';
            if (currentSort !== lastSort) {
                lastSort = currentSort;
                console.log('BDPF: Sort changed to', currentSort, '→', sortMap[currentSort]);
                triggerBdpfFilter(currentSort);
            }
        }, 100);
    }
    
    /**
     * Alternativ: Intercept BD_SORT.apply direkte
     */
    function interceptSortFunction() {
        // Vent til BD_SORT er definert
        const checkInterval = setInterval(() => {
            if (window.BD_SORT && typeof window.BD_SORT.apply === 'function') {
                clearInterval(checkInterval);
                
                // Wrap original apply function
                const originalApply = window.BD_SORT.apply;
                window.BD_SORT.apply = function(mode) {
                    // Kjør original funksjon
                    originalApply.call(this, mode);
                    
                    // Trigger BDPF filter
                    console.log('BDPF: Intercepted sort change to', mode, '→', sortMap[mode]);
                    triggerBdpfFilter(mode);
                };
                
                console.log('BDPF: Sort integration ready (intercepted)');
            }
        }, 100);
        
        // Stop etter 5 sekunder hvis ikke funnet
        setTimeout(() => clearInterval(checkInterval), 5000);
    }
    
    // Start monitoring når DOM er klar
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    function init() {
        // Bruk intercept-metoden (mest pålitelig)
        interceptSortFunction();
        
        // Fallback: Poll localStorage
        // watchSortChanges();
        
        console.log('BDPF: Sort integration initialized');
    }
})();
