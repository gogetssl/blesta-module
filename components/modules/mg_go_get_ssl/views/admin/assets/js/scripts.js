$(function() {
    $('#plugin_support_manager_admin_tickets_client').css('z-index', 0);
    $('#admin_clients_transactions').css('z-index', 0);
});

var ajax = function (url, type, data, before, success, complete, error) {
    if (!type) {
        type = 'POST';
    }
    if (!data) {
        data = {};
    }

    $.ajax({
        url: url,
        type: type,
        data: data,

        beforeSend: function () {
            if (typeof before == 'function') {
                before();
            }
        },
        success: function (response) {
            if (typeof success == 'function') {
                success(response);
            }
        },
        complete: function () {
            if (typeof complete == 'function') {
                complete();
            }
        },
        error: function () {
            if (typeof error == 'function') {
                error();
            }
        },

        statusCode: {
            401: function (response) {
                alert('You are not allowed to perform this action');
            }
        }
    });
};

var enablePricePeriod = function(checkbox) {
    var period = checkbox.data('period');
    var container = checkbox.parents('.pricing-in-currency');
    var prices = container.find('.period-price[data-period="' + period + '"]');
    var setupFees = container.find('.period-setup-fee-price[data-period="' + period + '"]');

    if (checkbox.is(':checked')) {
        prices.find('input').show();
        setupFees.find('input').show();
    } else {
        prices.find('input').hide();
        setupFees.find('input').hide();
    }
};

var showActionModal = function(container) {
    container.find('.page-modal-cover').css({
        visibility: 'visible',
        opacity: 1
    });
    container.find('.action-modal').css({visibility: 'visible'});
    container.find('.action-modal').css({
        top: '10%',
        opacity: 1
    });
    container.find('.action-modal').addClass('open');
    container.find('#admin_clients_transactions').css({top: -1, visibility: 'hidden'});
};
var closeActionModal = function() {

    $('.action-modal').css({
        top: '40%',
        opacity: 0,
    });
    $('.action-modal').delay(400).queue(function(next) {
        $('.page-modal-cover').css({
            visibility: 'hidden',
            opacity: 0
        });
        $('.action-modal').css({top: 0, visibility: 'hidden'});
        next();
    });
    $('.action-modal .response').html('');
    $('.action-modal').removeClass('open');

};

$(function() {
    $('body').off('click', '.instant-modal').on('click', '.instant-modal', function() {
        $('.action-modal .content').html(
            "<div class='modal-title'>" + $(this).attr('data-title') + "</div>" +
            $($(this).attr('data-selector')).html()
        );
        $('.action-modal').find('.alert').remove();

        showActionModal($(this).parents('.border-box-sizing'));
    });

    $('body').on('click', '.action-modal .close-button', function() {
        closeActionModal();
    });

    $(document).keydown(function(e) {
        if (e.keyCode == 27 && $('.action-modal').hasClass('open')) {
            closeActionModal();
        }
    });

    $('body').on('click', '.preloader', function(event) {
        var input = $(this);

        input.addClass('loading');

        setTimeout(function() {
            input.attr('disabled', 'disabled');
        }, 100);
    });

    // ----- ----- -----

    $('input:checkbox[name="use_admin_contact"]').click(function() {
        if ($(this).is(':checked')) {
            $(this).parents('ul').find('input, select').not($(this)).attr('readonly', 'readonly').css({background: 'whitesmoke'});
        } else {
            $(this).parents('ul').find('input, select').not($(this)).removeAttr('readonly').css({background: 'white'});
        }
    });

    if ($('input:checkbox[name="use_admin_contact"]').length && $('input:checkbox[name="use_admin_contact"]').is(':checked')) {
        $('input:checkbox[name="use_admin_contact"]')
            .parents('ul').find('input, select').not($(this)).attr('readonly', 'readonly').css({background: 'whitesmoke'});
    }

    $('body').on('change', '.dcv-method-select', function() {
        var tr = $(this).parent('td').parent('tr');
        var table = tr.parents('table');

        if ($(this).val() == 'EMAIL') {
            tr.find('.domain-select').show();
            table.find('.email-th').show();
        } else {
            tr.find('.domain-select').hide();
            table.find('.email-th').hide();
        }
    });

    $('.enable-price-period').each(function() {
        enablePricePeriod($(this));
    });

    $('.enable-price-period').click(function() {
        enablePricePeriod($(this));
    });

    $('.change-package-status').click(function(event) {
        var button = $(this);
        var li = button.parents('li');

        event.preventDefault();

        ajax(button.data('href'), 'POST', {}, function() {
            li.css({opacity: 0.3});
        }, function(response) {
            try {
                var json = JSON.parse(response);
                var label = li.find('.status-label');
                var messages = $('.module-messages');

                if (json.status == 'success') {
                    label.removeClass('status-label-active status-label-inactive status-label-restricted');
                    label.addClass('status-label-' + json.package_status).text(json.package_status_text);

                    if (json.package_status == 'active') {
                        button.text(button.data('text-deactivate'));
                    } else {
                        button.text(button.data('text-activate'));
                    }
                } else {
                    messages.html(json.message);
                }
            } catch (Err) {
                console.log(Err);
            }
        }, function() {
            li.css({opacity: 1});
        }, function() {
            li.css({opacity: 1});
        });
    });

    $('.test-connection-button').click(function(event) {
        var button = $(this);
        var li = button.parents('li');
        var username = $('input:text[name="api_username"]').val();
        var password = $('input:password[name="api_password"]').val();

        event.preventDefault();

        ajax(button.data('href'), 'POST', {
            username: username,
            password: password
        }, function() {
            li.css({opacity: 0.3});
        }, function(response) {
            try {
                var json = JSON.parse(response);
                var rand = Math.floor((Math.random() * 100000000) + 1);
                var message = '<div id="' + rand + '">' + json.message + '</div>';

                $('.module-messages').html('').append(message);

                setTimeout(function() {
                    $('#' + rand).remove();
                }, 6000);
            } catch (Err) {
                console.log(Err);
            }
        }, function() {
            li.css({opacity: 1});
        }, function() {
            li.css({opacity: 1});
        });
    });

    $('.pay-type-select').change(function() {
        var billingCycle = $(this).val();
        var productPricing = $(this).parents('.pad').find('.product-pricing');
        var prices = productPricing.find('.period-price[data-period="annually"], .period-price[data-period="biennially"]');
        var setupFees = productPricing.find('.period-setup-fee-price[data-period="annually"], .period-setup-fee-price[data-period="biennially"]');
        var enablePricePeriods = productPricing.find('.enable-price-period[data-period="annually"], .enable-price-period[data-period="biennially"]');
        var monthlyEnablePricePeriods = productPricing.find('.enable-price-period[data-period="monthly"]');

        productPricing.show();

        if (billingCycle == 'one_time') {
            enablePricePeriods.hide();
            monthlyEnablePricePeriods.show();

            prices.find('input').hide();
            setupFees.find('input').hide();
            enablePricePeriods.attr('checked', false).prop('checked', false);
        } else if (billingCycle == 'recurring') {
            enablePricePeriods.show();
            monthlyEnablePricePeriods.show();
        } else if (billingCycle == 'free') {
            productPricing.hide();
        }
    });

    $('body').off('click', '.perform-ajax-action').on('click', '.perform-ajax-action', function(event) {
        var button = $(this);
        var data = button.parents('form').length ? button.parents('form').serializeArray() : {};

        event.preventDefault();

        ajax(button.data('url'), 'POST', data, null, function (response) {
            try {
                var json = JSON.parse(response);
                var isModalOpen = button.parents('.border-box-sizing').find('.action-modal').hasClass('open');

                if (isModalOpen) {
                    if (json.status == 'success') {
                        closeActionModal();
                    } else {
                        button.parents('.border-box-sizing').find('.action-modal').find('.alert').remove();
                        $(json.message).insertAfter('.action-modal .content .modal-title');
                        return;
                    }
                }

                if (json.refresh) {
                    setTimeout(function() {
                        window.location.reload();
                    }, 5000);
                }

                $('.module-messages').html(json.message);
            } catch (Err) {
                console.log(Err);
            }
        }, function () {
            button.removeClass('loading').removeAttr('disabled');
        }, function() {
            button.removeClass('loading').removeAttr('disabled');
        });
    });
});