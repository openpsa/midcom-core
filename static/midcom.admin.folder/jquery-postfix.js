$(document).ready(function() {
    $('#midcom_admin_folder_order_form_sort_type select').on('change', function() {
        let form = $(this).closest('form');
        $('#midcom_admin_folder_order_form').addClass('loading');
        $.post(form.attr('action') + '?ajax', form.serialize(), function(data) {
            $('#midcom_admin_folder_order_form').removeClass('loading');
            $('#midcom_admin_folder_order_form_wrapper').html(data);
        });
    });

    $('#midcom_admin_folder_order_form').on('submit', function() {
        $(this).find('ul').each(function() {
            $(this).find('li').each(function(i) {
                $(this).find('input').val(i);
            });
        });
    });
});
