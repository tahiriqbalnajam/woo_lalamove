<?php
if (!class_exists('lalamove_main')) {
  class lalamove_main {
    private $lala_key;
    private $lala_secret;
    private $lala_url;
    private $lala_country;
    private $gmap_key;

    public function __construct() {

      $this->lala_key = '';
      $this->lala_secret = '';
      $this->lala_url = 'https://sandbox-rest.lalamove.com';
      $this->gmap_key = '';
      $this->lala_country = 'PH_CEB';

    }
    public function request() {
      require dirname(__FILE__).'/vendor/autoload.php';
      $request = new \Lalamove\Api\LalamoveApi($this->lala_url, $this->lala_key, $this->lala_secret, $this->lala_country);
      if($request)
        return $request;
      else
        return false;
    }
    public function get_store_address() {
      global $woocommerce;
      $store_address     = get_option( 'woocommerce_store_address' );
      $store_address_2   = get_option( 'woocommerce_store_address_2' );
      $store_city        = get_option( 'woocommerce_store_city' );
      $store_postcode    = get_option( 'woocommerce_store_postcode' );
      $store_country = $woocommerce->countries->countries[$woocommerce->countries->get_base_country()];
      $store_state_code  =  $woocommerce->countries->get_base_state();
      $customer_state  = $woocommerce->countries->get_states( $woocommerce->countries->get_base_country() )[$store_state_code];
      $saddress = array($store_address, $store_city, $store_state, $store_country);
      $saddress_filter = array_filter($saddress, function($value) { return !is_null($value) && $value !== ''; });
      $storeaddress = implode(',', $saddress_filter);
      return $storeaddress;
    }

    public function get_customer_address() {
      global $woocommerce;
      $customer_address1 = $woocommerce->customer->get_shipping_address();
      $customer_country  = $woocommerce->countries->countries[$woocommerce->customer->get_shipping_country()];
      $customer_state_array    =  $woocommerce->countries->get_states($woocommerce->customer->get_shipping_country());
      $customer_state = $woocommerce->countries->get_states($woocommerce->customer->get_shipping_country() )[$woocommerce->customer->get_shipping_state()];
      $customer_postcode = $woocommerce->customer->get_shipping_postcode();
      $customer_city     = $woocommerce->customer->get_shipping_city();
      $customer_address = "$customer_address1, $customer_city, $customer_state,  $customer_country";
      if($customer_address1 == '' ||  $customer_country == '' || $customer_state == '' || $customer_city == '') {
        wc_add_notice( 'Your address is not valid for lalamove.'.$customer_address, 'notice' );
        return;
      }
      return $customer_address;
    }

    public function get_service_type($package) {
      $total_weight = 0;
      foreach ( $package['contents'] as $item_id => $values ) {
        $_product = $values['data'];
        $weight = (int)$_product->get_weight();
        $total_weight += $weight;

      }
      $serviceType = 'MOTORCYCLE';
      if($total_weight > 20)
        $serviceType = "MPV";
      elseif($total_weight > 300)
        $serviceType = "VAN";
      elseif($total_weight > 600)
        $serviceType = "TRUCK330";
      elseif($total_weight > 1000) {
        wc_add_notice("Weight exceeded lalamove Shipment restrictions" , 'notice' );
        return;
      }
      return $serviceType;
    }

    public function get_lat_lng($address) {
      $address = urlencode($address);
      $json_latlng = file_get_contents("https://maps.googleapis.com/maps/api/geocode/json?address=".$address."&key=".$this->gmap_key);
      $latlng = json_decode($json_latlng);
      $lat = $latlng->results[0]->geometry->location->lat;
      $lng = $latlng->results[0]->geometry->location->lng;
      if(empty($lat) || empty($lng)) {
        wc_add_notice( 'System was not able to get lat, long.'."https://maps.googleapis.com/maps/api/geocode/json?address=".$address."&key=".$this->gmap_key ,'notice' );
        return;
      }
      return array('lat' => $lat, 'lng' => $lng);
    }

    public function get_quotation_body($scheduleAt, $pakage = '',  $order =false ) {
      global $woocommerce;
      $specialRequests = array();
      $chosen_payment_method = WC()->session->get('chosen_payment_method');
      if($chosen_payment_method == 'cod')
        $specialRequests[] = "COD";
      $pakage = ($pakage) ? $pakage : $_SESSION['lala_pakage'];
      $serviceType = $this->get_service_type($package);
      $saddress = $this->get_store_address();
      //$slatlng = $this->get_lat_lng($saddress);
      $caddress = $this->get_customer_address();
      //$clatlng = $this->get_lat_lng($caddress);

      $date = new DateTime('now', new DateTimeZone('Asia/Manila'));
      $date->add(new DateInterval('PT1H'));
      $scheduleAt =  $date->format('Y-m-d\TH:i:s.00\Z');  
      $body = array(
        "scheduleAt" =>  "$scheduleAt", // ISOString with the format YYYY-MM-ddTHH:mm:ss.000Z at UTC time
        "serviceType" => "$serviceType",                              // string to pick the available service type
        "specialRequests" => $specialRequests,                               // array of strings available for the service type
        "requesterContact" => array(
          "name" => "Draco Yam",
          "phone" => "09051234567"                                  // Phone number format must follow the format of your country
        ),  
        "stops" => array(
          array(
            
            "addresses" => array(
              "en_PH" => array(
                "displayString" => " $saddress",
                "country" => "$this->lala_country"                                  // Country code must follow the country you are at
              )   
            )   
          ),  
          array(
            
            "addresses" => array(
              "en_PH" => array(
                //"displayString" => "United Hills Village (UPS 1) 1700 Paranaque Bridge Paranaque Metro Manila Philippines",
                "displayString" => "$caddress",
                "country" => "$this->lala_country"                                  // Country code must follow the country you are at
              )   
            )   
          )   
        ),  
        "deliveries" => array(
          array(
            "toStop" => 1,
            "toContact" => array(
              "name" => "Brian Garcia",
              "phone" => "09051234566"                              // Phone number format must follow the format of your country
            ),  
            "remarks" => "ORDER #: 1234, ITEM 1 x 1, ITEM 2 x 2"
          )   
        )   
      );

      if($order) {
        $order_body = array("quotedTotalFee" => array(
          "amount" => $_SESSION['order_cost'],
          "currency" => "PHP"
        ));
        
        $body = array_merge($body,$order_body);
      }

      return $body;
    }

    public function make_request_body() {
      try{
        

        if(!$request) {
          wc_add_notice( 'no request', 'notice' );
          return;
        }
        $qbody = $this->get_quotation_body($scheduleAt, $serviceType, $specialRequests, $saddress, $slatlng, $caddress, $clatlng);
        
      }
      catch(Exception $e) {
          wc_add_notice($e->getMessage(), 'errorrrr');
      }
    }

    public function lalamove_post_order() {
      try{
          require dirname(__FILE__).'/vendor/autoload.php';
          $request = new \Lalamove\Api\LalamoveApi($this->lala_url, $this->lala_key, $this->lala_secret, $this->lala_country);
          
          if(!$request) {
            wc_add_notice( 'no request', 'notice' );
            return;
          }

          $qbody = $this->get_quotation_body($scheduleAt, $serviceType, $specialRequests, $saddress, $caddress, true);
          print_r($qbody);
          $response = $request->postOrder($qbody);
          if(!$response) {
            wc_add_notice( 'no value response'.$response, 'notice' );
            return;
          }
      }
      catch(Exception $e) {
          wc_add_notice($e->getMessage(), 'errorrrr');
      }
    }

  }
}