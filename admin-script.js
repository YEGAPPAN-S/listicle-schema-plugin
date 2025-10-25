jQuery(document).ready(function($) {
    let itemIndex = $('#list-items-container .list-item-row').length;
    
    // Toggle fields when checkbox changes
    $('input[name="listicle_schema_enable"]').on('change', function() {
        if ($(this).is(':checked')) {
            $('#listicle-schema-fields').slideDown();
        } else {
            $('#listicle-schema-fields').slideUp();
        }
    });
    
    // Add new item
    $('#add-list-item').on('click', function() {
        let template = $('#list-item-template').html();
        let newItem = template.replace(/\{\{INDEX\}\}/g, itemIndex)
                              .replace(/\{\{POSITION\}\}/g, itemIndex + 1);
        $('#list-items-container').append(newItem);
        itemIndex++;
        updatePositionNumbers();
    });
    
    // Remove item
    $(document).on('click', '.remove-item', function() {
        if ($('#list-items-container .list-item-row').length > 1) {
            $(this).closest('.list-item-row').fadeOut(300, function() {
                $(this).remove();
                updatePositionNumbers();
            });
        } else {
            alert('You must have at least one item.');
        }
    });
    
    // Auto-detect list items
    $('#detect-list-items').on('click', function() {
        let postId = $('#post_ID').val();
        
        if (!postId) {
            alert('Please save the post as a draft first.');
            return;
        }
        
        $('#detect-loading').show();
        $(this).prop('disabled', true);
        
        $.ajax({
            url: listicleSchema.ajax_url,
            type: 'POST',
            data: {
                action: 'detect_list_items',
                post_id: postId,
                nonce: listicleSchema.nonce
            },
            success: function(response) {
                $('#detect-loading').hide();
                $('#detect-list-items').prop('disabled', false);
                
                if (response.success && response.data.items) {
                    // Clear existing items
                    $('#list-items-container').empty();
                    itemIndex = 0;
                    
                    // Add detected items
                    response.data.items.forEach(function(item) {
                        let template = $('#list-item-template').html();
                        let newItem = template.replace(/\{\{INDEX\}\}/g, itemIndex)
                                              .replace(/\{\{POSITION\}\}/g, itemIndex + 1);
                        let $newItem = $(newItem);
                        
                        $newItem.find('input[name*="[name]"]').val(item.name);
                        $newItem.find('input[name*="[url]"]').val(item.url);
                        
                        $('#list-items-container').append($newItem);
                        itemIndex++;
                    });
                    
                    updatePositionNumbers();
                    alert('Detected ' + response.data.items.length + ' items! Please review and edit as needed.');
                } else {
                    alert(response.data || 'No list items detected. Please add items manually.');
                }
            },
            error: function() {
                $('#detect-loading').hide();
                $('#detect-list-items').prop('disabled', false);
                alert('Error detecting list items. Please try again.');
            }
        });
    });
    
    // Update position numbers
    function updatePositionNumbers() {
        $('#list-items-container .list-item-row').each(function(index) {
            $(this).find('.position-number').text(index + 1);
            
            // Update input names to ensure proper indexing
            $(this).find('input[name*="[name]"]').attr('name', 'listicle_items[' + index + '][name]');
            $(this).find('input[name*="[url]"]').attr('name', 'listicle_items[' + index + '][url]');
        });
    }
    
    // Initialize position numbers on page load
    updatePositionNumbers();
});
