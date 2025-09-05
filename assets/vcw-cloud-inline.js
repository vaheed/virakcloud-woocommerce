
/* Virak Cloud Woo – Inline actions (no modals) */
(function(){
  function qs(s, r){ return (r||document).querySelector(s); }
  function ce(t, c){ var e=document.createElement(t); if(c) e.className=c; return e; }

  function post(url, data){
    return fetch(url, {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
      credentials: 'same-origin',
      body: new URLSearchParams(data).toString()
    }).then(r=>r.json()).catch(e=>({error:String(e)}));
  }

  function renderResponse(container, payload){
    if (!container) return;
    let html = "";
    
    // Check if we're in admin context
    const isAdmin = window.location.href.includes('/wp-admin/') || document.body.classList.contains('wp-admin');
    
    // Handle success response
    if (payload && payload.success && payload.data) {
      const data = payload.data;
      
      if (data.credentials) {
        // Render credentials (username and password) and include a copy
        // button next to each value. Public IP is intentionally omitted.
        const c = data.credentials;
        const username = c.username || '';
        const password = c.password || '';
        html += '<div class="vcw-result-card vcw-result--success">';
        html += '<h4>🔑 Instance Credentials</h4>';
        html += '<div class="vcw-kv">';
        // Username row with copy button
        html += '<div>Username</div><div><code>' + (username || '—') + '</code>';
        html += ' <button type="button" class="vcw-copy-btn" data-copy="' + username.replace(/"/g,'&quot;') + '" title="Copy username">Copy</button></div>';
        // Password row with copy button
        html += '<div>Password</div><div><code>' + (password || '—') + '</code>';
        html += ' <button type="button" class="vcw-copy-btn" data-copy="' + password.replace(/"/g,'&quot;') + '" title="Copy password">Copy</button></div>';
        html += '</div>';
        if (c.found_in && c.found_in.length){
          html += `<div class="vcw-muted">Fields: ${c.found_in.join(', ')}</div>`;
        }
        html += '</div>';
      } else if (Array.isArray(data.snapshots)) {
        // Render snapshot list when snapshots array is returned
        html += '<div class="vcw-result-card vcw-result--info">';
        html += '<h4>📸 Snapshots</h4>';
        // Create snapshot button
        html += `<button class="vcw-btn vcw-act vcw-btn--primary" data-act="snapshot_create">Create Snapshot</button>`;
        if (data.snapshots.length > 0) {
          html += '<ul class="vcw-snapshot-list" style="list-style:none;padding-left:0;margin-top:12px;">';
          data.snapshots.forEach(function(snap){
            const sid = snap && (snap.snapshot_id || snap.id || snap.uuid || snap.name || '');
            const sname = snap && (snap.name || snap.display_name || sid);
            html += `<li class="vcw-snapshot-item" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;gap:8px;" data-snapshot-id="${sid}">`;
            html += `<span>${sname||sid}</span>`;
            html += `<div style="flex-shrink:0;display:flex;gap:6px;">`;
            html += `<button class="vcw-btn vcw-act vcw-btn--secondary" data-act="snapshot_revert" data-snapshot-id="${sid}">Revert</button>`;
            html += `<button class="vcw-btn vcw-act vcw-btn--danger" data-act="snapshot_delete" data-snapshot-id="${sid}">Delete</button>`;
            html += `</div>`;
            html += '</li>';
          });
          html += '</ul>';
        } else {
          html += '<p>No snapshots found.</p>';
        }
        html += '</div>';
      } else if (data.body_pretty) {
        html += '<div class="vcw-result-card vcw-result--info">';
        html += '<h4>📋 Response Data</h4>';
        html += `<pre class="vcw-code">${data.body_pretty.replace(/[&<>]/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[s]))}</pre>`;
        html += '</div>';
      } else if (data.body_raw) {
        html += '<div class="vcw-result-card vcw-result--info">';
        html += '<h4>📋 Raw Response</h4>';
        html += `<pre class="vcw-code">${String(data.body_raw).replace(/[&<>]/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[s]))}</pre>`;
        html += '</div>';
      } else if (data.vnc_url) {
        html += '<div class="vcw-result-card vcw-result--success">';
        html += '<h4>🖥️ Console Access</h4>';
        html += `<div class="vcw-kv"><div>VNC URL</div><div><a href="${data.vnc_url}" target="_blank" rel="noopener" class="vcw-btn vcw-btn--primary">Open Console</a></div></div>`;
        html += '</div>';
      } else if (Array.isArray(data.images)) {
        // Render image selection list for rebuild. When rebuilding an instance we
        // first fetch the list of available VM images. Each image is shown
        // with its name and a rebuild button that submits the selected image.
        html += '<div class="vcw-result-card vcw-result--warning">';
        html += '<h4>🛠️ Rebuild Instance</h4>';
        html += '<div class="vcw-rebuild-warning">';
        html += '<p><strong>⚠️ Warning:</strong> Rebuilding will replace the current disk with the selected image and may cause data loss. Make sure to backup important data before proceeding.</p>';
        html += '</div>';
        if (data.images.length > 0) {
          html += '<p>Select a VM image below to rebuild this instance:</p>';
          html += '<div class="vcw-image-grid">';
          data.images.forEach(function(img){
            const imgId = img && (img.id || img.uuid || img.slug || '');
            const imgName = img && (img.name || img.displayName || img.label || imgId);
            const imgDesc = img && (img.description || img.desc || '');
            const imgSize = img && (img.size || img.disk_size || '');
            const imgOs = img && (img.os || img.operating_system || '');
            
            html += '<div class="vcw-image-card">';
            html += '<div class="vcw-image-header">';
            html += '<h5 class="vcw-image-name">' + imgName + '</h5>';
            html += '</div>';
            html += '<div class="vcw-image-details">';
            if (imgDesc) {
              html += '<p class="vcw-image-desc">' + imgDesc + '</p>';
            }
            if (imgOs) {
              html += '<div class="vcw-image-spec"><strong>OS:</strong> ' + imgOs + '</div>';
            }
            if (imgSize) {
              html += '<div class="vcw-image-spec"><strong>Size:</strong> ' + imgSize + '</div>';
            }
            html += '</div>';
            html += '<div class="vcw-image-actions">';
            html += '<button class="vcw-btn vcw-act vcw-btn--danger vcw-btn--rebuild" data-act="rebuild" data-image-id="' + imgId + '" data-image-name="' + imgName.replace(/"/g,'&quot;') + '">Rebuild with this image</button>';
            html += '</div>';
            html += '</div>';
          });
          html += '</div>';
        } else {
          html += '<p>No VM images available for this zone.</p>';
        }
        html += '</div>';
      } else if (data.details) {
        // Professional instance details display
        html += '<div class="vcw-result-card vcw-result--info">';
        html += '<h4>📊 Instance Details</h4>';
        
        // Basic Information Section
        html += '<div class="vcw-details-section">';
        html += '<h5>Basic Information</h5>';
        html += '<div class="vcw-details-grid">';
        
        if (data.details.status) {
          html += `<div class="vcw-detail-item"><span class="vcw-detail-label">Current Status</span><span class="vcw-detail-value vcw-status-badge" data-status="${data.details.status.toLowerCase()}">${data.details.status}</span></div>`;
        }
        if (data.details.name) {
          html += `<div class="vcw-detail-item"><span class="vcw-detail-label">Instance Name</span><span class="vcw-detail-value">${data.details.name}</span></div>`;
        }
        if (data.details.id && isAdmin) {
          html += `<div class="vcw-detail-item"><span class="vcw-detail-label">Instance ID</span><span class="vcw-detail-value"><code>${data.details.id}</code></span></div>`;
        }
        html += '</div>';
        html += '</div>';
        
        // Service Information Section
        if (data.details.service_offering || data.details.category) {
          html += '<div class="vcw-details-section">';
          html += '<h5>Service Information</h5>';
          html += '<div class="vcw-details-grid">';
          
          if (data.details.service_offering && typeof data.details.service_offering === 'string' && data.details.service_offering !== 'Unknown') {
            html += `<div class="vcw-detail-item"><span class="vcw-detail-label">Service Offering</span><span class="vcw-detail-value">${data.details.service_offering}</span></div>`;
          }
          if (data.details.category && typeof data.details.category === 'string' && data.details.category !== 'Unknown') {
            html += `<div class="vcw-detail-item"><span class="vcw-detail-label">Category</span><span class="vcw-detail-value">${data.details.category}</span></div>`;
          }
          if (data.details.description && typeof data.details.description === 'string') {
            html += `<div class="vcw-detail-item vcw-detail-full"><span class="vcw-detail-label">Description</span><span class="vcw-detail-value">${data.details.description}</span></div>`;
          }
          html += '</div>';
          html += '</div>';
        }
        
        // Hardware Specifications Section
        if (data.details.cpu_cores || data.details.memory || data.details.disk_size) {
          html += '<div class="vcw-details-section">';
          html += '<h5>Hardware Specifications</h5>';
          html += '<div class="vcw-details-grid">';
          
          if (data.details.cpu_cores) {
            html += `<div class="vcw-detail-item"><span class="vcw-detail-label">CPU Cores</span><span class="vcw-detail-value">${data.details.cpu_cores} cores</span></div>`;
          }
          if (data.details.memory) {
            const memoryValue = data.details.memory >= 1024 ? 
              `${(data.details.memory / 1024).toFixed(1)} GB` : 
              `${data.details.memory} MB`;
            html += `<div class="vcw-detail-item"><span class="vcw-detail-label">Memory</span><span class="vcw-detail-value">${memoryValue}</span></div>`;
          }
          if (data.details.cpu_speed) {
            html += `<div class="vcw-detail-item"><span class="vcw-detail-label">CPU Speed</span><span class="vcw-detail-value">${data.details.cpu_speed} MHz</span></div>`;
          }
          if (data.details.disk_size) {
            html += `<div class="vcw-detail-item"><span class="vcw-detail-label">Disk Size</span><span class="vcw-detail-value">${data.details.disk_size} GB</span></div>`;
          }
          if (data.details.network_rate) {
            html += `<div class="vcw-detail-item"><span class="vcw-detail-label">Network Rate</span><span class="vcw-detail-value">${data.details.network_rate} Mbps</span></div>`;
          }
          if (data.details.disk_iops) {
            html += `<div class="vcw-detail-item"><span class="vcw-detail-label">Disk IOPS</span><span class="vcw-detail-value">${data.details.disk_iops}</span></div>`;
          }
          html += '</div>';
          html += '</div>';
        }
        
        // Pricing Information Section (Admin only)
        if (isAdmin && (data.details.hourly_price_up || data.details.hourly_price_down)) {
          html += '<div class="vcw-details-section">';
          html += '<h5>Pricing Information</h5>';
          html += '<div class="vcw-details-grid">';
          
          if (data.details.hourly_price_up) {
            html += `<div class="vcw-detail-item"><span class="vcw-detail-label">Hourly Price (Up)</span><span class="vcw-detail-value vcw-price">${data.details.hourly_price_up}</span></div>`;
          }
          if (data.details.hourly_price_down) {
            html += `<div class="vcw-detail-item"><span class="vcw-detail-label">Hourly Price (Down)</span><span class="vcw-detail-value vcw-price">${data.details.hourly_price_down}</span></div>`;
          }
          html += '</div>';
          html += '</div>';
        }
        
        // Network Information Section
        if (data.details.ip_address || data.details.public_ip || data.details.network || data.details.zone_name) {
          html += '<div class="vcw-details-section">';
          html += '<h5>Network Information</h5>';
          html += '<div class="vcw-details-grid">';
          
          if (data.details.ip_address && typeof data.details.ip_address === 'string') {
            html += `<div class="vcw-detail-item"><span class="vcw-detail-label">IP Address</span><span class="vcw-detail-value">${data.details.ip_address}</span></div>`;
          }
          if (data.details.public_ip && typeof data.details.public_ip === 'string') {
            html += `<div class="vcw-detail-item"><span class="vcw-detail-label">Public IP</span><span class="vcw-detail-value">${data.details.public_ip}</span></div>`;
          }
          if (data.details.network && typeof data.details.network === 'string') {
            html += `<div class="vcw-detail-item"><span class="vcw-detail-label">Network</span><span class="vcw-detail-value">${data.details.network}</span></div>`;
          }
          if (data.details.zone_name && typeof data.details.zone_name === 'string') {
            if (isAdmin) {
              html += `<div class="vcw-detail-item"><span class="vcw-detail-label">Zone</span><span class="vcw-detail-value">${data.details.zone_name} <small>(ID: ${data.details.zone})</small></span></div>`;
            } else {
              html += `<div class="vcw-detail-item"><span class="vcw-detail-label">Zone</span><span class="vcw-detail-value">${data.details.zone_name}</span></div>`;
            }
          }
          html += '</div>';
          html += '</div>';
        }
        
        // VM Image Information Section
        if (data.details.vm_image) {
          html += '<div class="vcw-details-section">';
          html += '<h5>VM Image Information</h5>';
          html += '<div class="vcw-details-grid">';
          
          if (data.details.vm_image.display_text && typeof data.details.vm_image.display_text === 'string') {
            html += `<div class="vcw-detail-item"><span class="vcw-detail-label">Image Name</span><span class="vcw-detail-value">${data.details.vm_image.display_text}</span></div>`;
          }
          if (data.details.vm_image.name_orginal && typeof data.details.vm_image.name_orginal === 'string') {
            html += `<div class="vcw-detail-item"><span class="vcw-detail-label">Original Name</span><span class="vcw-detail-value">${data.details.vm_image.name_orginal}</span></div>`;
          }
          if (data.details.vm_image.os_type && typeof data.details.vm_image.os_type === 'string') {
            html += `<div class="vcw-detail-item"><span class="vcw-detail-label">OS Type</span><span class="vcw-detail-value">${data.details.vm_image.os_type}</span></div>`;
          }
          if (data.details.vm_image.os_name && typeof data.details.vm_image.os_name === 'string') {
            html += `<div class="vcw-detail-item"><span class="vcw-detail-label">OS Name</span><span class="vcw-detail-value">${data.details.vm_image.os_name}</span></div>`;
          }
          if (data.details.vm_image.ready_to_use_app !== undefined) {
            const readyText = data.details.vm_image.ready_to_use_app ? 'Ready App' : 'Standard Image';
            const readyClass = data.details.vm_image.ready_to_use_app ? 'ready-app' : 'standard-image';
            html += `<div class="vcw-detail-item"><span class="vcw-detail-label">App Type</span><span class="vcw-detail-value vcw-app-type ${readyClass}">${readyText}</span></div>`;
          }
          html += '</div>';
          html += '</div>';
        }
        
        
        html += '</div>';
      } else if (data.status !== undefined) {
        html += '<div class="vcw-result-card vcw-result--success">';
        html += '<h4>📊 Instance Status</h4>';
        // Show the primary status of the instance using a badge
        html += `<div class="vcw-kv"><div>Current Status</div><div><span class="vcw-status-badge" data-status="${data.status.toLowerCase()}">${data.status}</span></div></div>`;
        // If the API includes a boolean running flag, display it
        if (data.is_running !== undefined) {
          const runningState = data.is_running ? 'Running' : 'Stopped';
          html += `<div class="vcw-kv"><div>Running State</div><div><span class="vcw-status-badge" data-status="${data.is_running ? 'running' : 'stopped'}">${runningState}</span></div></div>`;
        }
        html += '</div>';
      } else {
        html += '<div class="vcw-result-card vcw-result--success">';
        html += '<h4>✅ Action Completed</h4>';
        html += '<p>Your request has been processed successfully.</p>';
        html += '</div>';
      }
    } else if (payload && payload.data && payload.data.message) {
      // Handle error response
      html += '<div class="vcw-result-card vcw-result--error">';
      html += '<h4>❌ Error Occurred</h4>';
      html += `<p>${payload.data.message}</p>`;
      html += '</div>';
    } else if (payload && payload.error) {
      // Fallback error handling
      html += '<div class="vcw-result-card vcw-result--error">';
      html += '<h4>❌ Error Occurred</h4>';
      html += `<p>${payload.error}</p>`;
      html += '</div>';
    } else {
      html += '<div class="vcw-result-card vcw-result--warning">';
      html += '<h4>⚠️ No Data Received</h4>';
      html += '<p>The action completed but no response data was received.</p>';
      html += '</div>';
    }
    
    container.innerHTML = html;
    container.closest('.vcw-card').scrollIntoView({behavior:'smooth', block:'nearest'});
  }

  // Auto-refresh status for all instances on page load
  function refreshAllStatuses() {
    const instances = document.querySelectorAll('[data-vcw-instance]');
    instances.forEach(card => {
      const zone = card.getAttribute('data-zone');
      const id = card.getAttribute('data-id');
      const nonce = card.getAttribute('data-nonce');
      if (zone && id && nonce) {
        // Refresh status silently
        post(vcwInline.ajax_url, { action:'vcw_instance_action', _ajax_nonce:nonce, act:'refresh_status', zone, id })
          .then(payload => {
            if (payload && payload.success && payload.data) {
              updateInstanceStatus(card, payload.data);
            }
          })
          .catch(err => console.log('Status refresh failed:', err));
      }
    });
  }

  // Update instance status and buttons based on API response
  function updateInstanceStatus(card, data) {
    if (!data.status) return;
    
    // Update status badge
    const statusBadge = card.querySelector('.vcw-status-badge');
    if (statusBadge) {
      statusBadge.textContent = data.status;
      statusBadge.setAttribute('data-status', data.status.toLowerCase());
    }
    
    // Update action buttons based on running state
    const actionsContainer = card.querySelector('.vcw-actions');
    if (actionsContainer && data.is_running !== undefined) {
      const startBtn = actionsContainer.querySelector('[data-act="start"]');
      const stopBtn = actionsContainer.querySelector('[data-act="stop"]');
      const rebootBtn = actionsContainer.querySelector('[data-act="reboot"]');
      
      if (data.is_running) {
        // Instance is running - show stop/reboot buttons
        if (startBtn) startBtn.style.display = 'none';
        if (stopBtn) stopBtn.style.display = 'inline-block';
        if (rebootBtn) rebootBtn.style.display = 'inline-block';
      } else {
        // Instance is stopped - show start button
        if (startBtn) startBtn.style.display = 'inline-block';
        if (stopBtn) stopBtn.style.display = 'none';
        if (rebootBtn) rebootBtn.style.display = 'none';
      }
    }
  }

  // Refresh statuses when page loads
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', refreshAllStatuses);
  } else {
    refreshAllStatuses();
  }

  document.addEventListener('click', function(ev){
    const btn = ev.target.closest('.vcw-act');
    if (!btn) return;
    const card = btn.closest('[data-vcw-instance]');
    if (!card) return;
    const zone = card.getAttribute('data-zone');
    const id = card.getAttribute('data-id');
    const act = btn.getAttribute('data-act');
    const nonce = card.getAttribute('data-nonce');
    const target = card.querySelector('.vcw-inline');
    
    // Build request payload and handle confirmations/prompts for specific actions
    let requestData = { action: 'vcw_instance_action', _ajax_nonce: nonce, act: act, zone: zone, id: id };
    // Confirm instance deletion
    if (act === 'delete') {
      const instanceName = card.getAttribute('data-name') || 'this instance';
      if (!confirm(`Are you sure you want to delete "${instanceName}"? This action cannot be undone and will permanently remove the instance and all its data.`)) {
        return;
      }
      // Add instance name to request data for API
      requestData.name = instanceName;
    }
    // Snapshot creation prompt
    if (act === 'snapshot_create') {
      const snapName = prompt('Enter a name for the new snapshot', '');
      if (snapName === null) {
        return;
      }
      requestData.name = snapName.trim();
    } else if (act === 'snapshot_revert' || act === 'snapshot_delete') {
      // For snapshot revert/delete, attach the snapshot id and show confirmation
      const sid = btn.getAttribute('data-snapshot-id');
      if (!sid) {
        alert('Snapshot ID missing.');
        return;
      }
      const confirmMessage = act === 'snapshot_revert'
        ? 'Are you sure you want to revert to this snapshot? This will restore the VM to the selected snapshot state.'
        : 'Are you sure you want to delete this snapshot? This action cannot be undone.';
      if (!confirm(confirmMessage)) {
        return;
      }
      requestData.snapshot_id = sid;
    }

    // Handle rebuild actions. When listing images we fetch and display a
    // selection list instead of immediately calling the API. When
    // rebuilding, attach the selected image ID and confirm with the user.
    if (act === 'rebuild') {
      const imgId = btn.getAttribute('data-image-id');
      const imgName = btn.getAttribute('data-image-name') || 'selected image';
      if (!imgId) {
        alert('Image ID missing.');
        return;
      }
      const confirmMessage = `⚠️ WARNING: Rebuilding will replace the current disk with "${imgName}" and may cause data loss.\n\nThis action cannot be undone. Make sure you have backed up any important data.\n\nAre you sure you want to continue?`;
      if (!confirm(confirmMessage)) {
        return;
      }
      requestData.vm_image_id = imgId;
    }
    
    btn.disabled = true;
    btn.classList.add('is-busy');
    post(vcwInline.ajax_url, requestData)
      .then(payload => {
        renderResponse(target, payload);
        // If a snapshot action occurred, refresh the snapshot list after a short delay
        const snapshotActs = ['snapshot_create', 'snapshot_delete', 'snapshot_revert'];
        if (snapshotActs.includes(act)) {
          setTimeout(() => {
            post(vcwInline.ajax_url, { action: 'vcw_instance_action', _ajax_nonce: nonce, act: 'snapshot_list', zone: zone, id: id })
              .then(listPayload => {
                renderResponse(target, listPayload);
              });
          }, 500);
        } else if (act !== 'refresh_status' && act !== 'rebuild_list_images') {
          // For most actions (except snapshot and image listing) refresh the instance status to update buttons
          setTimeout(() => {
            post(vcwInline.ajax_url, { action: 'vcw_instance_action', _ajax_nonce: nonce, act: 'refresh_status', zone: zone, id: id })
              .then(refreshPayload => {
                if (refreshPayload && refreshPayload.success && refreshPayload.data) {
                  updateInstanceStatus(card, refreshPayload.data);
                }
              })
              .catch(err => console.log('Auto-refresh failed:', err));
          }, 1000);
        }
      })
      .finally(() => {
        btn.disabled = false;
        btn.classList.remove('is-busy');
      });
  });

  // Global click handler for copy buttons. This runs after the instance
  // action handler and intercepts clicks on any element with the
  // `.vcw-copy-btn` class. When clicked, the button's `data-copy`
  // attribute is copied to the clipboard. The button label briefly
  // changes to indicate success.
  document.addEventListener('click', function(ev){
    const copyBtn = ev.target.closest('.vcw-copy-btn');
    if (!copyBtn) return;
    ev.preventDefault();
    ev.stopPropagation();
    const text = copyBtn.getAttribute('data-copy') || '';
    if (!navigator.clipboard) {
      // Fallback for browsers without clipboard API support
      const textarea = document.createElement('textarea');
      textarea.value = text;
      textarea.style.position = 'fixed';
      textarea.style.opacity = '0';
      document.body.appendChild(textarea);
      textarea.select();
      try { document.execCommand('copy'); } catch (err) {}
      document.body.removeChild(textarea);
    } else {
      navigator.clipboard.writeText(text).catch(() => {});
    }
    const original = copyBtn.textContent;
    copyBtn.textContent = 'Copied!';
    setTimeout(() => { copyBtn.textContent = original; }, 1500);
  });
})();
