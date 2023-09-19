function check_status(ajax_url) {
    let is_paid = false;

    function status_loop() {
        if (is_paid) return;

        jQuery.getJSON(ajax_url, function (data) {
            if (data.is_pending) {
                jQuery('.payment_details,.payment_complete').hide(200);
                jQuery('.payment_pending,.zanjir_loader').show(200);
            }

            if (data.is_paid) {
                jQuery('.zanjir_loader,.payment_pending,.payment_details').hide(200);
                jQuery('.payment_complete,.zanjir_check').show(200);

                is_paid = true;
            }
        });

        setTimeout(status_loop, 10000);
    }

    status_loop();
}
