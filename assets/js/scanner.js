/**
 * PDM Scanner Admin JavaScript
 */
(function($) {
    'use strict';
    
    var scanData = {
        isScanning: false,
        results: [],
        totalScanned: 0,
        totalImagesFound: 0,
        totalUpdated: 0,
        offset: 0
    };
    
    $(document).ready(function() {
        initScanner();
    });
    
    function initScanner() {
        $('#pdm-start-scan').on('click', startScan);
        $('#pdm-stop-scan').on('click', stopScan);
    }
    
    function startScan() {
        if (scanData.isScanning) {
            return;
        }
        
        // Reset data
        scanData = {
            isScanning: true,
            results: [],
            totalScanned: 0,
            totalImagesFound: 0,
            totalUpdated: 0,
            offset: 0
        };
        
        // Update UI
        $('#pdm-start-scan').hide();
        $('#pdm-stop-scan').show();
        $('.pdm-progress-container').show();
        $('#pdm-scan-results').hide();
        
        updateProgress(0, pdmScanner.messages.scanning);
        updateStats(scanData.totalScanned, scanData.totalImagesFound, scanData.totalUpdated);
        
        // Start scanning
        scanContent();
    }
    
    function stopScan() {
        scanData.isScanning = false;
        
        // Update UI
        $('#pdm-start-scan').show();
        $('#pdm-stop-scan').hide();
        
        updateProgress(100, pdmScanner.messages.stopped);
        
        if (scanData.results.length > 0) {
            displayResults();
        }
    }
    
    function scanContent() {
        if (!scanData.isScanning) {
            return;
        }
        
        $.ajax({
            url: pdmScanner.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pdm_scan_content',
                offset: scanData.offset,
                nonce: pdmScanner.nonce
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    
                    // Update scan data
                    scanData.results = scanData.results.concat(data.results);
                    scanData.totalScanned += data.processed;
                    scanData.totalImagesFound += data.images_found;
                    scanData.totalUpdated += data.images_updated;
                    scanData.offset = data.offset;
                    
                    // Update progress
                    var progress = Math.round((scanData.totalScanned / data.total) * 100);
                    updateProgress(progress, pdmScanner.messages.scanning + ' (' + scanData.totalScanned + '/' + data.total + ')');
                    updateStats(scanData.totalScanned, scanData.totalImagesFound, scanData.totalUpdated);
                    
                    // Continue scanning if there are more posts
                    if (data.has_more && scanData.isScanning) {
                        setTimeout(scanContent, 200); // Small delay to prevent overwhelming
                    } else if (scanData.isScanning) {
                        // Scanning complete
                        finalizeScan();
                    }
                } else {
                    showError(response.data || pdmScanner.messages.error);
                    stopScan();
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error:', textStatus, errorThrown);
                showError(pdmScanner.messages.error + ': ' + textStatus);
                stopScan();
            }
        });
    }
    
    function finalizeScan() {
        scanData.isScanning = false;
        
        // Update UI
        $('#pdm-start-scan').show();
        $('#pdm-stop-scan').hide();
        
        updateProgress(100, pdmScanner.messages.completed);
        
        // Display results
        displayResults();
        
        // Show completion message
        if (scanData.totalUpdated > 0) {
            showSuccess('Scan completed! ' + scanData.totalUpdated + ' images were synced from your media library.');
        } else {
            showSuccess('Scan completed! All images are already synced with your media library.');
        }
    }
    
    function updateProgress(percent, text) {
        $('#pdm-progress-percent').text(percent + '%');
        $('#pdm-progress-text').text(text);
        $('.pdm-progress-fill').css('width', percent + '%');
    }
    
    function updateStats(scanned, found, updated) {
        $('#pdm-stats-scanned').text(scanned);
        $('#pdm-stats-found').text(found);
        $('#pdm-stats-updated').text(updated);
    }
    
    function displayResults() {
        if (scanData.results.length === 0) {
            $('#pdm-results-table').html('<div class="pdm-no-results">All images are already synced with your media library.</div>');
        } else {
            var html = buildResultsTable();
            $('#pdm-results-table').html(html);
        }
        
        $('#pdm-scan-results').show();
    }
    
    function buildResultsTable() {
        var html = '';
        
        // Summary stats
        html += '<div class="pdm-summary-stats">';
        html += '<div class="pdm-stat-box">';
        html += '<span class="pdm-stat-number">' + scanData.totalScanned + '</span>';
        html += '<span class="pdm-stat-label">Pages Scanned</span>';
        html += '</div>';
        html += '<div class="pdm-stat-box">';
        html += '<span class="pdm-stat-number">' + scanData.totalImagesFound + '</span>';
        html += '<span class="pdm-stat-label">Images Found</span>';
        html += '</div>';
        html += '<div class="pdm-stat-box">';
        html += '<span class="pdm-stat-number">' + scanData.totalUpdated + '</span>';
        html += '<span class="pdm-stat-label">Images Synced</span>';
        html += '</div>';
        html += '</div>';
        
        if (scanData.results.length === 0) {
            html += '<div class="pdm-no-results">All images are already synced with your media library.</div>';
            return html;
        }
        
        // Results table
        html += '<table class="pdm-results-table wp-list-table widefat fixed striped">';
        html += '<thead>';
        html += '<tr>';
        html += '<th>Page/Post</th>';
        html += '<th>Type</th>';
        html += '<th>Image</th>';
        html += '<th>Title Tag</th>';
        html += '<th>Alt in Media Library</th>';
        html += '<th>Alt in Page</th>';
        html += '<th>Status</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        scanData.results.forEach(function(post) {
            if (post.images && post.images.length > 0) {
                post.images.forEach(function(image, index) {
                    html += '<tr>';
                    
                    // Post title (only show for first image)
                    if (index === 0) {
                        html += '<td rowspan="' + post.images.length + '">';
                        html += '<a href="' + post.post_url + '" target="_blank" class="pdm-post-link">';
                        html += post.post_title || 'Untitled';
                        html += '</a>';
                        html += '</td>';
                        var postTypeLabel = post.post_type;
                        if (post.is_divi) {
                            postTypeLabel += ' (Divi)';
                        }
                        html += '<td rowspan="' + post.images.length + '">' + postTypeLabel + '</td>';
                    }
                    
                    // Image
                    html += '<td>';
                    html += '<img src="' + image.src + '" class="pdm-image-preview" alt="" onerror="this.style.display=\'none\'">';
                    html += '<br><small>' + image.src.split('/').pop() + '</small>';
                    html += '</td>';
                    
                    // Title tag (media library vs page)
                    html += '<td>';
                    if (image.title_in_media && image.title_in_page) {
                        if (image.title_in_media === image.title_in_page) {
                            html += '<span class="pdm-title-text">' + image.title_in_media + '</span>';
                        } else {
                            html += '<strong>Media:</strong> ' + (image.title_in_media || '<em>Empty</em>') + '<br>';
                            html += '<strong>Page:</strong> ' + (image.title_in_page || '<em>Empty</em>');
                        }
                    } else if (image.title_in_media) {
                        html += '<strong>Media:</strong> ' + image.title_in_media + '<br>';
                        html += '<strong>Page:</strong> <em>Empty</em>';
                    } else if (image.title_in_page) {
                        html += '<strong>Media:</strong> <em>Empty</em><br>';
                        html += '<strong>Page:</strong> ' + image.title_in_page;
                    } else {
                        html += '<em>Empty in media library</em>';
                    }
                    html += '</td>';
                    
                    // Alt in media library
                    html += '<td>';
                    if (image.alt_in_media) {
                        html += '<span class="pdm-alt-text">' + image.alt_in_media + '</span>';
                    } else {
                        html += '<em style="color: #dc3232;">Empty in media library</em>';
                    }
                    html += '</td>';
                    
                    // Alt in page
                    html += '<td>';
                    if (image.alt_in_page) {
                        html += '<span class="pdm-alt-text">' + image.alt_in_page + '</span>';
                    } else {
                        if (!image.alt_in_media) {
                            html += '<em style="color: #999;">-</em>';
                        } else {
                            html += '<em style="color: #dc3232;">Not set on page</em>';
                        }
                    }
                    html += '</td>';
                    
                    // Status
                    html += '<td>';
                    if (image.success) {
                        if (image.already_synced) {
                            html += '<span class="pdm-status-success">✓ Synced</span>';
                        } else {
                            html += '<span class="pdm-status-success">✓ Synced</span>';
                        }
                    } else {
                        // Check specific reason for not syncing
                        if (image.reason && image.reason.indexOf('Media library empty but page has data') !== -1) {
                            html += '<span class="pdm-status-not-synced">✗ Not Synced</span><br>';
                            html += '<small>Media library is empty - add alt text to sync</small>';
                        } else if (image.reason && image.reason.indexOf('Title empty in media library but page has data') !== -1) {
                            html += '<span class="pdm-status-not-synced">✗ Not Synced</span><br>';
                            html += '<small>Media library title is empty - add title to sync</small>';
                        } else if (image.reason && image.reason.indexOf('Empty in media library') !== -1) {
                            html += '<span class="pdm-status-empty">— Empty in media library</span><br>';
                            html += '<small>Add data to media library to sync</small>';
                        } else if (image.reason && image.reason.indexOf('Alt tag empty') !== -1) {
                            html += '<span class="pdm-status-empty">— Alt tag empty in media library</span><br>';
                            html += '<small>Add alt text in media library to sync</small>';
                        } else if (image.reason && image.reason.indexOf('Title empty') !== -1) {
                            html += '<span class="pdm-status-empty">— Title empty in media library</span><br>';
                            html += '<small>Add title in media library to sync</small>';
                        } else if (image.reason && image.reason.indexOf('Image not found') !== -1) {
                            html += '<span class="pdm-status-info">ⓘ Image not found in media library</span><br>';
                            html += '<small>Please upload image to media library first</small>';
                        } else {
                            html += '<span class="pdm-status-empty">— No sync needed</span>';
                        }
                    }
                    html += '</td>';
                    
                    html += '</tr>';
                });
            }
        });
        
        html += '</tbody>';
        html += '</table>';
        
        return html;
    }
    
    function showError(message) {
        var errorHtml = '<div class="pdm-error-message">' + message + '</div>';
        $('.pdm-progress-container').after(errorHtml);
        
        setTimeout(function() {
            $('.pdm-error-message').fadeOut();
        }, 5000);
    }
    
    function showSuccess(message) {
        var successHtml = '<div class="pdm-success-message">' + message + '</div>';
        $('.pdm-progress-container').after(successHtml);
        
        setTimeout(function() {
            $('.pdm-success-message').fadeOut();
        }, 5000);
    }
    
})(jQuery);
