(function ($) {
    'use strict';

    // Tab switching
    $(document).on('click', '.nav-tab[data-tab]', function (e) {
        e.preventDefault();
        var tab = $(this).data('tab');

        $('.nav-tab[data-tab]').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        $('.qo-tab-panel').hide();
        $('#' + tab).show();
    });

    $(document).on('submit', '#quick-order-form', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $submit = $form.find('[type="submit"]');
        var $result = $('#quick-order-result');

        $submit.prop('disabled', true);
        $result.hide();

        $.ajax({
            url: quickOrder.ajaxUrl,
            method: 'POST',
            data: $form.serialize() + '&action=quick_order_create',
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $('#qo-order-id').text('#' + response.data.order_id);
                    $('#qo-payment-url').val(response.data.payment_url);
                    $result.show();
                    $form[0].reset();
                } else {
                    alert(response.data.message || '建立失敗');
                }
            },
            error: function () {
                alert('請求失敗，請重試');
            },
            complete: function () {
                $submit.prop('disabled', false);
            }
        });
    });

    $(document).on('submit', '#qo-link-customer-form', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $submit = $form.find('[type="submit"]');
        var $result = $('#qo-link-result');

        $submit.prop('disabled', true);
        $result.hide();

        $.ajax({
            url: quickOrder.ajaxUrl,
            method: 'POST',
            data: $form.serialize() + '&action=quick_order_link_customer',
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    var msg = response.data.linked > 0
                        ? response.data.linked + ' 筆訂單已關聯'
                        : '無待關聯的訂單';
                    $result.html('<div class="notice notice-success"><p>' + msg + '</p></div>').show();
                    $form.find('#qo-link-email').val('');
                } else {
                    $result.html('<div class="notice notice-error"><p>' + (response.data.message || '操作失敗') + '</p></div>').show();
                }
            },
            error: function () {
                $result.html('<div class="notice notice-error"><p>請求失敗，請重試</p></div>').show();
            },
            complete: function () {
                $submit.prop('disabled', false);
            }
        });
    });

    $(document).on('click', '#qo-copy-url', function () {
        var $input = $('#qo-payment-url');
        $input.select();
        document.execCommand('copy');

        var $success = $('#qo-copy-success');
        $success.show();
        setTimeout(function () {
            $success.hide();
        }, 2000);
    });
})(jQuery);
