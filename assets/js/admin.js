/**
 * PDM Bulk Alt Admin JavaScript
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        initPDMBulkAlt();
    });
    
    function initPDMBulkAlt() {
        // Handle save button clicks
        $(document).on('click', '.pdm-save-alt', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $input = $button.siblings('.pdm-alt-input');
            var $status = $button.siblings('.pdm-alt-status');
            var attachmentId = $button.data('attachment-id');
            var fieldType = $button.data('field-type');
            var fieldValue = $input.val().trim();
            
            // Disable button and show saving status
            $button.prop('disabled', true);
            $status.removeClass('success error').addClass('saving').text(pdmBulkAlt.messages.saving);
            
            // Send AJAX request
            $.ajax({
                url: pdmBulkAlt.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pdm_update_alt_text',
                    attachment_id: attachmentId,
                    alt_text: fieldValue, // Keep same param name for compatibility
                    field_type: fieldType,
                    nonce: pdmBulkAlt.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.removeClass('saving error').addClass('success').text(pdmBulkAlt.messages.saved);
                        // Clear status after 2 seconds
                        setTimeout(function() {
                            $status.removeClass('success').text('');
                        }, 2000);
                    } else {
                        $status.removeClass('saving success').addClass('error').text(response.data || pdmBulkAlt.messages.error);
                    }
                },
                error: function() {
                    $status.removeClass('saving success').addClass('error').text(pdmBulkAlt.messages.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });
        
        // Handle Enter key in input fields
        $(document).on('keypress', '.pdm-alt-input', function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                $(this).siblings('.pdm-save-alt').trigger('click');
            }
        });
        
        // Auto-resize input fields based on content
        $(document).on('input', '.pdm-alt-input', function() {
            var $input = $(this);
            var text = $input.val();
            var fieldType = $input.data('field-type');
            
            // Clear any previous status when user starts typing
            $input.siblings('.pdm-alt-status').removeClass('success error saving').text('');
            
            // Simple auto-resize for text inputs (not textareas)
            if (!$input.is('textarea') && text.length > 20) {
                $input.css('width', Math.min(text.length * 8, 200) + 'px');
            } else if (!$input.is('textarea')) {
                $input.css('width', '');
            }
        });
        
        // Handle quick edit functionality
        $(document).on('click', '.editinline', function() {
            var $row = $(this).closest('tr');
            var attachmentId = $row.attr('id').replace('post-', '');
            var currentAlt = $row.find('.pdm-alt-input').val() || '';
            
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
