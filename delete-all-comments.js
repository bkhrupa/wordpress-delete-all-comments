jQuery(function ($) {
    $(document).ready(function () {

        var timerId;

        //var $

        var send = function (sendData, callback) {
            $.post(ajaxurl,
                sendData, function (data) {
                    if (data.success) {
                        callback(data);
                    }
                    else {
                        clearInterval(timerId);
                        alert('Something went wrong. Please, reload page and try again.');
                    }
                });
        };

        var restForm = function () {
            $('#dacFormDeleteOlder :input').prop('disabled', false);
            $('#dacSubmitConfirm').prop('disabled', false);
            $('#dacFormDeleteOlderConfirm').hide(200);
            $('#dacProgress').hide(200);
            $('#dacProgressBar').prop('value', 0);
        };

        restForm();

        $('#dacCancelConfirm').on('click', function (event) {
            event.preventDefault();
            clearTimeout(timerId);
            restForm();
        });

        $('#dacFormDeleteOlder').submit(function (event) {
            event.preventDefault();

            // prepare request params
            var sendData = {
                action: 'dac_prepare_deletion',
                dacOlderDays: $('#dacOlderDays').val(),
                dacOlderLimit: $('#dacOlderLimit').val()
            };

            send(sendData, function (data) {
                $('#dacFormDeleteOlder :input').prop('disabled', true);

                $('#deleteCount').text(data.data.count);
                $('#dacLeftCount').text(data.data.count);
                $('#dacFormDeleteOlderConfirm').show(200);

                if (data.data.count < 1) {
                    $('#dacSubmitConfirm').prop('disabled', true);
                }
            });
        });

        $('#dacFormDeleteOlderConfirm').submit(function (event) {
            event.preventDefault();

            $('#dacProgress').show(200);
            $('#dacSubmitConfirm').prop('disabled', true);

            timerId = setTimeout(function tick() {
                var sendData = {
                    action: 'dac_confirm_deletion',
                    dacOlderDays: $('#dacOlderDays').val(),
                    dacOlderLimit: $('#dacOlderLimit').val()
                };

                send(sendData, function (data) {
                    $('#dacLeftCount').text(data.data.left);
                    $('#dacProgressBar').prop({
                        value: Math.round(100 - data.data.left * 100 / data.data.count)
                    });

                    if (data.data.left < 1) {
                        clearInterval(timerId);
                        alert('Successful deleted "' + data.data.count + ' coments".');
                        location.reload();
                    }
                });

                timerId = setTimeout(tick, 1000);
            }, 1000);
        });
    });

});
