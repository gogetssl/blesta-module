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

var showActionModal = function() {
    $('#page-modal-cover').css({
        visibility: 'visible',
        opacity: 1
    });
    $('#action-modal').css({visibility: 'visible'});
    $('#action-modal').css({
        top: '10%',
        opacity: 1
    });
    $('#action-modal').addClass('open');
};
var closeActionModal = function() {
    $('#action-modal').css({
        top: '40%',
        opacity: 0,
    });
    $('#action-modal').delay(400).queue(function(next) {
        $('#page-modal-cover').css({
            visibility: 'hidden',
            opacity: 0
        });
        $('#action-modal').css({top: 0, visibility: 'hidden'});
        next();
    });
    $('#action-modal .response').html('');
    $('#action-modal').removeClass('open');
    $('#all-page-content').removeClass('blur');
};

$(function() {
    $('body').on('click', '.instant-modal', function() {
        $('#action-modal .content').html(
            "<div class='modal-title'>" + $(this).attr('data-title') + "</div>" +
            $($(this).attr('data-selector')).html()
        );
        showActionModal();
    });

    $('body').on('click', '#action-modal .close-button', function() {
        closeActionModal();
    });

    $(document).keydown(function(e) {
        if (e.keyCode == 27 && $('#action-modal').hasClass('open')) {
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

    $('body').on('change', '.dcv-method-select', function() {
        var tr = $(this).parents('tr');
        var table = tr.parents('table');

        if ($(this).val() == 'EMAIL') {
            tr.find('.domain-select').show();
            table.find('.email-th').show();
        } else {
            tr.find('.domain-select').hide();
            table.find('.email-th').hide();
        }
    });

    $('body').on('click', '.perform-ajax-action', function(event) {
        var button = $(this);
        var data = button.parents('form').length ? button.parents('form').serializeArray() : {};

        event.preventDefault();

        ajax(button.data('url'), 'POST', data, null, function (response) {
            try {
                var json = JSON.parse(response);
                var isModalOpen = $('#action-modal').hasClass('open');

                if (isModalOpen) {
                    if (json.status == 'success') {
                        closeActionModal();
                    } else {
                        $('#action-modal').find('.alert').remove();
                        $(json.message).insertAfter('#action-modal .content .modal-title');
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