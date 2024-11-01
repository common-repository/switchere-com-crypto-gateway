<?php
/**
 * WooCommerce Switchere Payment Gateway.
 *
 * @class       WC_Gateway_Switchere
 * @extends     WC_Payment_Gateway
**/
class WC_Gateway_Switchere extends WC_Payment_Gateway {
    private $API_HOST = " ";
    private $module_url;

    /**
     * Constructor
     */
    public function __construct() {
        $plugin_dir = plugin_dir_url(__FILE__);

        $this->id                 = 'switchere';
        $this->has_fields         = true;
        $this->method_title       = 'Switchere Payment Gateway Plugin';
        $this->method_description = 'Switchere Payment Gateway Plugin.';
        $this->supports           = array( 'products' );

        $this->module_url = plugin_dir_url(dirname(__FILE__));

        $this->icon = apply_filters( 'woocommerce_gateway_icon', $this->module_url . 'assets/images/icon.png' );

        // Load the form fields.
        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );

        // Checking if valid to use
        $this->enabled    = $this->is_available() ? 'yes' : 'no';
        $this->is_sandbox = $this->get_option( 'is_sandbox' );

        // Site URL
        $this->siteUrl      = get_site_url();
        $this->switchereUrl = $this->is_sandbox == 'yes'
            ? "https://sandbox.switchere.com"
            : "https://switchere.com"
        ;

        $this->api_key = $this->get_option( 'api_key' );
        $this->secret = $this->get_option( 'secret' );
        $this->callback_secret  = $this->get_option( 'callback_secret' );

        // This action hook saves the settings
        add_action( 'woocommerce_update_options_payment_gateways_'.$this->id, array( $this, 'process_admin_options' ) );

        add_action( 'woocommerce_api_payment_callback', array( $this, 'payment_callback' ) );
        add_action( 'woocommerce_api_activate_partner_callback', array( $this, 'activate_partner_callback' ) );

        wp_register_style('switchere-settings', $this->module_url . '/assets/css/switchere.css');
        wp_enqueue_style('switchere-settings');
    }

    /**
     * Return whether or not this gateway still requires setup to function.
     *
     * @return bool
     */
    public function needs_setup() {
        foreach ($this->settings as $key => $value) {
            if ( trim($value) === '' ) {
                return true;
            }
        }

        return false;
    }

    public function is_available() {
        return true;
        $args = array(
            'plugin_name' => 'woocommerce',
        );

        $apiUrl = $this->switchereUrl."/api/v2/partner/ecommerce/is_available";
        $response = wp_remote_post( $apiUrl, $args );
        if ( !is_wp_error( $response ) ) {
            $body = json_decode( $response['body'], true );
            if ( $body['is_available'] ) {
                return parent::is_available();
            }
        }
        return false;
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => 'Enable/Disable',
                'label'       => 'Enable Switchere Gateway',
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ),
            'is_sandbox' => array(
                'title'       => 'Activate sandbox mode',
                'label'       => 'Use sandbox Switchere Gateway',
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'yes',
                'custom_attributes' => array(
                    'onclick' => 'document.getElementsByClassName("woocommerce-save-button")[0].click()',
                ),
            ),
            'title' => array(
                'title'        => 'Title',
                'type'         => 'text',
                'placeholder'  => 'Pay with crypto',
                'default'      => 'MyApp',
                'class'        => 'switchere-settings__input',
                'custom_attributes' => array(
                    'maxlength'    => 60,
                    'autocomplete' => 'off',
                ),
            ),
            'description' => array(
                'title'       => 'Description',
                'type'        => 'textarea',
                'placeholder' => 'You can pay with any cryptocurrency',
                'class'       => 'switchere-settings__input switchere-settings__input--description',
                'custom_attributes' => array(
                    'maxlength'    => 150,
                ),
            ),
            'api_key' => array(
                'title'       => 'API-Key',
                'type'        => 'text',
                'placeholder' => 'Enter Switchere API-Key',
                'desc_tip'    => true,
                'class'       => 'switchere-settings__input',
                'custom_attributes' => array(
                    'autocomplete' => 'off',
                ),
            ),
            'secret' => array(
                'title'       => 'Secret',
                'type'        => 'text',
                'placeholder' => 'Enter Switchere Secret',
                'desc_tip'    => true,
                'class'       => 'switchere-settings__input',
                'custom_attributes' => array(
                    'autocomplete' => 'off',
                ),
            ),
            'callback_secret' => array(
                'title'       => 'Callback Secret',
                'type'        => 'text',
                'placeholder' => 'Enter Switchere Callback Secret',
                'desc_tip'    => true,
                'class'       => 'switchere-settings__input',
                'custom_attributes' => array(
                    'autocomplete' => 'off',
                ),
            ),
        );
    }

    public function process_payment( $order_id ) {
        global $woocommerce;

        // To receive order id
        $order = wc_get_order( $order_id );

        // Create a session and send it to Payment platform while handling errors
        $requestBody = array(
            'plugin_name'       => 'woocommerce',
            'partner_order_id'  => $order->get_order_number(),
            'purchase_currency' => get_woocommerce_currency(),
            'purchase_amount'   => $order->get_total(),
            'client_email'      => $order->get_billing_email(),
            'website'           => $this->siteUrl,
        );

        $context = json_encode($requestBody);

        $secret = htmlspecialchars_decode($this->secret);

        $sign = trim(base64_encode(hash_hmac('sha512', hash('sha256', '/api/v2/partner/ecommerce/purchase_url'.$context, true), $secret, true)), '=');

        $header = array(
            'Content-Type' => 'application/json',
            'Api-Key' => $this->api_key,
            'API-Signature' => $sign
        );

        $args = array(
            'method' => 'POST',
            'headers' => $header,
            'body' => $context,
        );

        $apiUrl = $this->switchereUrl."/api/v2/partner/ecommerce/purchase_url";
        $response = wp_remote_post( $apiUrl, $args );

        error_log(is_wp_error( $response ));

        if ( !is_wp_error( $response ) ) {
            $body = json_decode( $response['body'], true );

            error_log(json_encode($body));

            if ( $body['success'] == 1 ) {
                $partner_order_id = $body['partner_order_id'];

                $order->update_status( 'processing');

                $url = $body['url'];

                if ( $url ) {
                    return array(
                        'result'   => 'success',
                        'redirect' => $url
                    );
                } else {
                    wc_add_notice( 'Please try again', 'error' );
                    return;
                }
            } else {
                wc_add_notice( 'Please try again', 'error' );
                return;
            }
        } else {
            wc_add_notice( 'Connection error.', 'error' );
            return;
        }
    }

    public function activate_partner_callback() {
        $post = file_get_contents('php://input');
        $data = json_decode($post, true);

        if ( !$data ) {
            error_log("Oops... Some problems with the auto activation method");
            return;
        }

        $this->update_option( 'api_key', $data['api_key'] );
        $this->update_option( 'secret', $data['secret'] );
        $this->update_option( 'callback_secret', $data['callback_secret'] );
    }

    public function payment_callback() {
        $headers = getallheaders();
        $post = file_get_contents('php://input');
        $data = json_decode($post, true);

        if ( !$data ) {
            error_log("Oops... Some problems with the order callback data");
            return;
        }

        $secret = htmlspecialchars_decode($this->callback_secret);

        $sign = base64_encode(hash_hmac('sha512', hash('sha256', $post, true), $secret, true));

        $headers = array_change_key_case($headers, CASE_LOWER);

        if ($sign != $headers['api-signature']) {
            error_log("Oops... Some problems with the callback sign");
            return;
        }

        $order_data = $data['client_order'];

        if ( $order_data && $order_data['partner_order_id'] ) {
            $order = wc_get_order( $order_data['partner_order_id'] );
            if ( $order ) {
                if ( $order_data['status'] == 'finished' ) {
                    if (empty($order_data['expected_payout_amount']) || $order_data['payout_amount'] == $order_data['expected_payout_amount']) {
                        $order->update_status( 'completed' );
                        $order->payment_complete();
                        $order->reduce_order_stock();
                    } else {
                        $order->update_status( 'on-hold' );
                        if ($order_data['payout_amount'] < $order_data['expected_payout_amount']) {
                            $order->add_order_note( "The customer has paid less than expected\n Received: ".($order_data['payout_amount'] + 0)." ".$order_data['payout_currency']."\n Expected: ".($order_data['expected_payout_amount'] + 0)." ".$order_data['payout_currency'] );
                        } else {
                            $order->add_order_note( "The customer has paid more than expected\n Received: ".($order_data['payout_amount'] + 0)." ".$order_data['payout_currency']."\n Expected: ".($order_data['expected_payout_amount'] + 0)." ".$order_data['payout_currency'] );
                        }
                    }
                } elseif ( $order_data['status'] == 'payin_pending') {
                    $order->update_status( 'pending' );
                } elseif ( $order_data['status'] == 'pending' || $order_data['status'] == 'processing' || $order_data['status'] == 'confirmation_pending' || $order_data['status'] == 'processing_payout' ) {
                    $order->update_status( 'processing' );
                } elseif ( $order_data['status'] == 'hold' || $order_data['status'] == 'frozen' ) {
                    $order->update_status( 'on-hold' );
                } elseif ( $order_data['status'] == 'expired' || $order_data['status'] == 'canceled' ) {
                    $order->update_status( 'cancelled' );
                } elseif ( $order_data['status'] == 'refunded' ) {
                    $order->update_status( 'refunded' );
                } else {
                    $order->update_status( 'failed' );
                }
            } else {
                error_log("Order is not exist. ID: ".$order_data['partner_order_id']);
            }
        } else {
            error_log("Oops... Some problems with the order callback data");
        }
    }

    public function admin_options() {
        $GLOBALS['hide_save_button'] = true;
        ?>
        <div class="switchere-settings">
            <div class="switchere-settings__header">
                <div>
                    <h2 class="switchere-settings__header-title"><?php _e('Switchere Payment Gateway Plugin', 'woocommerce'); ?></h2>
                    <p>Switchere Payment Gateway Plugin</p>
                </div>

                <div>
                    <img
                        class="switchere-settings__logo"
                        src="<?php echo $this->module_url . 'assets/images/logo.svg'; ?>"
                    />
                </div>
            </div>

            <div class="switchere-settings__body">
                <table class="form-table switchere-settings__table" role="presentation">
                    <?php $this->generate_settings_html(); ?>

                    <tr>
                        <td>
                            <p class="submit switchere-settings__buttons">
                                <?php if ( $this->needs_setup() ):?>
                                    <a
                                        class="button button-primary switchere-settings__buttons--activate"
                                        type="button"
                                        <?php echo $this->get_custom_attribute_html( array(
                                            'custom_attributes' => array(
                                                'onclick' => "location.href="."'".$this->switchereUrl."/cabinet?activate_plugin=woocommerce&plugin_name=woocommerce&redirect_url=".$this->siteUrl."'",
                                            ),
                                        ) ); ?>
                                    ><?php esc_html_e( 'Activate', 'woocommerce' ); ?></a>
                                <?php endif ?>

                                <button name="save" class="button button-primary woocommerce-save-button woocommerce-save-button" type="submit" value="<?php esc_attr_e( 'Save changes', 'woocommerce' ); ?>">
                                    <?php esc_html_e( 'Save changes', 'woocommerce' ); ?>
                                </button>

                                <div>
                                    <p style="color: red;"><b>We accept only these fiat currencies: EUR, USD.<br>If you want to use our service with another fiat currency,</b>
                                    <a href = "mailto:merchant@atri.tech?subject=WooCommerce merchant fiat request"><b>contact us</b></a></p>
                                </div>
                            </p>
                        </td>
                    </tr>
                </table>

                <img
                    src="<?php echo $this->module_url . 'assets/images/illustration.png'; ?>"
                    class="switchere-settings__illustration"
                    alt="Switchere"
                />
            </div>
        </div>
        <?php
    }
}















