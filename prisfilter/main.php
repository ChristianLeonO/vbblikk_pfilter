<?php
/** HTML (med PHP + JS) – lim inn i Breakdance Code-element (PHP aktivert) */
?>
<div class="Frame79"
     data-ajax="<?php echo esc_url( admin_url('admin-ajax.php') ); ?>"
     data-nonce="<?php echo esc_attr( wp_create_nonce('bdpf') ); ?>">
  <div class="Frame45">
    <div class="Frame46">

      <!-- Kategorier -->
      <div class="Frame81">
        <div class="collapsible">
          <div class="Kategorier">
            <div class="Frame35 filter-header">
              <div class="KategorierTitle">Kategorier</div>
              <div class="Vector">
                <svg width="16" height="8" viewBox="0 0 16 8" xmlns="http://www.w3.org/2000/svg"><path d="M14.6924 0L16 1.1898L8.8734 7.67036C8.75921 7.77483 8.62342 7.85773 8.47384 7.9143C8.32426 7.97088 8.16386 8 8.00185 8C7.83984 8 7.67944 7.97088 7.52986 7.9143C7.38029 7.85773 7.24449 7.77483 7.1303 7.67036L0 1.1898L1.30763 0.00112152L8 6.08359L14.6924 0Z" fill="#6E82D0" fill-opacity="0.3"/></svg>
              </div>
            </div>
          </div>
          <div class="filter-content">
            <?php if (function_exists('bdpf_category_tree_html')) echo bdpf_category_tree_html(); ?>
          </div>
        </div>
      </div>

      <!-- Materiale -->
      <div class="Frame82">
        <div class="collapsible">
          <div class="Kategorier">
            <div class="Frame35 filter-header">
              <div class="KategorierTitle">Materiale</div>
              <div class="Vector">
                <svg width="16" height="8" viewBox="0 0 16 8" xmlns="http://www.w3.org/2000/svg"><path d="M14.6924 0L16 1.1898L8.8734 7.67036C8.75921 7.77483 8.62342 7.85773 8.47384 7.9143C8.32426 7.97088 8.16386 8 8.00185 8C7.83984 8 7.67944 7.97088 7.52986 7.9143C7.38029 7.85773 7.24449 7.77483 7.1303 7.67036L0 1.1898L1.30763 0.00112152L8 6.08359L14.6924 0Z" fill="#6E82D0" fill-opacity="0.3"/></svg>
              </div>
            </div>
          </div>
          <div class="filter-content">
            <?php if (function_exists('bdpf_attribute_checklist')) echo bdpf_attribute_checklist('pa_materiale'); ?>
          </div>
        </div>
      </div>

	      <div class="Farge">
	        <div class="collapsible">
	          <div class="Kategorier">
	            <div class="Frame35 filter-header">
	              <div class="KategorierTitle">Farge</div>
              <div class="Vector">
                <svg width="16" height="8" viewBox="0 0 16 8" xmlns="http://www.w3.org/2000/svg"><path d="M14.6924 0L16 1.1898L8.8734 7.67036C8.75921 7.77483 8.62342 7.85773 8.47384 7.9143C8.32426 7.97088 8.16386 8 8.00185 8C7.83984 8 7.67944 7.97088 7.52986 7.9143C7.38029 7.85773 7.24449 7.77483 7.1303 7.67036L0 1.1898L1.30763 0.00112152L8 6.08359L14.6924 0Z" fill="#6E82D0" fill-opacity="0.3"/></svg>
              </div>
            </div>
          </div>
	          <div class="filter-content">
	            <?php if (function_exists('bdpf_attribute_checklist')) echo bdpf_attribute_checklist('pa_farge'); ?>
	          </div>
	        </div>
	      </div>

	      <!-- Lengde -->
	      <div class="Lengde">
	        <div class="collapsible">
	          <div class="Kategorier">
	            <div class="Frame35 filter-header">
	              <div class="KategorierTitle">Lengde</div>
	              <div class="Vector">
	                <svg width="16" height="8" viewBox="0 0 16 8" xmlns="http://www.w3.org/2000/svg"><path d="M14.6924 0L16 1.1898L8.8734 7.67036C8.75921 7.77483 8.62342 7.85773 8.47384 7.9143C8.32426 7.97088 8.16386 8 8.00185 8C7.83984 8 7.67944 7.97088 7.52986 7.9143C7.38029 7.85773 7.24449 7.77483 7.1303 7.67036L0 1.1898L1.30763 0.00112152L8 6.08359L14.6924 0Z" fill="#6E82D0" fill-opacity="0.3"/></svg>
	              </div>
	            </div>
	          </div>
	          <div class="filter-content">
	            <?php if (function_exists('bdpf_attribute_checklist')) echo bdpf_attribute_checklist('pa_lengde'); ?>
	          </div>
	        </div>
	      </div>

	      <button type="button" class="BDPFReset">Nullstill filter</button>

    </div>
  </div>
</div>

<!-- ✅ Stil som gjør SVG grå når valgt (robust mot ulike markup-varianter) -->
<style>
  /* Hvis markup er input + label + svg inne i label */
  input[type="checkbox"][name^="filter_\"]:checked + label svg *,
  input[type="checkbox"][name^="filter_\"]:checked + label svg {
    fill: #9CA3AF !important; /* Tailwind gray-400 */
    stroke: #9CA3AF !important;
    opacity: 0.7;
  }

  /* Hvis vi legger klassen .is-selected på containeren (li eller item) */
  .bdpf-attribute li.is-selected svg *,
  .bdpf-attribute-item.is-selected svg *,
  .bdpf-attribute li.is-selected svg,
  .bdpf-attribute-item.is-selected svg {
    fill: #9CA3AF !important;
    stroke: #9CA3AF !important;
    opacity: 0.7;
  }

  /* Fallback for individuelle SVG-elementer */
  .bdpf-attribute li.is-selected svg path,
  .bdpf-attribute li.is-selected svg line,
  .bdpf-attribute li.is-selected svg rect,
  .bdpf-attribute li.is-selected svg circle,
  .bdpf-attribute li.is-selected svg polygon,
  input[type="checkbox"][name^="filter_\"]:checked + label svg path,
  input[type="checkbox"][name^="filter_\"]:checked + label svg line,
  input[type="checkbox"][name^="filter_\"]:checked + label svg rect,
  input[type="checkbox"][name^="filter_\"]:checked + label svg circle,
  input[type="checkbox"][name^="filter_\"]:checked + label svg polygon {
    fill: #9CA3AF !important;
    stroke: #9CA3AF !important;
  }
</style>

<script>
(function() {
  function ready(fn){
    if (document.readyState !== 'loading') { fn(); }
    else { document.addEventListener('DOMContentLoaded', fn); }
  }

  ready(function () {
    const params = new URLSearchParams(window.location.search);

    const checkboxes = document.querySelectorAll('input[type="checkbox"][name^="filter_\"]');

    const urlValuesByKey = {};
    params.forEach((val, key) => {
      if (key.startsWith('filter_')) {
        if (!urlValuesByKey[key]) urlValuesByKey[key] = new Set();
        val.split(',').forEach(v => urlValuesByKey[key].add(decodeURIComponent(v).toLowerCase()));
      }
    });

    function containerOf(cb){
      // Prøv å finne nærmeste li / item som omkranser svg/enheten
      return cb.closest('li') || cb.closest('.bdpf-attribute-item') || cb.parentElement;
    }

    function setSelectedState(cb){
      const container = containerOf(cb);
      if (container) container.classList.toggle('is-selected', cb.checked);
    }

    // 3) Preselect fra URL og sett .is-selected (beholder tidligere logikk)
    checkboxes.forEach(cb => {
      const name = cb.name || '';
      const m = name.match(/^filter_[^\[]+/);
      if (m) {
        const key = m[0];
        const selectedSet = urlValuesByKey[key];
        if (selectedSet) {
          const val = (cb.value || '').toLowerCase();
          if (selectedSet.has(val)) {
            cb.checked = true;
          }
        }
      }
      setSelectedState(cb);
    });

    // 4) Oppdater i sanntid når brukeren klikker
    document.addEventListener('change', function(e){
      const cb = e.target;
      if (cb && cb.matches('input[type="checkbox"][name^="filter_\"]')) {
        setSelectedState(cb);
      }
    }, true);
  });
})();
</script>
