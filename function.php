<?php
class WC_Zanjir_Gateway extends WC_Payment_Gateway
{
    private static $HAS_TRIGGERED = false;
    private static $COIN_SUPPORTED = [];
    private static $COIN_MIN_AMOUNT_SUPPORT = [];

    function __construct()
    {
        $this->id = 'zanjir';
        $this->has_fields = true;
        $this->method_title = 'Zanjir';
        $this->method_description = 'Zanjir allows customers to pay in cryptocurrency';

        $this->supports = array(
            'products',
            'tokenization',
            'add_payment_method',
        );

        $this->coin_supported();

        $this->init_form_fields();
        $this->init_settings();
        $this->zanjir_settings();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'process_page'));

        add_action('wp_ajax_nopriv_' . $this->id . '_order_status', array($this, 'order_status'));
        add_action('wp_ajax_' . $this->id . '_order_status', array($this, 'order_status'));

        add_action('wp_ajax_nopriv_' . $this->id . '_process_callback', array($this, 'process_callback'));
        add_action('wp_ajax_' . $this->id . '_process_callback', array($this, 'process_callback'));

    }

    function coin_supported() {
        $coin_list = (new Zanjir\Zanjir)->coin_list();
        foreach ($coin_list as $proto) {
            foreach($proto as $ticker=>$value){
                $coins[$ticker] = $value->title;
                $amount_support[$ticker] = $value->min_amount;
            }
        }
        WC_Zanjir_Gateway::$COIN_SUPPORTED = $coins;
        WC_Zanjir_Gateway::$COIN_MIN_AMOUNT_SUPPORT = $amount_support;
    }


    private function zanjir_settings() {
        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->exchange = $this->get_option('exchange') === 'yes';
        $this->coins = $this->get_option('coins');
        $this->currency = $this->get_option('currency');
        foreach(WC_Zanjir_Gateway::$COIN_SUPPORTED as $ticker=>$title) {
            $this->{$ticker . '_address'} = $this->get_option($ticker . '_address');
        }
    }

    function init_form_fields()
    {
    
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enabled', 'zanjir'),
                'type' => 'checkbox',
                'label' => __('Enable Zanjir Payments', 'zanjir'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Title', 'zanjir'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'zanjir'),
                'default' => __('Cryptocurrency', 'zanjir'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title'        => __('Description', 'zanjir'),
                'type'         => 'textarea',
                'default'      => '',
                'description'  => __('Payment method description that the customer will see on your checkout', 'zanjir' )
            ),
            'exchange' => array(
                'title' => __('Auto price exchange', 'zanjir'),
                'type' => 'checkbox',
                'label' => __("If you enable this feature, the price of your products will be automatically converted to the desired coin amount. (We suggest it is active) your shop currency : ".get_woocommerce_currency()  , 'zanjir'),
                'default' => 'yes'
            ),
            'currency' => array(
                'title' => __('currency', 'zanjir'),
                'type' => 'select',
                'default' => get_woocommerce_currency(),
                'options' => ["USD"=>"US Dollar (USD)",
                "EUR"=>"Euro (EUR)",
                "AED"=>"Emirati Dirham (AED)",
                "GBP"=>"Pounds (GBP)",
                "AUD"=>"Australian Dollar (AUD)",
                "CAD"=>"Canadian dollar (CAD)",
                "TRY"=>"Turkish Lira (TRY)",
                "MYR"=>"Malaysian Ringgit (MYR)",
                "INR"=>"Indian Rupee (INR)",
                "IRR"=>"Iranian rial (IRR)"],
                'description' => __("Note: If the automatic exchange rate conversion is active, you must select your currency according to the store currency.", 'zanjir'),
            ),
            'coins' => array(
                'title' => __('Accepted cryptocurrencies', 'zanjir'),
                'type' => 'multiselect',
                'default' => '',
                'css' => 'height: 15em;',
                'options' => WC_Zanjir_Gateway::$COIN_SUPPORTED,
                'description' => __("Select which coins do you wish to accept. CTRL + click to select multiple", 'zanjir'),
            )

        );

        foreach (WC_Zanjir_Gateway::$COIN_SUPPORTED as $ticker=>$title) {
            $this->form_fields["{$ticker}_address"] = array(
                'title' => __("{$title} Address", 'zanjir'),
                'type' => 'text',
                'description' => __("Insert your {$title} address here. Leave blank if you want to skip this cryptocurrency", 'zanjir'),
                'desc_tip' => true,
            );
        }

    }


    function payment_fields()
        { ?>
        <div class="form-row form-row-wide">
            <p><?php esc_html_e($this->description,'Zanjir'); ?></p>
            <ul style="list-style: none outside;">
                <?php
        if (!empty($this->coins) && is_array($this->coins)) {
            foreach ($this->coins as $ticker) {
                $wallet_address = $this->{$ticker . '_address'};
                if (!empty($wallet_address)) { ?>
                            <li>
                                <input id="payment_method_<?php  esc_html_e($ticker,'Zanjir'); ?>" type="radio" class="input-radio"
                                       name="Zanjir_coin" value="<?php esc_html_e($ticker,'Zanjir'); ?>"/>
                                <label for="payment_method_<?php esc_html_e($ticker,'Zanjir'); ?>" style="display: inline-block;"><?php  esc_html_e('Pay with ' . WC_Zanjir_Gateway::$COIN_SUPPORTED[$ticker] , 'Zanjir'); ?></label>
                            </li>
                            <?php
                }
            }
        } ?>
            </ul>
        </div>
        <?php
    }

    function process_payment($order_id)
    {
        global $woocommerce;

        $ticker = sanitize_text_field($_POST['Zanjir_coin']);
        $wallet_address = $this->{$ticker . '_address'};
        if (!empty($wallet_address)) {
        
            $callback_url = add_query_arg(array(
                    'action' => 'zanjir_process_callback',
                    'order_id' => $order_id,
                ), home_url('/wp-admin/admin-ajax.php'));

            try {
                $order = new WC_Order($order_id);
                $total_amount = $order->get_total('edit');
                $currency = get_woocommerce_currency();

                $params["amount"] = $total_amount;
                $params["address"] = $wallet_address;
                $params["ticker"] = $ticker;
                if($this->exchange){
                    $params["currency"] = $this->currency;
                }
                $params["callback"] = $callback_url;

                $zanjir = (new Zanjir\Zanjir)->create($params);

                if($zanjir->status != 1000){
                    wc_add_notice(__("Payment error: " . (new Zanjir\Zanjir)->error_dictionary($zanjir->status), 'Zanjir'), 'error');
                    return null;
                }

                $qr_code_data = (new Zanjir\Zanjir)->qrcode_base64($zanjir->in_wallet,$zanjir->amount);
                $order->add_meta_data('zanjir_nonce', $zanjir->nonce);
                $order->add_meta_data('zanjir_adminwallet', $wallet_address);
                $order->add_meta_data('zanjir_address', $zanjir->in_wallet);
                $order->add_meta_data('zanjir_amount', $zanjir->amount);
                $order->add_meta_data('zanjir_amount_fiat', $total_amount);
                $order->add_meta_data('zanjir_ticker', $zanjir->ticker);
                $order->add_meta_data('zanjir_title', WC_Zanjir_Gateway::$COIN_SUPPORTED[$zanjir->ticker]);
                $order->add_meta_data('zanjir_qr_code', $qr_code_data);
                $order->save_meta_data();

                $order->update_status('on-hold', __('Awaiting payment: ' . WC_Zanjir_Gateway::$COIN_SUPPORTED[$zanjir->ticker], 'Zanjir'));
                $woocommerce->cart->empty_cart();

                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                );

            } catch (Exception $e) {
                wc_add_notice(__("Payment error: Unknown coin", 'Zanjir'), 'error');
                return null;
            }
        }

        wc_add_notice(__('Payment error:', 'woocommerce') . __("Payment could not be processed, please try again", 'Zanjir'), 'error');
        return null;
    }

    function process_page($order_id)
    {
        if (WC_Zanjir_Gateway::$HAS_TRIGGERED) return;
        WC_Zanjir_Gateway::$HAS_TRIGGERED = true;

        $order = new WC_Order($order_id);
        $total = $order->get_total();
        $currency_symbol = get_woocommerce_currency_symbol();
        $zanjir_address = $order->get_meta('zanjir_address');
        $zanjir_amount = $order->get_meta('zanjir_amount');
        $zanjir_ticker = $order->get_meta('zanjir_ticker');
        $zanjir_title = $order->get_meta('zanjir_title');
        $qr_code_base64 = $order->get_meta('zanjir_qr_code');
        $zanjir_amount_fiat = $order->get_meta('zanjir_amount_fiat');


        $ajax_url = add_query_arg(array(
                'action' => 'zanjir_order_status',
                'order_id' => $order_id,
            ), home_url('/wp-admin/admin-ajax.php'));

        wp_enqueue_script('zanjir-payment', ZANJIR_PLUGIN_URL . 'static/payment.js', array(), false, true);
        wp_add_inline_script('zanjir-payment', "jQuery(function() {let ajax_url = '{$ajax_url}'; setTimeout(function(){check_status(ajax_url)}, 500)})");
        wp_enqueue_style('zanjir-loader-css', ZANJIR_PLUGIN_URL . 'static/zanjir.css');

?>

        <div class="payment-panel">
            <div class="zanjir_loader">
                <div>
                    <div class="lds-css ng-scope">
                        <div class="lds-dual-ring">
                            <div></div>
                            <div>
                                <div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="zanjir_check">
                <img src="<?php esc_html_e(ZANJIR_PLUGIN_URL . 'static/success.png','Zanjir');  ?>"/>
            </div>
            <div class="payment_details">
                <h4><?php  _e('Waiting for payment', 'Zanjir') ?></h4>
                <div class="qrcode_wrapper">
                    <div class="inner-wrapper">
                            <img src="<?php esc_html_e($qr_code_base64,'Zanjir'); ?>" />
                    </div>

                </div>
                <div class="details_box">
                    <?php  _e('In order to confirm your order, please send', 'Zanjir') ?>
                    <span><b><?php esc_html_e($zanjir_amount,'Zanjir'); ?></b></span>
                    <span><b><?php strtoupper(esc_html_e($zanjir_title,'Zanjir')); ?></b></span>
                    <?php if($this->exchange){esc_html_e("({$currency_symbol}{$zanjir_amount_fiat})",'Zanjir');} ?>
                    <?php  _e('to', 'Zanjir') ?>
                    <span><b><?php esc_html_e($zanjir_address,'Zanjir'); ?></b></span>
                </div>
            </div>
            <div class="payment_complete">
                <h4><?php  _e('Your payment has been confirmed!', 'Zanjir') ?></h4>
            </div>
        </div>
        <?php
    }

    function process_callback()
    {    
        $input_order_id = sanitize_text_field($_GET['order_id']);
        $input_nonce = sanitize_text_field($_POST['nonce']);
        $input_amount = sanitize_text_field($_POST['amount']);
        $input_in_wallet = sanitize_text_field($_POST['in_wallet']);
        $input_txid = sanitize_text_field($_POST['txid']);
        
        $order = new WC_Order($input_order_id);
        if ($order->is_paid() || $input_nonce != $order->get_meta('zanjir_nonce')) die("Error");
        if ($input_amount >= $order->get_meta('zanjir_amount')) {
            $zanjir = (new Zanjir\Zanjir)->logs($input_in_wallet);
            if($zanjir->confirmations){
            $order->payment_complete($input_in_wallet);
            $order->add_order_note("TxID : " . $input_txid);
            $order->add_meta_data('fee', $zanjir->fee);
            $order->save_meta_data();
            }
        }

        die("*ok*");
    }

    function order_status()
    {
        $order_id = sanitize_text_field($_REQUEST['order_id']);
        try {
            $order = new WC_Order($order_id);
            $data = [
            'is_paid' => $order->is_paid(),
            ];
            _e(json_encode($data),'Zanjir');
            die();

        } catch (Exception $e) {
           _e(json_encode(['status' => 'error', 'error' => 'error'],'Zanjir'));
        }
        _e(json_encode(['status' => 'error', 'error' => 'not a valid order_id']),'Zanjir');
        die();
    }
}
?>