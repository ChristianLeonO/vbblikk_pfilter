(function () {
  function init() {
    var root = document.querySelector('.Frame79') || document;

    // --- Config ---
    var cfgRoot = document.querySelector('.Frame79');
    window.BDPF = {
      ajax:  cfgRoot ? cfgRoot.dataset.ajax  : '',
      nonce: cfgRoot ? cfgRoot.dataset.nonce : '',
      targets: { products: '#bdpf-loop' },   // hele wrapperen rundt ul.products + paginering
      page: { current: 1, max: 1 },
      price: { touched:false, initialized:false, step:1, boundsKey:'' },
      ready: false
    };

    // --- Hjelpere ---
    function formatKr(n){ var x=Number(n); if(!isFinite(x))return'0'; try{return new Intl.NumberFormat('no-NO').format(Math.round(x));}catch(e){return String(Math.round(x));} }
    function clampPair(a,b,step){ var vMin=Number(a),vMax=Number(b); if(!isFinite(vMin))vMin=0; if(!isFinite(vMax))vMax=vMin+step; if(vMin>vMax){var t=vMin; vMin=vMax; vMax=t;} if(vMax-vMin<step)vMin=vMax-step; return {vMin:vMin,vMax:vMax}; }
    function enhancePagination(container,current,max){
      var page = Number(current) || 1;
      var maxPages = Number(max) || null;
      container.querySelectorAll('.woocommerce-pagination a.page-numbers').forEach(function(a){
        var pageNum = NaN;
        var txt = (a.textContent || '').trim();
        var parsed = parseInt(txt,10);
        if(!isNaN(parsed)){
          pageNum = parsed;
        } else if (a.classList.contains('next')) {
          pageNum = page + 1;
        } else if (a.classList.contains('prev')) {
          pageNum = Math.max(1, page - 1);
        } else {
          var href = a.getAttribute('href') || '';
          var m = href.match(/\/page\/(\d+)/);
          if(m){ pageNum = parseInt(m[1],10); }
        }
        if(!isNaN(pageNum)){
          if(maxPages) pageNum = Math.min(Math.max(1,pageNum), maxPages);
          a.dataset.page = String(pageNum);
        }
      });
    }
    function getCatsAndAttrs(){
      var cats=[], attrs={};
      document.querySelectorAll('input.bdpf-term[type="checkbox"]:checked').forEach(function(el){
        var tax=el.dataset.tax||'product_cat'; var id=parseInt(el.value,10); if(isNaN(id))return;
        if (tax==='product_cat') cats.push(id); else (attrs[tax]||(attrs[tax]=[])).push(id);
      });
      document.querySelectorAll('.bdpf-parent-leaf.is-checked').forEach(function(el){
        var id=parseInt(el.dataset.termId,10); if(!isNaN(id)) cats.push(id);
      });
      return {cats, attrs};
    }
    function deselectAllCategories(exceptEl){
      document.querySelectorAll('input.bdpf-term[data-tax="product_cat"]').forEach(function(cb){ if(cb!==exceptEl) cb.checked=false; });
      document.querySelectorAll('.bdpf-parent-leaf.is-checked').forEach(function(btn){
        if(btn!==exceptEl){ btn.classList.remove('is-checked'); btn.setAttribute('aria-pressed','false'); }
      });
    }
    function onFacetChangeResetPage(){ BDPF.page.current=1; BDPF.price.touched=false; BDPF.price.initialized=false; }
    function getOrderby(){
      var map = {
        'default': '',
        'price-asc': 'price',
        'price-desc': 'price-desc',
        'name-asc': 'title',
        'name-desc': 'title-desc',
        'sku': 'sku'
      };

      function orderbyFromLocalStorage(){
        try {
          var mode = localStorage.getItem('bd-sort') || 'default';
          return Object.prototype.hasOwnProperty.call(map, mode) ? map[mode] : '';
        } catch(e) {
          return '';
        }
      }

      // Hvis vi har custom sortering (sortering.php), skal den styre sorteringen i AJAX-kallene.
      // Woo sin <select.orderby> kan bli re-renderet med default-verdi etter AJAX og ellers "overstyre"
      // valgt sort på side 2+ hvis vi leser select først.
      var hasCustomSortUi = !!document.querySelector('.frame37');
      var lsOrderby = orderbyFromLocalStorage();
      if (hasCustomSortUi) {
        return lsOrderby;
      }

      // Når custom UI er borte/ikke i DOM (eller vi er på en annen mal),
      // behold likevel aktivt valg fra localStorage hvis det er satt (ikke default).
      if (lsOrderby) return lsOrderby;

      var sel=document.querySelector('#bdpf-loop select.orderby');
      if (sel && typeof sel.value === 'string') return sel.value;

      return lsOrderby;
    }

	    // --- Wrappere vi oppdaterer ofte ---
	    var matWrap = document.querySelector('.Frame82 .filter-content');  // Materiale
	    var vmWrap  = document.querySelector('.Frame83 .filter-content');  // Varemerke
	    var fgWrap  = document.querySelector('.Farge .filter-content');    // Farge
	    var lenWrap = document.querySelector('.Lengde .filter-content');   // Lengde
	    var sizeWrap= document.getElementById('bdpf-size-content');        // Størrelse

    // --- Placeholder/buildere (uendret visuell logikk) ---
    function buildAttrPlaceholderRow(text){ return '<li class="bdpf-subcat-item"><span class="txt Underkategori">'+text+'</span></li>'; }
    function buildAttrPlaceholderList(text){ return '<ul class="subcategory-list no-indent bdpf-attr-list">'+buildAttrPlaceholderRow(text)+'</ul>'; }
	    function fillSizePlaceholder(text){
	      if(!sizeWrap) return;
	      ['pa_bredde','pa_lengde'].forEach(function(tax){
	        var body=sizeWrap.querySelector('[data-tax="'+tax+'"] .js-size-body');
	        if(body) body.innerHTML=buildAttrPlaceholderRow(text);
	      });
	    }
	    // Unike SVG-id'er per checkbox (hindrer id-kollisjoner på tvers av <svg>)
	    var svgSeq = 0;
	    function buildAttrList(tax,terms,selectedIds){
	      selectedIds=(selectedIds||[]).map(Number);
	      if(!terms||!terms.length) return buildAttrPlaceholderList('Ingen valg');
	      var h='<ul class="subcategory-list no-indent bdpf-attr-list" data-tax="'+tax+'">';
	      terms.forEach(function(t){
	        svgSeq++;
	        var gradId = 'bdpfChkGrad-' + svgSeq;
	        var shadowId = 'bdpfChkShadow-' + svgSeq;
	        var c=selectedIds.indexOf(Number(t.id))!==-1?' checked':'';
	        h+='<li class="bdpf-subcat-item"><label class="category-checkbox" data-filter="attribute" data-term-id="'+t.id+'">'
	          +'<input type="checkbox" class="bdpf-term" data-tax="'+tax+'" value="'+t.id+'"'+c+'>'
	          +'<span class="fake-box" aria-hidden="true"><span class="svg svg--unchecked"><svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><rect x="0.125" y="0.125" width="15.75" height="15.75" rx="3.875" fill="none" stroke="black" stroke-width="0.25"/></svg></span><span class="svg svg--checked"><svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><defs><linearGradient id="'+gradId+'" x1="0" y1="0" x2="16" y2="16"><stop offset="0" stop-color="#5A5A5A"/><stop offset="1" stop-color="#2F2F2F"/></linearGradient><filter id="'+shadowId+'" x="-20%" y="-20%" width="140%" height="140%"><feDropShadow dx="0" dy="1" stdDeviation="0.4" flood-color="#000" flood-opacity="0.25"/></filter></defs><rect x="0.125" y="0.125" width="15.75" height="15.75" rx="3.875" fill="url(#'+gradId+')" stroke="#333" stroke-width="0.25" filter="url(#'+shadowId+')"/><rect x="0.375" y="0.375" width="15.25" height="15.25" rx="3.5" fill="none" stroke="#FFFFFF" stroke-opacity="0.12"/></svg></span></span>'
	          +'<span class="txt Underkategori">'+t.name+'</span></label></li>';
	      });
	      return h+'</ul>';
	    }
	    function buildAttrItems(tax,terms,selectedIds){
	      selectedIds=(selectedIds||[]).map(Number);
	      if(!terms||!terms.length) return buildAttrPlaceholderRow('Ingen valg');
	      var h='';
	      terms.forEach(function(t){
	        svgSeq++;
	        var gradId = 'bdpfChkGrad-' + svgSeq;
	        var shadowId = 'bdpfChkShadow-' + svgSeq;
	        var c=selectedIds.indexOf(Number(t.id))!==-1?' checked':'';
	        h+='<li class="bdpf-subcat-item"><label class="category-checkbox" data-filter="attribute" data-term-id="'+t.id+'">'
	          +'<input type="checkbox" class="bdpf-term" data-tax="'+tax+'" value="'+t.id+'"'+c+'>'
	          +'<span class="fake-box" aria-hidden="true"><span class="svg svg--unchecked"><svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><rect x="0.125" y="0.125" width="15.75" height="15.75" rx="3.875" fill="none" stroke="black" stroke-width="0.25"/></svg></span><span class="svg svg--checked"><svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><defs><linearGradient id="'+gradId+'" x1="0" y1="0" x2="16" y2="16"><stop offset="0" stop-color="#5A5A5A"/><stop offset="1" stop-color="#2F2F2F"/></linearGradient><filter id="'+shadowId+'" x="-20%" y="-20%" width="140%" height="140%"><feDropShadow dx="0" dy="1" stdDeviation="0.4" flood-color="#000" flood-opacity="0.25"/></filter></defs><rect x="0.125" y="0.125" width="15.75" height="15.75" rx="3.875" fill="url(#'+gradId+')" stroke="#333" stroke-width="0.25" filter="url(#'+shadowId+')"/><rect x="0.375" y="0.375" width="15.25" height="15.25" rx="3.5" fill="none" stroke="#FFFFFF" stroke-opacity="0.12"/></svg></span></span>'
	          +'<span class="txt Underkategori">'+t.name+'</span></label></li>';
	      });
	      return h;
	    }

    // Første placeholders
	    (function(){
	      var note='Vennligst velg en kategori først';
	      if(matWrap) matWrap.innerHTML=buildAttrPlaceholderList(note);
	      if(vmWrap)  vmWrap.innerHTML =buildAttrPlaceholderList(note);
	      if(fgWrap)  fgWrap.innerHTML =buildAttrPlaceholderList(note);
	      if(lenWrap) lenWrap.innerHTML=buildAttrPlaceholderList(note);
	      fillSizePlaceholder(note);
	    })();

    /* -------------------------
       Collapsibles + reset
    ------------------------- */
    document.querySelectorAll('.collapsible .filter-header').forEach(function (h) {
      h.addEventListener('click', function () {
        var w=h.closest('.collapsible'); if(w) w.classList.toggle('is-open');
      });
    });
    root.addEventListener('click', function (e) {
      var btn=e.target.closest('.sub-collapsible'); if(!btn) return;
      e.preventDefault();
      var li=btn.closest('li'); if(!li) return;
      var expanded=btn.getAttribute('aria-expanded')==='true';
      btn.setAttribute('aria-expanded', expanded?'false':'true');
      li.classList.toggle('is-open');
    });
    var resetBtn=document.querySelector('.BDPFReset');
    if(resetBtn){ resetBtn.addEventListener('click', function(e){ e.preventDefault();
      document.querySelectorAll('input.bdpf-term[type="checkbox"]').forEach(function(cb){ cb.checked=false; });
      document.querySelectorAll('.bdpf-parent-leaf.is-checked').forEach(function(btn){ btn.classList.remove('is-checked'); btn.setAttribute('aria-pressed','false'); });
      BDPF.page.current=1; BDPF.price.touched=false; BDPF.price.initialized=false; BDPF.price.boundsKey='';
      applyFilters(1);
    });}

    /* -------------------------
       Pris-slider
    ------------------------- */
    var sliderWrap=document.querySelector('.Pris .bdpf-price-slider');
    var rMin=sliderWrap?sliderWrap.querySelector('.bdpf-range--min'):null;
    var rMax=sliderWrap?sliderWrap.querySelector('.bdpf-range--max'):null;
    var minEl=document.querySelector('input[name="bdpf_min_price"]');
    var maxEl=document.querySelector('input[name="bdpf_max_price"]');
    var textEl=document.querySelector('.PrisText');

    function syncPriceText(vMin,vMax){ if(textEl) textEl.textContent='Pris: kr '+formatKr(vMin)+' - kr '+formatKr(vMax); }

    function fetchPriceBounds(cats,attrs){
      if(!sliderWrap||!rMin||!rMax) return Promise.resolve(null);
      var key=JSON.stringify({cats:cats.slice().sort(), attrs:Object.keys(attrs).sort().reduce(function(o,k){o[k]=attrs[k].slice().sort();return o;},{})});
      if(BDPF.price.boundsKey===key) return Promise.resolve(null);
      BDPF.price.boundsKey=key;

      var fd=new FormData();
      fd.append('action','bdpf_get_price_bounds');
      fd.append('nonce',BDPF.nonce||'');
      fd.append('categories',JSON.stringify(cats));
      fd.append('attributes',JSON.stringify(attrs));

      return fetch(BDPF.ajax||'',{method:'POST',credentials:'same-origin',body:fd})
        .then(function(r){return r.json();})
        .then(function(j){
          if(!j||typeof j.price_min==='undefined'||typeof j.price_max==='undefined') return null;
          var bMin=Number(j.price_min), bMax=Number(j.price_max);
          if(!isFinite(bMin)||!isFinite(bMax)) return null;

          rMin.min=bMin; rMin.max=bMax; rMax.min=bMin; rMax.max=bMax;

          if(!BDPF.price.initialized||!BDPF.price.touched){
            var pair=clampPair(bMin,bMax,BDPF.price.step);
            rMin.value=pair.vMin; rMax.value=pair.vMax;
            if(minEl) minEl.value=pair.vMin; if(maxEl) maxEl.value=pair.vMax;
            syncPriceText(pair.vMin,pair.vMax); BDPF.price.initialized=true;
          } else {
            var curMin=Math.max(bMin,Math.min(bMax,Number(rMin.value)));
            var curMax=Math.max(bMin,Math.min(bMax,Number(rMax.value)));
            var pair2=clampPair(curMin,curMax,BDPF.price.step);
            rMin.value=pair2.vMin; rMax.value=pair2.vMax;
            if(minEl) minEl.value=pair2.vMin; if(maxEl) maxEl.value=pair2.vMax;
            syncPriceText(pair2.vMin,pair2.vMax);
          }
          return {bMin,bMax};
        })
        .catch(function(err){ console.error('[BDPF price bounds]',err); return null; });
    }
    function onMinInput(){
      var vMin=Number(rMin.value), vMax=Number(rMax.value);
      if(vMin>=vMax-BDPF.price.step) rMin.value=vMax-BDPF.price.step;
      if(minEl)minEl.value=rMin.value; if(maxEl)maxEl.value=rMax.value;
      syncPriceText(rMin.value,rMax.value); BDPF.price.touched=true; BDPF.page.current=1;
      clearTimeout(onMinInput._t); onMinInput._t=setTimeout(function(){ applyFilters(1); },300);
    }
    function onMaxInput(){
      var vMin=Number(rMin.value), vMax=Number(rMax.value);
      if(vMax<=vMin+BDPF.price.step) rMax.value=vMin+BDPF.price.step;
      if(minEl)minEl.value=rMin.value; if(maxEl)maxEl.value=rMax.value;
      syncPriceText(rMin.value,rMax.value); BDPF.price.touched=true; BDPF.page.current=1;
      clearTimeout(onMaxInput._t); onMaxInput._t=setTimeout(function(){ applyFilters(1); },300);
    }
    if(rMin&&rMax){ rMin.addEventListener('input',onMinInput); rMax.addEventListener('input',onMaxInput); }

    /* -------------------------
       Facet-hendelser
    ------------------------- */
    root.addEventListener('change', function(e){
      if(!e.target.matches('input.bdpf-term[type="checkbox"]')) return;
      if(e.target.dataset.tax==='product_cat' && e.target.checked){ deselectAllCategories(e.target); }
      onFacetChangeResetPage(); applyFilters(1);
    });
    root.addEventListener('click', function(e){
      var lab=e.target.closest('.bdpf-subcat-item .category-checkbox'); if(!lab) return;
      var input=lab.querySelector('input[type="checkbox"]');
      if(input && !input.disabled){ e.preventDefault(); input.checked=!input.checked; onFacetChangeResetPage(); input.dispatchEvent(new Event('change',{bubbles:true})); }
    });
    root.addEventListener('click', function(e){
      var leaf=e.target.closest('.bdpf-parent-leaf'); if(!leaf) return; e.preventDefault();
      var willSelect=!leaf.classList.contains('is-checked');
      if(willSelect){ deselectAllCategories(leaf); leaf.classList.add('is-checked'); leaf.setAttribute('aria-pressed','true'); }
      else { leaf.classList.remove('is-checked'); leaf.setAttribute('aria-pressed','false'); }
      onFacetChangeResetPage(); applyFilters(1);
    });

    // Sortering (Breakdance/Woo select inne i #bdpf-loop)
    document.addEventListener('change', function(e){
      var sel=e.target.closest('#bdpf-loop select.orderby'); if(!sel) return;
      e.preventDefault(); BDPF.page.current=1; applyFilters(1);
    });

    // Sortering (custom dropdown fra sortering.php)
    document.addEventListener('bdpf:sort-changed', function(){
      if(!BDPF.ready) return;
      BDPF.page.current=1;
      applyFilters(1);
    });

    /* -------------------------
       Oppdater attributt-lister (via egen AJAX)
    ------------------------- */
	    function updateAttributesForCats(cats,attrs){
	      if(!cats.length){
	        var note='Vennligst velg en kategori først';
	        if(matWrap) matWrap.innerHTML=buildAttrPlaceholderList(note);
	        if(vmWrap)  vmWrap.innerHTML =buildAttrPlaceholderList(note);
	        if(fgWrap)  fgWrap.innerHTML =buildAttrPlaceholderList(note);
	        if(lenWrap) lenWrap.innerHTML=buildAttrPlaceholderList(note);
	        fillSizePlaceholder(note);
	        return;
	      }
      var fd2=new FormData();
      fd2.append('action','bdpf_get_attribute_terms');
      fd2.append('nonce',BDPF.nonce||'');
      fd2.append('categories',JSON.stringify(cats));
      fd2.append('attributes',JSON.stringify(attrs));

      fetch(BDPF.ajax||'',{method:'POST',credentials:'same-origin',body:fd2})
        .then(function(r){return r.json();})
	        .then(function(json){
	          var selM=(attrs['pa_materiale']||[]).map(Number),
	              selB=(attrs['product_brand']||[]).map(Number),
	              selF=(attrs['pa_farge']||[]).map(Number),
	              selW=(attrs['pa_bredde']||[]).map(Number),
	              selL=(attrs['pa_lengde']||[]).map(Number);
	          if(matWrap) matWrap.innerHTML=buildAttrList('pa_materiale',json['pa_materiale']||[],selM);
	          if(vmWrap)  vmWrap.innerHTML =buildAttrList('product_brand',json['product_brand']||[],selB);
	          if(fgWrap)  fgWrap.innerHTML =buildAttrList('pa_farge',json['pa_farge']||[],selF);
	          if(lenWrap) lenWrap.innerHTML=buildAttrList('pa_lengde',json['pa_lengde']||[],selL);
	          if(sizeWrap){
	            var bre=sizeWrap.querySelector('[data-tax="pa_bredde"] .js-size-body');
	            if(bre) bre.innerHTML=buildAttrItems('pa_bredde',json['pa_bredde']||[],selW);
	          }
	        })
	        .catch(function(err){ console.error('[BDPF attr]',err); });
	    }

    /* -------------------------
       Kjerne: applyFilters() – matcher ny backend
    ------------------------- */
    function applyFilters(paged){
      if(!BDPF.ready) return;
      if(typeof paged==='number') BDPF.page.current=paged;

      var s=getCatsAndAttrs(); var cats=s.cats, attrs=s.attrs;

      updateAttributesForCats(cats,attrs);
      fetchPriceBounds(cats,attrs);

      var container=document.querySelector(BDPF.targets.products);
      if(container) container.classList.add('bdpf-is-loading');

      var fd=new FormData();
      fd.append('action','bdpf_filter_products'); // <- NY backend handler
      fd.append('nonce',BDPF.nonce||'');
      fd.append('categories',JSON.stringify(cats));
      fd.append('attributes',JSON.stringify(attrs));
      fd.append('paged',String(BDPF.page.current));
      fd.append('orderby', getOrderby());

      if(BDPF.price.touched && rMin && rMax){
        fd.append('min_price', rMin.value);
        fd.append('max_price', rMax.value);
      } else {
        fd.append('min_price','');
        fd.append('max_price','');
      }

      fetch(BDPF.ajax||'',{method:'POST',credentials:'same-origin',body:fd})
        .then(function(r){return r.json();})
        .then(function (json) {
          var payload = (json && typeof json === 'object' && 'data' in json) ? json.data : json;
          if (!payload) payload = {};

          if (container) {
            if (typeof payload.markup === 'string') {
              container.innerHTML = payload.markup;
            } else if (typeof payload.html === 'string') {
              var list = container.querySelector('ul.products');
              if (list) list.innerHTML = payload.html;
            }
            container.classList.remove('bdpf-is-loading');
          }
          if (typeof payload.page !== 'undefined') {
            BDPF.page.current = Number(payload.page) || 1;
          }
          if (typeof payload.max_pages !== 'undefined') {
            BDPF.page.max = Number(payload.max_pages) || 1;
          }
          if (container) {
            enhancePagination(container, BDPF.page.current, BDPF.page.max);
          }
          try {
            document.dispatchEvent(new CustomEvent('bdpf:products-updated', { detail: { page: BDPF.page.current, max_pages: BDPF.page.max } }));
          } catch(e) {}
        })
        .catch(function(err){
          console.error('[BDPF products]', err);
          if (container) container.classList.remove('bdpf-is-loading');
        });
    }

    // Paginering (delegert – virker også på nyinnsatt HTML)
    document.addEventListener('click', function(e){
      var a=e.target.closest('#bdpf-loop .woocommerce-pagination a.page-numbers');
      if(!a) return;
      e.preventDefault();
      var dp=a.getAttribute('data-page');
      var page = dp ? parseInt(dp,10) : NaN;
      if(isNaN(page)){
        var isPrev=a.classList.contains('prev'), isNext=a.classList.contains('next');
        if(isPrev) page=Math.max(1,(BDPF.page.current||1)-1);
        else if(isNext) page=(BDPF.page.current||1)+1;
      }
      if(!page||page<1||isNaN(page)) return;
      applyFilters(page);
    });

    (function firstSync(){
      var ca=getCatsAndAttrs();
      fetchPriceBounds(ca.cats, ca.attrs);
      BDPF.ready=true;
      var container=document.querySelector(BDPF.targets.products);
      if(container){ enhancePagination(container, BDPF.page.current || 1, null); }

      // Hvis custom sortering er aktiv (lagret i localStorage), hent side 1 fra server i samme rekkefølge
      // slik at paginering blir konsistent på tvers av sider.
      try {
        var hasCustomSortUi = !!document.querySelector('.frame37');
        var mode = hasCustomSortUi ? (localStorage.getItem('bd-sort') || 'default') : 'default';
        if (hasCustomSortUi && mode && mode !== 'default') {
          applyFilters(1);
        }
      } catch(e) {}
    })();
  }

  if(document.readyState==='loading'){
    document.addEventListener('DOMContentLoaded', init, {once:true});
  } else {
    init();
  }
})();
