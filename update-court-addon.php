<?php 
/* nkg_jet_edit_profile_user */

add_action('jet-form-builder/custom-action/nkg_jet_edit_profile_users', 'nkg_jet_edit_profile_user_callback',100);

function nkg_jet_edit_profile_user_callback($request) {
    // Your code here
	
    $log_dir = plugin_dir_path( __FILE__ ) . 'logs/';
    if ( ! file_exists( $log_dir ) ) {
        mkdir( $log_dir, 0755, true );
    }
    $log_file = $log_dir . 'plugin.log';
    error_log( print_r( $request, true ), 3, $log_file );
    if(isset($request['change_court_name']) && $request['change_court_name'] == 'yes') {

        $user_id = $request['stu_id'];
        $user_selected_court = $request['user_selected_court'];
        $new_court_name = $request['new_court_name'];

        $new_court_total_fee = 0;
        $selected_total_fee = 0;
        $orderids = array(); 

        $args = array(
            'post_type' => 'court-name',
            'title'     => $user_selected_court,
            'post_status' => 'publish',
            'posts_per_page' => 1
        );
        $selected_query = new WP_Query($args);
        if ( !empty($selected_query->posts) ) {

            $selected_court_name_id = $selected_query->posts[0]->ID;
            $school_fee = get_post_meta($selected_court_name_id, 'school_fee', true);
            $court_fee = get_post_meta($selected_court_name_id, 'court_fee', true);
            $process_fee = get_post_meta($selected_court_name_id, 'process_fee', true);
            $state_surcharge_fee = get_post_meta($selected_court_name_id, 'state_surcharge_fee', true);
            $state_fee = get_post_meta($selected_court_name_id, 'state_fee', true);
             $online_fee = get_post_meta($selected_court_name_id, 'online_fee', true);

           // $selected_total_fee = $school_fee + $court_fee + $process_fee + $state_surcharge_fee + $state_fee ;
            $selected_total_fee = $online_fee;
              
        }
        
        $args = array(
            'post_type' => 'court-name',
            'title'     => $new_court_name,
            'post_status' => 'publish',
            'posts_per_page' => 1
        );

        $query = new WP_Query($args);

        if ( !empty($query->posts) ) {

            $new_court_name_id = $query->posts[0]->ID;
            $school_fee = get_post_meta($new_court_name_id, 'school_fee', true);
            $court_fee = get_post_meta($new_court_name_id, 'court_fee', true);
            $process_fee = get_post_meta($new_court_name_id, 'process_fee', true);
            $state_surcharge_fee = get_post_meta($new_court_name_id, 'state_surcharge_fee', true);
            $state_fee = get_post_meta($new_court_name_id, 'state_fee', true);
            $online_fee = get_post_meta($new_court_name_id, 'online_fee', true);

            //$new_court_total_fee = $school_fee + $court_fee + $process_fee + $state_surcharge_fee + $state_fee + $online_fee;
            $new_court_total_fee = $online_fee;

        }

        if ($selected_total_fee != $new_court_total_fee) {


            $orderids = get_orders_with_product( $user_id, $selected_court_name_id );

           
            $order_total = $new_court_total_fee - $selected_total_fee;
            // $order_total = 100; // for testing purpose

            if( $order_total > 0 ) {
                // Create a new order                 
               
                $product_id = $new_court_name_id; // Assuming the product ID is the same as the court name post ID
                $order_id = eb_create_custom_order_for_user( $user_id, $product_id, $order_total);
                echo "<br> Order ID: " . $order_id;
                
                if($order_id){

                    $order = wc_get_order( $order_id );
                    // notify user about court name change
                    $payment_url = $order->get_checkout_payment_url();
                    $refund = false;
                    eb_send_email_notification( $user_id, $new_court_name, $user_selected_court,$payment_url,$refund  );
                    // Update order meta to indicate court name change
                    $order->update_meta_data( 'court_name_changed', 'yes' );
                    $order->update_meta_data( 'old_court_id', $selected_court_name_id );
                    $order->update_meta_data( 'old_order_id', $orderids[0] );
                    $order->save();

                }


            }


            if($order_total <= 0 && !empty($orderids)) {
 
                $order_id = $orderids[0];
                $order = wc_get_order( $order_id );
                $is_court_name_changed = $order->get_meta( '_court_name_changed' );
                if(empty($is_court_name_changed)) {

                    $payment_gateway_id = $order->get_payment_method();
                    $payment_gateway    = WC_Payment_Gateways::instance()->payment_gateways()[ $payment_gateway_id ] ?? null;

                    // Check if gateway supports automatic refunds
                    $supports_refund = $payment_gateway && method_exists( $payment_gateway, 'process_refund' ) && $payment_gateway->supports( 'refunds' );
                    
                    $refund_amount = abs($order_total);
                    $arg_refund = array(
                        'order_id'     => $order_id,
                        'amount'       => $refund_amount,
                        'reason'       => 'Refund for court name change',
                        'line_items'   => array(), // optional
                        
                    );
                    if($supports_refund){
                        $arg_refund['refund_payment'] = true; // This triggers gateway refund
                    }
                    $refund_res = wc_create_refund( $arg_refund );
                    if($refund_res){
                        // Refund successful
                        // email notification
                        $payment_url = "";
                        $refund = true;
                        eb_send_email_notification( $user_id, $new_court_name, $user_selected_court,$payment_url,$refund  );
                        $order->update_meta_data( '_court_name_changed', 'yes' );
                        $order->save();
                    } 
                } 

            }



        }else{

            $payment_url = "";
            $refund = false;
            eb_send_email_notification( $user_id, $new_court_name, $user_selected_court,$payment_url,$refund  );

        }






    } else {

        error_log("No court name change requested.", 3, $log_file);

    }
   

	
}

add_action('init', 'nkg_jet_edit_profile_user_init');
function nkg_jet_edit_profile_user_init() {
    if(isset($_GET['nkg_jet_edit_profile_user']) && $_GET['nkg_jet_edit_profile_user'] == 'yes') {

    /*
        $order = wc_get_order( 6340 );
        echo "<pre>";
        foreach ( $order->get_items() as $item_id => $item ) {
             
                $product_id = $item->get_product_id();
                $allmeta = $item->get_meta_data();
            print_r($item_id);
               echo "<br> product_id : ".$product_id.'<br> ';
               print_r($allmeta);
             wc_update_order_item_meta($item_id, '_product_id', 5794);
            //  $item->add_meta_data( '_product_id', $product_id, true );
            //  $item->save();
        } 
        //$order->calculate_totals();
        $order->save();

        print_r($order->get_meta_data());
        die();*/
        

        $user_id = 52;

        $user_selected_court = '0714 - Agua Fria Justice';
        $new_court_name = '0100 - APACHE CO. SUP/JUVENILE';
        // get posttype "court-name" by name 
         
        $new_court_total_fee = 0;
        $selected_total_fee = 0;
        $orderids = array(); 

        $args = array(
            'post_type' => 'court-name',
            'title'     => $user_selected_court,
            'post_status' => 'publish',
            'posts_per_page' => 1
        );
        $selected_query = new WP_Query($args);
        if ( !empty($selected_query->posts) ) {

            $selected_court_name_id = $selected_query->posts[0]->ID;
            $school_fee = get_post_meta($selected_court_name_id, 'school_fee', true);
            $court_fee = get_post_meta($selected_court_name_id, 'court_fee', true);
            $process_fee = get_post_meta($selected_court_name_id, 'process_fee', true);
            $state_surcharge_fee = get_post_meta($selected_court_name_id, 'state_surcharge_fee', true);
            $state_fee = get_post_meta($selected_court_name_id, 'state_fee', true);
             $online_fee = get_post_meta($selected_court_name_id, 'online_fee', true);

           // $selected_total_fee = $school_fee + $court_fee + $process_fee + $state_surcharge_fee + $state_fee ;
            $selected_total_fee = $online_fee;
            /*
            $orderids =  get_orders_with_product( $user_id, $selected_court_name_id );
            if ( !empty($orderids) ) {
                    $order = wc_get_order( $orderids[0] );

            
             print_r($order);
             
            } else {
                 
            }*/
            
            

        }

         // get posttype "court-name" by name

        $args = array(
            'post_type' => 'court-name',
            'title'     => $new_court_name,
            'post_status' => 'publish',
            'posts_per_page' => 1
        );

        $query = new WP_Query($args);

        if ( !empty($query->posts) ) {

            $new_court_name_id = $query->posts[0]->ID;
            $school_fee = get_post_meta($new_court_name_id, 'school_fee', true);
            $court_fee = get_post_meta($new_court_name_id, 'court_fee', true);
            $process_fee = get_post_meta($new_court_name_id, 'process_fee', true);
            $state_surcharge_fee = get_post_meta($new_court_name_id, 'state_surcharge_fee', true);
            $state_fee = get_post_meta($new_court_name_id, 'state_fee', true);
            $online_fee = get_post_meta($new_court_name_id, 'online_fee', true);

            //$new_court_total_fee = $school_fee + $court_fee + $process_fee + $state_surcharge_fee + $state_fee + $online_fee;
            $new_court_total_fee = $online_fee;

        }
        // check if new court total fee is different from selected court total fee
        if ($selected_total_fee != $new_court_total_fee) {
            // create new order with new court name and order total is different from selected court total fee
            $orderids = get_orders_with_product( $user_id, $selected_court_name_id );

           
            $order_total = $new_court_total_fee - $selected_total_fee;
            $order_total = 100; // for testing purpose

            if( $order_total > 0 ) {
                // Create a new order
                 echo "Create new order ..".$new_court_name_id;
               
                $product_id = $new_court_name_id; // Assuming the product ID is the same as the court name post ID
                $order_id = eb_create_custom_order_for_user( $user_id, $product_id, $order_total);
                echo "<br> Order ID: " . $order_id;
                
                if($order_id){

                    $order = wc_get_order( $order_id );
                    // notify user about court name change
                    $payment_url = $order->get_checkout_payment_url();
                    $refund = false;
                    eb_send_email_notification( $user_id, $new_court_name, $user_selected_court,$payment_url,$refund  );
                    // Update order meta to indicate court name change
                    $order->update_meta_data( 'court_name_changed', 'yes' );
                    $order->update_meta_data( 'old_court_id', $selected_court_name_id );
                    $order->update_meta_data( 'old_order_id', $orderids[0] );
                    $order->save();

                }

                 

            }


            if($order_total <= 0 && !empty($orderids)) {
 
                $order_id = $orderids[0];
                $order = wc_get_order( $order_id );
                $is_court_name_changed = $order->get_meta( '_court_name_changed' );
                if(empty($is_court_name_changed)) {

                    $payment_gateway_id = $order->get_payment_method();
                    $payment_gateway    = WC_Payment_Gateways::instance()->payment_gateways()[ $payment_gateway_id ] ?? null;

                    // Check if gateway supports automatic refunds
                    $supports_refund = $payment_gateway && method_exists( $payment_gateway, 'process_refund' ) && $payment_gateway->supports( 'refunds' );
                    
                    $refund_amount = abs($order_total);
                    $arg_refund = array(
                        'order_id'     => $order_id,
                        'amount'       => $refund_amount,
                        'reason'       => 'Refund for court name change',
                        'line_items'   => array(), // optional
                        
                    );
                    if($supports_refund){
                        $arg_refund['refund_payment'] = true; // This triggers gateway refund
                    }
                    $refund_res = wc_create_refund( $arg_refund );
                    if($refund_res){
                        // Refund successful
                        // email notification
                        $payment_url = "";
                        $refund = true;
                        eb_send_email_notification( $user_id, $new_court_name, $user_selected_court,$payment_url,$refund  );
                        $order->update_meta_data( '_court_name_changed', 'yes' );
                        $order->save();
                    }

                    
                }
 
                // Refund the order

            }
            

        }

        echo "<pre>";
        var_dump($selected_total_fee);
        var_dump($new_court_total_fee);
        print_r($query->posts);
        echo "</pre>";



    } 

}

function get_orders_with_product( $user_id,$product_id ) {
    $matched_orders = [];
 
    $args = array(
        'customer' => $user_id,
        'limit' => -1, 
        'orderby' => 'date',        
        'order' => 'DESC',
        'status' => array( 'processing', 'completed' ),
    );
    $orders = wc_get_orders( $args );

    foreach ( $orders as $order ) {
        foreach ( $order->get_items() as $item ) {
            if ( $item->get_product_id() == $product_id ) {
                $matched_orders[] = $order->get_id();
                break;
            }
        }
    }

    return $matched_orders;
}

function eb_create_custom_order_for_user( $user_id, $product_id, $custom_price, $quantity = 1 ) {
    // Load user data
    $user = get_user_by( 'id', $user_id );
    if ( ! $user ) {
        return new WP_Error( 'invalid_user', 'Invalid user ID' );
    }
     /* $product = wc_get_product( $product_id );
        echo "<pre>";
       // print_r($product);
        print_r($product->get_name());
      die("Product ID: ".$product_id);*/



    // Get billing & shipping info from user meta
    $billing = [
        'first_name' => get_user_meta( $user_id, 'billing_first_name', true ),
        'last_name'  => get_user_meta( $user_id, 'billing_last_name', true ),
        'email'      => $user->user_email,
        'phone'      => get_user_meta( $user_id, 'billing_phone', true ),
        'address_1'  => get_user_meta( $user_id, 'billing_address_1', true ),
        'address_2'  => get_user_meta( $user_id, 'billing_address_2', true ),
        'city'       => get_user_meta( $user_id, 'billing_city', true ),
        'state'      => get_user_meta( $user_id, 'billing_state', true ),
        'postcode'   => get_user_meta( $user_id, 'billing_postcode', true ),
        'country'    => get_user_meta( $user_id, 'billing_country', true ),
    ];

    $shipping = [
        'first_name' => get_user_meta( $user_id, 'shipping_first_name', true ),
        'last_name'  => get_user_meta( $user_id, 'shipping_last_name', true ),
        'address_1'  => get_user_meta( $user_id, 'shipping_address_1', true ),
        'address_2'  => get_user_meta( $user_id, 'shipping_address_2', true ),
        'city'       => get_user_meta( $user_id, 'shipping_city', true ),
        'state'      => get_user_meta( $user_id, 'shipping_state', true ),
        'postcode'   => get_user_meta( $user_id, 'shipping_postcode', true ),
        'country'    => get_user_meta( $user_id, 'shipping_country', true ),
    ];

  

    // Create new order
    $order = wc_create_order([
        'customer_id' => $user_id,
    ]);
  
    
     $item = new WC_Order_Item_Product();
   // $item->set_product( $product );
    $item->set_quantity( $quantity );
    $item->set_subtotal( $custom_price * $quantity );
    $item->set_total( $custom_price * $quantity );
    //$item->set_prop( 'product_id', $product_id );
    $item->set_name( get_the_title( $product_id ) ); // Set item
   // $item->set_product_id( $product_id ); // Set product ID
    // $item->add_meta_data( '_product_id', $product_id, true );

    $item->save();
    $order_item_id = $item->get_id();
     
    // Add item to order before saving
    $order->add_item( $item );

      
    // Add product with custom price
    
     
    $product = wc_get_product( $product_id );
   /* $order_item_id = wc_add_order_item(
	$order->get_id(),
	array(
		'order_item_name' => $product->get_name(), // may differ from the product name
		'order_item_type' => 'line_item', // product
	)
    );*/
    /*
    if( $order_item_id ) {
        // provide its meta information
        echo "<br> Add order item meta";
        
        wc_add_order_item_meta( $order_item_id, '_product_id', $product_id, true ); // ID of the product
       
       wc_add_order_item_meta( $order_item_id, '_qty', 1, true ); // quantity
        // you can also add "_variation_id" meta
        wc_add_order_item_meta( $order_item_id, '_line_subtotal', $custom_price, true ); // price per item
        wc_add_order_item_meta( $order_item_id, '_line_total', $custom_price, true ); // total price
         wc_add_order_item_meta($item_id, '_line_tax', 0);                              // Tax (0 if not applicable)
    wc_add_order_item_meta($item_id, '_line_subtotal_tax', 0);                   // Subtotal tax (0 if not applicable)

    }
    */  
    
    // Set billing & shipping address
    $order->set_address( $billing, 'billing' );
    $order->set_address( $shipping, 'shipping' );

    // Recalculate totals and save
    $order->calculate_totals();
    $order->save();

     foreach ( $order->get_items() as $item_id => $item ) {
            wc_update_order_item_meta($item_id, '_product_id', $product_id);           
        } 
    return $order->get_id();
}
/**
 * Sends an email notification to the user about the court name change.
 *
 * @param int $user_id The ID of the user.
 * @param string $new_court_name The new court name.
 * @param string $old_court_name The old court name.
 * @param string $payment_url The URL for payment, if applicable.
 * @param bool $refund Whether a refund has been processed.
 */
function eb_send_email_notification( $user_id, $new_court_name, $old_court_name,$payment_url = '',$refund = false ) {

    $user_info = get_userdata( $user_id );

    $to = $user_info->user_email;
    $to = "nimeshgorfad@gmail.com";
     
    $subject = 'Court Name Change Notification';
    $message = sprintf(
        '<p> Dear %s, your court name has been changed from %s to %s.</p>',
        $user_info->display_name,
        $old_court_name,
        $new_court_name
    );

    if($refund) {
        $message .= '<p> A refund has been processed for the difference in fees.</p>';
    }

    if ( !empty($payment_url) ) {
        $message .= sprintf( '<p> Please complete your payment at the following link: %s</p>', $payment_url );
    }
    $headers = array('Content-Type: text/html; charset=UTF-8');
    
    wp_mail( $to, $subject, $message, $headers );

}