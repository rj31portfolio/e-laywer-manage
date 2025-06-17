 
// Document ready function
$(document).ready(function() {
    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // Initialize popovers
    $('[data-bs-toggle="popover"]').popover();
    
    // Handle form submissions with AJAX
    $('form.ajax-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        const originalText = submitBtn.html();
        
        // Show loading state
        submitBtn.prop('disabled', true);
        submitBtn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...');
        
        $.ajax({
            url: form.attr('action'),
            type: form.attr('method'),
            data: form.serialize(),
            success: function(response) {
                if (response.success) {
                    // Show success message
                    if (response.redirect) {
                        window.location.href = response.redirect;
                    } else {
                        const alert = `<div class="alert alert-success">${response.message}</div>`;
                        form.prepend(alert);
                        form.trigger('reset');
                        
                        // Remove alert after 5 seconds
                        setTimeout(() => {
                            $('.alert').fadeOut(500, function() {
                                $(this).remove();
                            });
                        }, 5000);
                    }
                } else {
                    // Show error message
                    const alert = `<div class="alert alert-danger">${response.message}</div>`;
                    form.prepend(alert);
                }
            },
            error: function(xhr) {
                // Show error message
                let errorMessage = 'An error occurred. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                const alert = `<div class="alert alert-danger">${errorMessage}</div>`;
                form.prepend(alert);
            },
            complete: function() {
                // Reset button state
                submitBtn.prop('disabled', false);
                submitBtn.html(originalText);
            }
        });
    });
    
    // Handle delete confirmations
    $('.confirm-delete').on('click', function(e) {
        e.preventDefault();
        const url = $(this).attr('href');
        if (confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
            window.location.href = url;
        }
    });
    
    // Image preview for file uploads
    $('input[type="file"].image-upload').on('change', function() {
        const input = $(this);
        const preview = $(input.data('target'));
        const file = input[0].files[0];
        
        if (file) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                preview.attr('src', e.target.result);
                preview.parent().show();
            }
            
            reader.readAsDataURL(file);
        }
    });
    
    // Initialize DataTables if present
    if ($.fn.DataTable) {
        $('#dataTable').DataTable({
            responsive: true,
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search...",
            }
        });
    }
    
    // Initialize select2 if present
    if ($.fn.select2) {
        $('.select2').select2({
            theme: 'bootstrap-5'
        });
    }
});