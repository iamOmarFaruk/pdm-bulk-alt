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
            var altText = $input.val().trim();
            
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
                    alt_text: altText,
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
            
            // Clear any previous status when user starts typing
            $input.siblings('.pdm-alt-status').removeClass('success error saving').text('');
            
            // Simple auto-resize
            if (text.length > 20) {
                $input.css('width', Math.min(text.length * 8, 200) + 'px');
            } else {
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
    }
    
})(jQuery);
