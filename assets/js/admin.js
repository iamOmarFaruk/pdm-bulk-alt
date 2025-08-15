/**
 * PDM Bulk Alt Admin JavaScript
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        initPDMBulkAlt();
    });
    
    function initPDMBulkAlt() {
        // Handle single save all button click
        $(document).on('click', '.pdm-save-all-attributes', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $wrapper = $button.closest('.pdm-bulk-attributes-wrapper');
            var $status = $wrapper.find('.pdm-save-status');
            var attachmentId = $wrapper.data('attachment-id');
            
            // Get all field values
            var titleValue = $wrapper.find('.pdm-attribute-input[data-field-type="title"]').val().trim();
            var altValue = $wrapper.find('.pdm-attribute-input[data-field-type="alt"]').val().trim();
            var captionValue = $wrapper.find('.pdm-attribute-input[data-field-type="caption"]').val().trim();
            
            // Validate required fields
            if (!altValue) {
                $status.removeClass('saving success').addClass('error').text('Alt text is required');
                return;
            }
            
            // Disable button and show saving status
            $button.prop('disabled', true).text('Saving...');
            $status.removeClass('success error partial').addClass('saving').text(pdmBulkAlt.messages.saving);
            
            // Send AJAX request with all values
            $.ajax({
                url: pdmBulkAlt.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pdm_update_alt_text',
                    attachment_id: attachmentId,
                    title: titleValue,
                    alt_text: altValue,
                    caption: captionValue,
                    nonce: pdmBulkAlt.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        $status.removeClass('saving error').addClass('success').text(data.message);
                        
                        // Clear status after 3 seconds
                        setTimeout(function() {
                            $status.removeClass('success').text('');
                        }, 3000);
                    } else {
                        $status.removeClass('saving success').addClass('error').text(response.data || pdmBulkAlt.messages.error);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX Error:', textStatus, errorThrown);
                    $status.removeClass('saving success partial').addClass('error').text(pdmBulkAlt.messages.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text('Save All');
                }
            });
        });
        
        // Handle Enter key in input fields - trigger save all
        $(document).on('keypress', '.pdm-attribute-input', function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                $(this).closest('.pdm-bulk-attributes-wrapper').find('.pdm-save-all-attributes').trigger('click');
            }
        });
        
        // Clear status when user starts typing in any field
        $(document).on('input', '.pdm-attribute-input', function() {
            var $wrapper = $(this).closest('.pdm-bulk-attributes-wrapper');
            $wrapper.find('.pdm-save-status').removeClass('success error saving').text('');
        });
        
        // Handle quick edit functionality
        $(document).on('click', '.editinline', function() {
            var $row = $(this).closest('tr');
            var attachmentId = $row.attr('id').replace('post-', '');
            var currentAlt = $row.find('.pdm-attribute-input[data-field-type="alt"]').val() || '';
            
            // Wait for quick edit row to be created
            setTimeout(function() {
                var $quickEditRow = $('#edit-' + attachmentId);
                $quickEditRow.find('.pdm-quick-edit-alt').val(currentAlt);
            }, 100);
        });
        
        // Initialize magnify feature
        initMagnifyFeature();
    }
    
    function initMagnifyFeature() {
        // Handle magnify trigger click
        $(document).on('click', '.pdm-magnify-trigger', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $trigger = $(this);
            var imageUrl = $trigger.data('image-url');
            
            if (imageUrl) {
                showImagePreview(imageUrl, $trigger);
            }
        });
        
        // Close preview on ESC key
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27) { // ESC key
                hideImagePreview();
            }
        });
        
        // Close preview on overlay click
        $(document).on('click', '.pdm-image-preview-overlay', function(e) {
            if (e.target === this) {
                hideImagePreview();
            }
        });
    }
    
    function showImagePreview(imageUrl, $trigger) {
        // Remove any existing preview
        $('.pdm-image-preview-overlay').remove();
        
        // Create overlay
        var $overlay = $('<div class="pdm-image-preview-overlay"></div>');
        var $container = $('<div class="pdm-image-preview-container"></div>');
        var $loading = $('<div class="pdm-image-loading"><div class="pdm-spinner"></div>Loading image...</div>');
        
        $container.append($loading);
        $overlay.append($container);
        $('body').append($overlay);
        
        // Show overlay with loading
        setTimeout(function() {
            $overlay.addClass('show');
        }, 10);
        
        // Load image
        var img = new Image();
        img.onload = function() {
            // Create simple image content (no info, no close button)
            var $img = $('<img class="pdm-image-preview-img" alt="Preview">');
            $img.attr('src', imageUrl);
            
            // Replace loading with just the image
            $container.html($img);
        };
        
        img.onerror = function() {
            $container.html('<div class="pdm-image-loading">Failed to load image</div>');
        };
        
        img.src = imageUrl;
    }
    
    function hideImagePreview() {
        var $overlay = $('.pdm-image-preview-overlay');
        if ($overlay.length) {
            $overlay.removeClass('show');
            setTimeout(function() {
                $overlay.remove();
            }, 300);
        }
    }
    
})(jQuery);
