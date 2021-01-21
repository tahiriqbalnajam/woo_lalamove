<?php
use \Datetime;
/*
Plugin Name: Woo Lalamove Shipping
Plugin URI: https://tahir.codes/
Description: This plugin is to integrate Lalamove shipping to woocommerce
Version: 1.0.1
Author: Tahir Iqbal
Author URI: https://tahir.codes/
*/
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

    function lalamoveidl_shipping_method() {
        if (!class_exists('Lalamoveidl_Shipping_Method')) {

            class Lalamoveidl_Shipping_Method extends WC_Shipping_Method {
                public $newrate;
                public  $lalamove;
                public $text_domain = 'lalamoveshipping';
                public $lalamove_apikey;
                public $lalamove_secret;
                public $storename;
                public $storephone;
                public $lalmove_mode;
                public function __construct( $instance_id = 0 ) {
                  require dirname(__FILE__).'/class_lalamove_main.php';
                  $this->lalamove = new lalamove_main();
                  $this->id = 'lalamoveidl_shipping';
                  $this->instance_id          = absint( $instance_id );
                  $this->method_title         = __('Lalamove Shipping', $this->text_domain);
                  $this->method_description   = __('Plugin for Lalalmove Shipping', $this->text_domain);
                  $this->last_response = '';
                  $this->supports             = array(
                      'shipping-zones',
                      'instance-settings',
                      'instance-settings-modal',
                      'settings'
                  );
                  $this->init();
                }

                /**
                 * Initialize Launch Simple Shipping.
                 */
                public function init() {
                    // Load the settings.
                    $this->init_form_fields();
		                $this->init_settings();
                    // Define user set variables.
                    $this->title      = $this->get_option( 'title' );
                    $this->lalamove_stoername = $this->get_option( 'lalamove_stoername' );
                    $this->lalamove_phone = $this->get_option( 'lalamove_phone' );
                    $this->lalamove_apikey = $this->get_option( 'lalamove_apikey');
                    $this->lalamove_secret = $this->get_option( 'lalamove_secret' );
                    $this->lalmove_mode = $this->get_option( 'lalmove_mode' );
                    add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
                    //add_action( 'woocommerce_after_shipping_rate', array($this, 'action_after_shipping_rate'), 10, 2);
                    //add_action( 'woocommerce_proceed_to_checkout', array( $this, 'action_add_text_before_proceed_to_checkout' ));
                    //add_action( 'woocommerce_proceed_to_checkout', array( $this, 'maybe_clear_wc_shipping_rates_cache' ));
                    //add_action('woocommerce_thankyou', array($this,'lalamove_send_order')); 
                }

                public function action_after_shipping_rate($rate, $index) {
                    $rate_id = $rate->id;
                    $rates = $this->last_response['rates'];
                    foreach( $rates as $r ) {
                        if ( $rate_id == $r['id'] ) { // This rate ID belongs to this instance
                            echo "<div class='shipping_rate_description'>" . $r['description'] . "</div>";
                        }
                    }
                }

                public function maybe_clear_wc_shipping_rates_cache() {
                    $packages = WC()->cart->get_shipping_packages();
                    foreach ($packages as $key => $value) {
                        $shipping_session = "shipping_for_package_$key";
                        unset(WC()->session->$shipping_session);
                    }
                }

                public function action_add_text_before_proceed_to_checkout() {
                    //echo $this->last_response;
                    //echo 'Tahir is here.';
                }

                /**
                 * Init form fields.
                 */
                public function init_form_fields() {
                    $this->instance_form_fields = array(
                        'title'      => array(
                            'title'         => __( 'Title', $this->text_domain ),
                            'type'          => 'text',
                            'description'   => __( 'This controls the title which the user sees during checkout.', $this->text_domain ),
                            'default'       => $this->method_title,
                            'desc_tip'      => true,
                        ),
                        'lalamove_stoername'      => array(
                            'title'         => __( 'Store Name', $this->text_domain ),
                            'type'          => 'text',
                            'description'   => __( 'Store name which will appear on lalamove order.', $this->text_domain ),
                            'default'       => '',
                            'desc_tip'      => true,
                        ),
                        'lalamove_phone'      => array(
                            'title'         => __( 'Store Phone#', $this->text_domain ),
                            'type'          => 'text',
                            'description'   => __( 'Store phone number which will appear on lalamove order.', $this->text_domain ),
                            'default'       => '',
                            'desc_tip'      => true,
                        ),
                        'lalamove_apikey'      => array(
                            'title'         => __( 'API Key', $this->text_domain ),
                            'type'          => 'text',
                            'description'   => __( 'Put Lalamove API key here.', $this->text_domain ),
                            'default'       => '',
                            'desc_tip'      => true,
                        ),
                        'lalamove_secret'      => array(
                          'title'         => __( 'Secret Key', $this->text_domain ),
                          'type'          => 'text',
                          'description'   => __( 'Put Lalamove API secret key here.', $this->text_domain ),
                          'default'       => '',
                          'desc_tip'      => true,
                      ),
                      'lalmove_mode'   => array(
                        'title'   => __( 'Mode (sandbox, live)', $this->text_domain ),
                        'type'    => 'select',
                        'class'   => 'wc-enhanced-select',
                        'default' => '',
                        'options' => array(
                          'sandbox' => __( 'Sandbox', $this->text_domain ),
                          'live' => __( 'Live', $this->text_domain ),
                        ),
                      ),  
                    );
                }

                /**
                 * Get setting form fields for instances of this shipping method within zones.
                 *o=-/
                 * @return array
                 */
                public function get_instance_form_fields() {
                    return parent::get_instance_form_fields();
                }

                /**
                 * Always return shipping method is available
                 *
                 * @param array $package Shipping package.
                 * @return bool
                 */
                public function is_available( $package ) {
                    $is_available = true;
                    return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', $is_available, $package, $this );
                }

                /**
                 * Free shipping rate applied for this method.
                 *
                 * @uses WC_Shipping_Method::add_rate()
                 *
                 * @param array $package Shipping package.
                 */
                public function calculate_shipping( $package = array() ) { 
                  try{
                    $request = $this->lalamove->request($this->lalamove_apikey, $this->lalamove_secret, $this->lalmove_mode, $this->lalamove_stoername, $this->lalamove_phone);
                    if(!$request) {
                        if( ! wc_has_notice( 'Calculate shipping - couldn\'t connect to lalamove api', 'notice' ) ) {
                            wc_add_notice( 'Calculate shipping - couldn\'t connect to lalamove api', 'notice' );
                        }
                      return;
                    }
                    $date = new DateTime();
                    $scheduleAt =  $date->format('Y-m-d\TH:i:s.00\Z');
                    $qbody = $this->lalamove->get_quotation_body($scheduleAt, $package);
                    $response = $request->quotation($qbody);
                    if(!$response) {
                      wc_add_notice( 'no value response'.$response, 'notice' );
                      return;
                    }
                    ob_start();
                    echo '<pre>';
                    print_r($qbody);
                    echo '</pre>';
                    $output = ob_get_contents();
                    ob_end_clean();
                    $code = json_decode($response['code']);
                    $res = json_decode($response['body']);
                    if($code != '200'){
                      wc_add_notice($request->showmsg($res->message).$res->message.$code.$output , 'notice' );
                      return;
                    }
                    else {
                      $_SESSION['order_cost'] = $res->totalFee;
                      $this->add_rate(
                          array(
                              'label'   => $this->title,
                              'cost'    => $res->totalFee,
                              'taxes'   => false,
                              'package' => $package,
                          )
                      );
                    }
                  }
                  catch(Exception $e) {
                      wc_add_notice($e->getMessage(), 'errorrrr');
                  }      
              }
            }
        }
    }
    add_action('woocommerce_shipping_init', 'Lalamoveidl_Shipping_Method');

    function add_lalamoveidl_shipping_method($methods) {
        $methods['lalamoveidl_shipping'] = 'lalamoveidl_shipping_method';
        return $methods;
    }
    add_filter('woocommerce_shipping_methods', 'add_lalamoveidl_shipping_method');
    
    function action_woocommerce_review_order_after_submit($order, $data) {
        global $woocommerce;
        $packages = WC()->shipping->get_packages();
        $chosen_methods = WC()->session->get( 'chosen_shipping_methods', array() );
        foreach ( $chosen_methods as $chosen_method ) {
            $chosen_method = explode( ':', $chosen_method );
            $method_ids[]  = current( $chosen_method );
        }        
        if( is_array( $chosen_methods ) && in_array( 'lalamoveidl_shipping', $method_ids ) ) {         
            
            foreach ( $packages as $i => $package ) {
             if ( $method_ids[ $i ] != "lalamoveidl_shipping" ) {                           
                    continue;                         
                }

                $lalamove_shipping = new Lalamoveidl_Shipping_Method();
                $lalamove_stoername = $lalamove_shipping->settings['lalamove_stoername'];
                $lalamove_phone = $lalamove_shipping->settings['lalamove_phone'];
                $lalamove_apikey = $lalamove_shipping->settings['lalamove_apikey'];
                $lalamove_secret = $lalamove_shipping->settings['lalamove_secret'];
                $lalmove_mode = $lalamove_shipping->settings['lalmove_mode'];
                $date = new DateTime();
                $scheduleAt =  $date->format('Y-m-d\TH:i:s.00\Z');
                $response = $lalamove_shipping->lalamove->lalamove_post_order($lalamove_apikey,  $lalamove_secret, $lalmove_mode, $lalamove_stoername, $lalamove_phone, $scheduleAt, $package);
            
            }       
        } 
    }
    add_action( 'woocommerce_checkout_create_order', 'action_woocommerce_review_order_after_submit', 10, 2 );

}