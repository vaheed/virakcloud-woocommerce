(function($){
  'use strict';
  
  function fillAndSelect(el, items, sel) {
    el.innerHTML = '';
    var o = document.createElement('option');
    o.value = '';
    o.textContent = '— Select —';
    el.appendChild(o);
    
    var found = false;
    (items || []).forEach(function(it) {
      var id = it.id || it.uuid || it.slug || '';
      var name = it.name || it.displayName || id;
      if (!id) return;
      
      var op = document.createElement('option');
      op.value = id;
      op.textContent = name;
      if (sel && id == sel) {
        op.selected = true;
        found = true;
      }
      el.appendChild(op);
    });
    return found;
  }
  
  function load(what, zone) {
    return $.post(VCWCFG.ajax, {
      action: 'vcw_cfg_load',
      _ajax_nonce: VCWCFG.nonce,
      nonce: VCWCFG.nonce,
      what: what,
      zone_id: zone || ''
    });
  }
  
  function disableSubmit(dis) {
    $('form.cart button[type="submit"]').prop('disabled', !!dis);
  }
  
  $(document).on('ready wc-enhanced-select-init', function() {
    var z = $('#vcw_cfg_zone'), n = $('#vcw_cfg_net'), i = $('#vcw_cfg_img');
    if (!z.length) return;
    
    var d = window.VCWCFG_PROD || {};
    
    // Load zones first
    load('zones').done(function(res) {
      var ok = false;
      if (res && res.success) {
        ok = fillAndSelect(z[0], res.data.items, d.default_zone);
      }
      if (ok) {
        z.trigger('change');
      } else {
        disableSubmit(false); // Allow submit only after full cascade
      }
    });
    
    // Handle zone change
    z.on('change', function() {
      disableSubmit(true);
      var zid = $(this).val();
      
      if (!zid) {
        n[0].innerHTML = '';
        i[0].innerHTML = '';
        disableSubmit(false);
        return;
      }
      
      // Load networks and images simultaneously
      $.when(load('nets', zid), load('imgs', zid)).done(function(nets, imgs) {
        var nOk = nets && nets[0] && nets[0].success && 
                  fillAndSelect(n[0], nets[0].data.items, d.default_net);
        var iOk = imgs && imgs[0] && imgs[0].success && 
                  fillAndSelect(i[0], imgs[0].data.items, d.default_img);
        disableSubmit(false);
      });
    });
  });
  
  // Require selections before add to cart
  $(document).on('click', 'form.cart button[type="submit"]', function(e) {
    var $z = $('#vcw_cfg_zone'), $n = $('#vcw_cfg_net'), $i = $('#vcw_cfg_img');
    if ($z.length && (!$.trim($z.val()) || !$.trim($n.val()) || !$.trim($i.val()))) {
      e.preventDefault();
      alert('Please select Zone, Network, and VM Image before adding to cart.');
      return false;
    }
  });
  
})(jQuery);
