jQuery(document).ready(function($) {
    // Add dialog HTML to the page
    $('body').append($('#slimsnap-upload-dialog-template').html());
    
    var dialog = $('#slimsnap-upload-dialog');
    var currentFile = null;
    var currentUploader = null;
    
    // Store original upload handler
    var originalUpload = wp.Uploader.prototype.success;
    
    // Override the upload success handler
    wp.Uploader.prototype.success = function(file) {
        if (file.type && file.type.indexOf('image/') === 0) {
            currentFile = file;
            currentUploader = this;
            showOptimizationDialog(file);
        } else {
            originalUpload.call(this, file);
        }
    };
    
    function showOptimizationDialog(file) {
        // Set file information
        $('#slimsnap-file-name').text(file.name);
        $('#slimsnap-original-size').text(formatBytes(file.size));
        
        // Create image preview
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = new Image();
            img.onload = function() {
                $('#slimsnap-dimensions').text(this.width + ' × ' + this.height + ' px');
                $('#slimsnap-preview-original').attr('src', e.target.result);
                $('#slimsnap-preview-optimized').attr('src', e.target.result);
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file.getNative());
        
        // Handle comparison slider
        $('.comparison-range').on('input', function() {
            var value = $(this).val();
            $('#slimsnap-preview-optimized').css('clip-path', 
                `polygon(${value}% 0, 100% 0, 100% 100%, ${value}% 100%)`
            );
        });
        
        // Show dialog
        dialog.fadeIn(200);
        
        // Reset UI
        $('.optimization-progress, .optimization-results').hide();
        $('.dialog-buttons').show();
    }
    
    // Handle optimization button click
    $('#slimsnap-optimize').click(function() {
        if (!currentFile) return;
        
        $(this).prop('disabled', true);
        $('.optimization-progress').show();
        updateProgress(0, 'Starting optimization...');
        
        var formData = new FormData();
        formData.append('action', 'slimsnap_pre_upload_optimize');
        formData.append('nonce', slimsnapMedia.nonce);
        formData.append('file', currentFile.getNative());
        formData.append('compression_type', $('input[name="compression_type"]:checked').val());
        formData.append('quality', $('#compression-quality').val());
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(evt) {
                    if (evt.lengthComputable) {
                        var percentComplete = (evt.loaded / evt.total) * 100;
                        updateProgress(percentComplete);
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    // Update optimized preview
                    if (response.data.preview_url) {
                        $('#slimsnap-preview-optimized').attr('src', response.data.preview_url);
                    }
                    showResults(response.data);
                    currentFile.optimize_image = 'yes';
                    currentFile.optimizationSettings = {
                        type: $('input[name="compression_type"]:checked').val(),
                        quality: $('#compression-quality').val()
                    };
                } else {
                    alert(response.data.message || 'Optimization failed');
                    completeUpload();
                }
            },
            error: function() {
                alert('Optimization request failed');
                completeUpload();
            }
        });
    });
    
    // Handle skip button click
    $('#slimsnap-skip').click(function() {
        completeUpload();
    });
    
    function showResults(data) {
        $('.optimization-progress').hide();
        $('.optimization-results').show();
        
        $('#result-original-size').text(formatBytes(data.original_size));
        $('#result-optimized-size').text(formatBytes(data.optimized_size));
        $('#result-savings').text(formatBytes(data.original_size - data.optimized_size) + 
            ' (' + data.savings_percent + '%)');
        
        // Auto-continue after 2 seconds
        setTimeout(completeUpload, 2000);
    }
    
    function completeUpload() {
        dialog.fadeOut(200);
        if (currentUploader && currentFile) {
            originalUpload.call(currentUploader, currentFile);
        }
        currentFile = null;
        currentUploader = null;
    }
    
    function updateProgress(percent, message) {
        $('.progress-bar-fill').css('width', percent + '%');
        if (message) {
            $('.progress-text').text(message);
        } else {
            $('.progress-text').text('Optimizing: ' + Math.round(percent) + '%');
        }
    }
    
    function formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';
        var k = 1024;
        var sizes = ['Bytes', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // Quality slider handling
    $('#compression-quality').on('input', function() {
        $('#quality-value').text($(this).val());
    });
    
    // Handle close button
    $('.close-dialog').click(function() {
        completeUpload();
    });
}); 