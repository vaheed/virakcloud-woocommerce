(function($) {
    'use strict';
    
    // Wizard state management
    let wizardState = {
        currentStep: 1,
        totalSteps: 5,
        selectedZone: '',
        selectedServices: [],
        selectedNetworks: [],
        selectedImages: []
    };
    
    // Initialize wizard
    function initWizard() {
        wizardState.currentStep = parseInt(vcwWizard.current_step) || 1;
        wizardState.totalSteps = parseInt(vcwWizard.total_steps) || 5;
        
        // Load step data if needed
        if (wizardState.currentStep >= 2) {
            loadStepData(wizardState.currentStep);
        }
        
        // Load saved wizard state for current step
        if (wizardState.currentStep >= 3) {
            loadWizardState();
        }
        
        // Update product counts if on step 4
        if (wizardState.currentStep === 4) {
            setTimeout(() => {
                updateProductCounts();
            }, 500);
        }
        
        // Bind events
        bindEvents();
    }
    
    // Bind all event handlers
    function bindEvents() {
        // Test connection button
        $(document).on('click', '.vcw-wizard-test-connection', handleTestConnection);
        
        // Save step buttons
        $(document).on('click', '.vcw-wizard-save-step', handleSaveStep);
        
        // Next button
        $(document).on('click', '.vcw-wizard-next', handleNextStep);
        
        // Sync products button
        $(document).on('click', '.vcw-wizard-sync-products', handleSyncProducts);
        
        // Preview products button
        $(document).on('click', '.vcw-wizard-sync-preview', handlePreviewProducts);
        
        // Selection item clicks
        $(document).on('click', '.vcw-selection-item', handleSelectionClick);
        
        // Checkbox changes
        $(document).on('change', '.vcw-selection-item input[type="checkbox"]', handleCheckboxChange);
        
        // Search functionality
        $(document).on('input', '.vcw-search-input', handleSearch);
        
        // Select all / Clear all buttons
        $(document).on('click', '.vcw-wizard-select-all', handleSelectAll);
        $(document).on('click', '.vcw-wizard-clear-all', handleClearAll);
    }
    
    // Handle test connection
    function handleTestConnection(e) {
        e.preventDefault();
        
        const $btn = $(e.target);
        const $result = $('#wizard-connection-result');
        
        const baseUrl = $('#wizard_base_url').val().trim();
        const apiToken = $('#wizard_api_token').val().trim();
        
        if (!baseUrl || !apiToken) {
            showResult($result, 'Please provide both base URL and API token.', 'error');
            return;
        }
        
        // Show loading state
        $btn.prop('disabled', true).text('Testing...');
        $result.hide();
        
        // Make AJAX call
        $.post(vcwWizard.ajax_url, {
            action: 'vcw_wizard_test_connection',
            _ajax_nonce: vcwWizard.nonce,
            base_url: baseUrl,
            api_token: apiToken
        })
        .done(function(response) {
            if (response.success) {
                showResult($result, response.data.message, 'success');
            } else {
                showResult($result, response.data.message || 'Connection failed', 'error');
            }
        })
        .fail(function() {
            showResult($result, 'Network error occurred. Please try again.', 'error');
        })
        .always(function() {
            $btn.prop('disabled', false).text('Test Connection');
        });
    }
    
    // Handle save step
    function handleSaveStep(e) {
        e.preventDefault();
        
        const $btn = $(e.target);
        const step = parseInt($btn.data('step'));
        
        if (!validateStep(step)) {
            return;
        }
        
        // Show loading state
        $btn.prop('disabled', true).text('Saving...');
        
        // Collect step data
        const stepData = collectStepData(step);
        
        // Save step
        $.post(vcwWizard.ajax_url, {
            action: 'vcw_wizard_save',
            _ajax_nonce: vcwWizard.nonce,
            step: step,
            data: stepData
        })
        .done(function(response) {
            if (response.success) {
                // Move to next step
                moveToStep(step + 1);
            } else {
                alert('Error: ' + (response.data.message || 'Failed to save step'));
            }
        })
        .fail(function() {
            alert('Network error occurred. Please try again.');
        })
        .always(function() {
            $btn.prop('disabled', false).text('Save & Continue');
        });
    }
    
    // Handle next step
    function handleNextStep(e) {
        e.preventDefault();
        
        const $btn = $(e.target);
        const step = parseInt($btn.data('step'));
        
        if (step < wizardState.totalSteps) {
            moveToStep(step + 1);
        }
    }
    
    // Handle sync products
    function handleSyncProducts(e) {
        e.preventDefault();
        
        const $btn = $(e.target);
        const $result = $('#wizard-sync-result');
        
        if (wizardState.selectedServices.length === 0) {
            showResult($result, 'No services selected for sync.', 'error');
            return;
        }
        
        // Confirm before creating products
        if (!confirm('Are you sure you want to create these products? This will add new products to your WooCommerce store.')) {
            return;
        }
        
        // Show loading state
        $btn.prop('disabled', true).text('Creating Products...');
        $result.hide();
        
        // Sync products
        $.post(vcwWizard.ajax_url, {
            action: 'vcw_wizard_sync_products',
            _ajax_nonce: vcwWizard.nonce
        })
        .done(function(response) {
            console.log('Sync products response:', response);
            if (response.success) {
                showResult($result, response.data.message, 'success');
                updateProductCounts();
                // Move to next step after successful sync
                setTimeout(() => moveToStep(5), 2000);
            } else {
                const errorMessage = response.data && response.data.message ? response.data.message : 'Failed to sync products';
                showResult($result, errorMessage, 'error');
                console.error('Product sync failed:', response);
            }
        })
        .fail(function(xhr, status, error) {
            console.error('Product sync network error:', { xhr, status, error });
            let errorMessage = 'Network error occurred. Please try again.';
            
            if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                errorMessage = xhr.responseJSON.data.message;
            } else if (xhr.status === 0) {
                errorMessage = 'Connection failed. Please check your internet connection.';
            } else if (xhr.status === 500) {
                errorMessage = 'Server error occurred. Please try again later.';
            }
            
            showResult($result, errorMessage, 'error');
        })
        .always(function() {
            $btn.prop('disabled', false).text('Create Products');
        });
    }
    
    // Handle preview products
    function handlePreviewProducts(e) {
        e.preventDefault();
        
        const $result = $('#wizard-sync-result');
        
        if (wizardState.selectedServices.length === 0) {
            showResult($result, 'No services selected for preview.', 'error');
            return;
        }
        
        const prefix = $('#wizard_product_prefix').val() || 'Cloud VM - ';
        let previewHtml = '<h4>Product Preview</h4><div class="vcw-preview-list">';
        
        wizardState.selectedServices.forEach(service => {
            previewHtml += `<div class="vcw-preview-item">
                <strong>${prefix}${service.name}</strong><br>
                <small>CPU: ${service.cpu} | Memory: ${service.memory} | Storage: ${service.storage}</small>
            </div>`;
        });
        
        previewHtml += '</div>';
        
        showResult($result, previewHtml, 'info');
    }
    
    // Handle selection item clicks
    function handleSelectionClick(e) {
        const $item = $(e.target).closest('.vcw-selection-item');
        const $checkbox = $item.find('input[type="checkbox"]');
        
        if (e.target !== $checkbox[0]) {
            $checkbox.prop('checked', !$checkbox.prop('checked')).trigger('change');
        }
    }
    
    // Handle checkbox changes
    function handleCheckboxChange(e) {
        const $checkbox = $(e.target);
        const $item = $checkbox.closest('.vcw-selection-item');
        const type = $item.data('type');
        const id = $item.data('id');
        
        if ($checkbox.is(':checked')) {
            $item.addClass('selected');
            addToSelection(type, id, $item.data('item'));
        } else {
            $item.removeClass('selected');
            removeFromSelection(type, id);
        }
        
        updateSelectionCounts();
        updateSelectionSummary();
    }
    
    // Add item to selection
    function addToSelection(type, id, itemData) {
        switch (type) {
            case 'service':
                if (!wizardState.selectedServices.find(s => s.id === id)) {
                    wizardState.selectedServices.push(itemData);
                }
                break;
            case 'network':
                if (!wizardState.selectedNetworks.find(n => n.id === id)) {
                    wizardState.selectedNetworks.push(itemData);
                }
                break;
            case 'image':
                if (!wizardState.selectedImages.find(i => i.id === id)) {
                    wizardState.selectedImages.push(itemData);
                }
                break;
        }
    }
    
    // Remove item from selection
    function removeFromSelection(type, id) {
        switch (type) {
            case 'service':
                wizardState.selectedServices = wizardState.selectedServices.filter(s => s.id !== id);
                break;
            case 'network':
                wizardState.selectedNetworks = wizardState.selectedNetworks.filter(n => n.id !== id);
                break;
            case 'image':
                wizardState.selectedImages = wizardState.selectedImages.filter(i => i.id !== id);
                break;
        }
    }
    
    // Update selection counts
    function updateSelectionCounts() {
        $('#wizard-selected-count').text(wizardState.selectedServices.length);
        $('#wizard-product-count').text(wizardState.selectedServices.length);
    }
    
    // Update selection summary
    function updateSelectionSummary() {
        $('#service-count').text(wizardState.selectedServices.length);
        $('#network-count').text(wizardState.selectedNetworks.length);
        $('#image-count').text(wizardState.selectedImages.length);
    }
    
    // Update product counts
    function updateProductCounts() {
        const serviceCount = wizardState.selectedServices.length;
        const networkCount = wizardState.selectedNetworks.length;
        const imageCount = wizardState.selectedImages.length;
        
        // Calculate total products to create (services * networks * images)
        const totalProducts = serviceCount * networkCount * imageCount;
        
        // Update the display
        $('#wizard-selected-count').text(serviceCount);
        $('#wizard-product-count').text(totalProducts);
        
        // Also update selection summary if it exists
        updateSelectionCounts();
    }
    
    // Load step data
    function loadStepData(step) {
        switch (step) {
            case 2:
                loadZones();
                break;
            case 3:
                loadServices();
                break;
        }
    }
    
    // Load saved wizard state
    function loadWizardState() {
        $.post(vcwWizard.ajax_url, {
            action: 'vcw_wizard_get_state',
            _ajax_nonce: vcwWizard.nonce
        })
        .done(function(response) {
            if (response.success && response.data) {
                const state = response.data;
                
                // Update wizard state
                if (state.selected_service_ids && state.selected_service_ids.length > 0) {
                    wizardState.selectedServices = state.selected_service_ids.map(id => ({ id: id }));
                }
                if (state.selected_network_ids && state.selected_network_ids.length > 0) {
                    wizardState.selectedNetworks = state.selected_network_ids.map(id => ({ id: id }));
                }
                if (state.selected_image_ids && state.selected_image_ids.length > 0) {
                    wizardState.selectedImages = state.selected_image_ids.map(id => ({ id: id }));
                }
                
                // Update UI counts
                updateSelectionCounts();
                
                // If on step 4, update product counts
                if (wizardState.currentStep === 4) {
                    updateProductCounts();
                }
            }
        })
        .fail(function() {
            console.log('Failed to load wizard state');
        });
    }
    
    // Load zones
    function loadZones() {
        const $zoneSelect = $('#wizard_zone');
        
        $.post(vcwWizard.ajax_url, {
            action: 'vcw_wizard_step',
            _ajax_nonce: vcwWizard.nonce,
            step: 2
        })
        .done(function(response) {
            if (response.success && response.data.zones) {
                populateZoneSelect($zoneSelect, response.data.zones);
            } else {
                $zoneSelect.html('<option value="">Error loading zones</option>');
            }
        })
        .fail(function() {
            $zoneSelect.html('<option value="">Failed to load zones</option>');
        });
    }
    
    // Load services
    function loadServices() {
        // Load all services data first, then populate with selections
        $.post(vcwWizard.ajax_url, {
            action: 'vcw_wizard_step',
            _ajax_nonce: vcwWizard.nonce,
            step: 3
        })
        .done(function(response) {
            if (response.success && response.data) {
                const data = response.data;
                
                // Populate each section with previously selected items
                if (data.services) {
                    populateSelectionGrid($('#wizard-service-offerings'), data.services, 'service', data.selected_services || []);
                }
                if (data.networks) {
                    populateSelectionGrid($('#wizard-network-offerings'), data.networks, 'network', data.selected_networks || []);
                }
                if (data.images) {
                    populateSelectionGrid($('#wizard-vm-images'), data.images, 'image', data.selected_images || []);
                }
                
                // Update wizard state with previously selected items
                if (data.selected_services) {
                    wizardState.selectedServices = data.selected_services.map(id => ({ id: id }));
                }
                if (data.selected_networks) {
                    wizardState.selectedNetworks = data.selected_networks.map(id => ({ id: id }));
                }
                if (data.selected_images) {
                    wizardState.selectedImages = data.selected_images.map(id => ({ id: id }));
                }
                
                // Update selection counts
                updateSelectionCounts();
            } else {
                $('#wizard-service-offerings').html('<p>Error loading service offerings</p>');
                $('#wizard-network-offerings').html('<p>Error loading network offerings</p>');
                $('#wizard-vm-images').html('<p>Error loading VM images</p>');
            }
        })
        .fail(function() {
            $('#wizard-service-offerings').html('<p>Failed to load service offerings</p>');
            $('#wizard-network-offerings').html('<p>Failed to load network offerings</p>');
            $('#wizard-vm-images').html('<p>Failed to load VM images</p>');
        });
    }
    
    
    // Populate zone select
    function populateZoneSelect($select, zones) {
        $select.empty().append('<option value="">Select a zone</option>');
        
        zones.forEach(zone => {
            const $option = $('<option>')
                .val(zone.id)
                .text(`${zone.name} (${zone.location})`);
            $select.append($option);
        });
        
        // Set selected value if exists
        if (wizardState.selectedZone) {
            $select.val(wizardState.selectedZone);
        }
    }
    
    // Populate selection grid
    function populateSelectionGrid($container, items, type, selectedIds = []) {
        if (!items || items.length === 0) {
            $container.html('<p>No items available</p>');
            return;
        }
        
        let html = '<div class="vcw-selection-list-items">';
        
        items.forEach(item => {
            const specs = getItemSpecs(item, type);
            const isSelected = selectedIds.includes(item.id);
            const checkedAttr = isSelected ? 'checked' : '';
            const selectedClass = isSelected ? 'selected' : '';
            
            html += `
                <div class="vcw-selection-item ${selectedClass}" data-type="${type}" data-id="${item.id}" data-item='${JSON.stringify(item)}'>
                    <div class="vcw-selection-item-content">
                        <input type="checkbox" id="${type}_${item.id}" ${checkedAttr}>
                        <div class="vcw-selection-item-details">
                            <h4>${item.name}</h4>
                            <p>${item.description || 'No description available'}</p>
                            <div class="vcw-item-specs">${specs}</div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        $container.html(html);
    }
    
    // Get item specifications
    function getItemSpecs(item, type) {
        switch (type) {
            case 'service':
                let serviceSpecs = `<span>CPU: ${item.cpu}</span><span>Memory: ${item.memory}</span><span>Storage: ${item.storage}</span>`;
                if (item.cpu_speed) serviceSpecs += `<span>Speed: ${item.cpu_speed}</span>`;
                if (item.network_rate) serviceSpecs += `<span>Network: ${item.network_rate}</span>`;
                if (item.disk_iops) serviceSpecs += `<span>IOPS: ${item.disk_iops}</span>`;
                if (item.price_up) serviceSpecs += `<span>Price Up: ${item.price_up}</span>`;
                if (item.price_down) serviceSpecs += `<span>Price Down: ${item.price_down}</span>`;
                if (item.category) serviceSpecs += `<span>Category: ${item.category}</span>`;
                if (item.suggested) serviceSpecs += `<span class="vcw-suggested">⭐ Suggested</span>`;
                return serviceSpecs;
            case 'network':
                let networkSpecs = `<span>Type: ${item.type}</span>`;
                if (item.subnet) networkSpecs += `<span>Subnet: ${item.subnet}</span>`;
                if (item.gateway) networkSpecs += `<span>Gateway: ${item.gateway}</span>`;
                if (item.vlan) networkSpecs += `<span>VLAN: ${item.vlan}</span>`;
                if (item.zone) networkSpecs += `<span>Zone: ${item.zone}</span>`;
                return networkSpecs;
            case 'image':
                let imageSpecs = `<span>Type: ${item.type}</span><span>OS: ${item.os_type}</span>`;
                if (item.os_name) imageSpecs += `<span>OS Name: ${item.os_name}</span>`;
                if (item.os_version) imageSpecs += `<span>Version: ${item.os_version}</span>`;
                if (item.cpu_requirement) imageSpecs += `<span>CPU Req: ${item.cpu_requirement}</span>`;
                if (item.cpu_speed_requirement) imageSpecs += `<span>Speed Req: ${item.cpu_speed_requirement}</span>`;
                if (item.memory_requirement) imageSpecs += `<span>Memory Req: ${item.memory_requirement}</span>`;
                if (item.disk_requirement) imageSpecs += `<span>Disk Req: ${item.disk_requirement}</span>`;
                if (item.category) imageSpecs += `<span>Category: ${item.category}</span>`;
                if (item.ready_to_use_app) imageSpecs += `<span class="vcw-ready-app">🚀 Ready App</span>`;
                return imageSpecs;
            default:
                return '';
        }
    }
    
    // Validate step
    function validateStep(step) {
        switch (step) {
            case 1:
                const baseUrl = $('#wizard_base_url').val().trim();
                const apiToken = $('#wizard_api_token').val().trim();
                if (!baseUrl || !apiToken) {
                    alert('Please provide both base URL and API token.');
                    return false;
                }
                break;
            case 2:
                const zoneId = $('#wizard_zone').val();
                if (!zoneId) {
                    alert('Please select a zone.');
                    return false;
                }
                wizardState.selectedZone = zoneId;
                break;
            case 3:
                return validateServiceSelection();
        }
        return true;
    }
    
    // Validate service selection with visual feedback
    function validateServiceSelection() {
        let isValid = true;
        
        // Check service offerings
        const serviceCount = wizardState.selectedServices.length;
        const $serviceError = $('#service-validation-error');
        if (serviceCount === 0) {
            $serviceError.show();
            isValid = false;
        } else {
            $serviceError.hide();
        }
        
        // Check network offerings
        const networkCount = wizardState.selectedNetworks.length;
        const $networkError = $('#network-validation-error');
        if (networkCount === 0) {
            $networkError.show();
            isValid = false;
        } else {
            $networkError.hide();
        }
        
        // Check VM images
        const imageCount = wizardState.selectedImages.length;
        const $imageError = $('#image-validation-error');
        if (imageCount === 0) {
            $imageError.show();
            isValid = false;
        } else {
            $imageError.hide();
        }
        
        return isValid;
    }
    
    // Collect step data
    function collectStepData(step) {
        switch (step) {
            case 1:
                return {
                    base_url: $('#wizard_base_url').val().trim(),
                    api_token: $('#wizard_api_token').val().trim()
                };
            case 2:
                return {
                    zone_id: $('#wizard_zone').val()
                };
            case 3:
                return {
                    selected_service_ids: wizardState.selectedServices.map(s => s.id),
                    selected_network_ids: wizardState.selectedNetworks.map(n => n.id),
                    selected_image_ids: wizardState.selectedImages.map(i => i.id)
                };
            default:
                return {};
        }
    }
    
    // Move to step
    function moveToStep(step) {
        if (step >= 1 && step <= wizardState.totalSteps) {
            window.location.href = `?page=virakcloud-woo-wizard&step=${step}`;
        }
    }
    
    // Handle search functionality
    function handleSearch(e) {
        const $input = $(e.target);
        const searchTerm = $input.val().toLowerCase();
        const type = $input.attr('id').replace('wizard-', '').replace('-search', '');
        const $container = $(`#wizard-${type}-offerings`);
        
        $container.find('.vcw-selection-item').each(function() {
            const $item = $(this);
            const text = $item.text().toLowerCase();
            
            if (text.includes(searchTerm)) {
                $item.show();
            } else {
                $item.hide();
            }
        });
    }
    
    // Handle select all
    function handleSelectAll(e) {
        const type = $(e.target).data('type');
        const $container = $(`#wizard-${type}-offerings`);
        
        $container.find('.vcw-selection-item:visible input[type="checkbox"]').prop('checked', true).trigger('change');
    }
    
    // Handle clear all
    function handleClearAll(e) {
        const type = $(e.target).data('type');
        const $container = $(`#wizard-${type}-offerings`);
        
        $container.find('.vcw-selection-item:visible input[type="checkbox"]').prop('checked', false).trigger('change');
    }
    
    // Show result message
    function showResult($container, message, type) {
        // Remove any previous result state classes and apply the new one
        // so that success messages appear with a green background,
        // errors with red, and warnings with yellow, as defined in
        // vcw-wizard.css. Without adding the class, the message would
        // default to plain styling.
        $container
            .removeClass('success error warning info')
            .addClass(type)
            .html(message)
            .show();
    }
    
    // Initialize when document is ready
    $(document).ready(function() {
        initWizard();
    });
    
})(jQuery);
