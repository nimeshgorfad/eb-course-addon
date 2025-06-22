<?php
/**
 * Plugin Name: EB Course Addon 
 * Description: Shortcode to display enrolled courses using user_id. Usage: [show_eb_course user_id="123"] [enrollment_eb_course user_id="123"] [court_date_past court_date="2023-10-01"] [show_eb_course_order user_id="123"]
 * Version: 1.0
 * Author: Nimesh Gorfad
 * Author URI: https://github.com/nimeshgorfad/
 */

defined( 'ABSPATH' ) || exit;

function sec_show_eb_course_shortcode( $atts ) {
    global $wpdb;

    // Shortcode attributes
    $atts = shortcode_atts( array(
        'user_id' => 0,
    ), $atts );

    $user_id = intval( $atts['user_id'] );

    if ( ! $user_id ) {
        return '<p><strong>Error:</strong> No user ID provided.</p>';
    }

    // Fetch course_id(s) for the user
    $results = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}moodle_enrollment WHERE user_id = %d",
            $user_id
        )
    );

    $stmt = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}moodle_enrollment WHERE user_id = %d",
            $user_id
    );

    $results = $wpdb->get_results( $stmt ); 

  //  return print_r($results, true);
    if ( empty( $results ) ) {
        //
         return '';
        // return '<p>No enrolled courses found for this user.</p>';
    }
 
    // Display course titles 
    foreach ( $results as $course ) {
        $course_id = intval( $course->course_id );
        $course_post = get_post( $course_id );
        if ( ! $course_post  ) {
            continue; // Skip if course not found or not of type eb_course
        }

        $output .= ' <b> Cours : </b>' . esc_html( $course_post->post_title ) . '</br>';  

       //Enrolled Date
       // $enrolled_date = isset( $course->time ) ? date( 'F j, Y', strtotime( $course->time ) ) : 'N/A';
        $output .= ' <b> Enrolled Date : </b>' . esc_html( $course->time ) . '';
  
    }
    

    return $output;
}

add_shortcode( 'show_eb_course', 'sec_show_eb_course_shortcode' );

/* Enrollment */

add_action( 'wp_enqueue_scripts', 'sec_enroll_course_ajax_script' );
function sec_enroll_course_ajax_script() {
    wp_enqueue_script( 'enrollment-ajax', plugin_dir_url( __FILE__ ) . 'js/enrollment.js', array( 'jquery' ), '1.3', true );

    $admin_nonce   = wp_create_nonce( 'eb_admin_nonce' );

    wp_localize_script( 'enrollment-ajax', 'enrollAjax', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'eb-manage-user-enrol' ),
        'admin_nonce' => $admin_nonce,
    ));
}

function sec_enrollment_eb_course_shortcode( $atts ) {
    global $wpdb;

    // Shortcode attributes
    $atts = shortcode_atts( array(
        'user_id' => 0,
    ), $atts );

    $user_id = intval( $atts['user_id'] );

    if ( ! $user_id ) {
        return '<p><strong>Error:</strong> No user ID provided.</p>';
    }

    // Query all eb_course posts (published or draft)
    $query = "SELECT `ID`, `post_title`, `post_status` 
              FROM `{$wpdb->prefix}posts` 
              WHERE `post_type` = 'eb_course' 
              AND (`post_status` = 'publish' OR `post_status` = 'draft')";

    $courses = $wpdb->get_results( $query, OBJECT_K );

    if ( empty( $courses ) ) {
        return '<p>No courses found.</p>';
    }

    
    ob_start();

    $results = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT course_id FROM {$wpdb->prefix}moodle_enrollment WHERE user_id = %d",
            $user_id
        )
    );

    if ( empty( $results ) ) {
        ?>
        <form method="post" action="">
            <?php wp_nonce_field( 'eb-manage-user-enrol', 'eb-manage-user-enrol' ); ?>

            <input type="hidden" name="new-enrollment-student" id="new-enrollment-student" value="<?php echo esc_attr( $user_id ); ?>" />

            <input type="hidden" name="action" value="enroll_user_to_courses" />

            <input type="hidden" name="new-enrollment-courses[]" id="new-enrollment-courses" value="4699" />
            
            <input type="submit" class="button" value="Enroll Student" />
        </form>
        <?php
        
    }else{


        $courses = get_posts( array(
            'post_type' => 'eb_course',
            'post__in'  => $results,
            'numberposts' => -1,
        ) );

        foreach ( $courses as $course ) {
        
            echo '<button type="button" href="javascript:void(0)" class="nkg-unenrol button" data-user-id="' . esc_attr( $user_id ) . '" data-record-id="' . esc_attr( $course->ID ) . '" data-course-id="' . esc_attr( $course->ID ) . '">Unenroll</a>';   


        }


    }
    

    return ob_get_clean();
}

add_shortcode( 'enrollment_eb_course', 'sec_enrollment_eb_course_shortcode' );


add_action( 'wp_ajax_enroll_user_to_courses', 'sec_handle_enroll_user_ajax' );

function sec_handle_enroll_user_ajax() {
    
	
	if ( function_exists( '\app\wisdmlabs\edwiserBridge\edwiser_bridge_instance' ) ) {

        $edwiser_bridge = \app\wisdmlabs\edwiserBridge\edwiser_bridge_instance();

        /* $manage_enrollment = new \app\wisdmlabs\edwiserBridge\Eb_Manage_Enrollment( $edwiser_bridge->get_plugin_name(), $edwiser_bridge->get_version() );

           $manage_enrollment->handle_new_enrollment();*/
        //   var_dump( $edwiser_bridge->get_plugin_name());
	    //  var_dump( $edwiser_bridge->get_version());
        $user_id = sanitize_text_field( wp_unslash( $_POST['new-enrollment-student'] ) );

        $raw_courses = $_POST['new-enrollment-courses'];  

        $courses = array();

        foreach ( $raw_courses as $course ) {
            $courses[] = sanitize_text_field( wp_unslash( $course ) );
        }

        $args = array(
					'user_id'           => $user_id,
					'role_id'           => 5,
					'courses'           => $courses,
					'unenroll'          => 0,
					'suspend'           => 0,
				);

               
       
        $response =  $edwiser_bridge->enrollment_manager()->update_user_course_enrollment( $args );
     
        if ( $response ) {
            wp_send_json_success( 'enrolled successfully' );
        }else {
            wp_send_json_error( 'Something went wrong' );
        } 

       // edwiser_bridge_instance()->enrollment_manager()->update_user_course_enrollment( $args );

         
	}else{
		 wp_send_json_success( 'Something went wrong..' );
	}
	 
}


/**
 * Summary of get_court_date_minus_7_days
 * @param mixed $atts
 * @return string
 */ 
function get_court_date_minus_7_days($atts) {
     $atts = shortcode_atts( array(
        'court_date' => 0,
    ), $atts );

     
    $court_date = $atts['court_date'];
    
    if (!$court_date) return '';

    // Convert to DateTime and subtract 7 days
    $date = new DateTime($court_date);
    $date->modify('-7 days');

    return $date->format('F j, Y'); // Change format if needed
}
add_shortcode('court_date_past', 'get_court_date_minus_7_days');

/**
 * last WooCommerce order for that user.
 * @param mixed $atts
 * @return bool|string
 */
function shortcode_show_eb_course_order($atts) {
    $atts = shortcode_atts(array(
        'user_id' => 0,
    ), $atts);

    $user_id = intval($atts['user_id']);

    if (!$user_id || get_userdata($user_id) === false) {
        return 'Invalid user ID.';
    }

   

    $args = array(
        'customer' => $user_id,
        'limit' => 1, // Retrieve all orders for the user
        'orderby' => 'date',
        'order' => 'DESC',
    );

        $orders = wc_get_orders( $args );

 

    if (empty($orders)) {
        return '';
    }

    $order = wc_get_order($orders[0]->ID);
    

    ob_start();

    foreach ($order->get_items() as $item) {
        $product_name = $item->get_name();
        $product_price = wc_price($item->get_total());
          if ( $item->get_variation_id() ) {
            $product = $item->get_product();

            $variation_attributes = $product->get_attributes();
            $formatted_variation  = wc_get_formatted_variation( $product, true );
             $product_name = $formatted_variation;
           
            echo ' <b>' . esc_html($product_name) . '</b> <br> fee - ' . $product_price . '';


          }else{
            echo '<p><strong>' . esc_html($product_name) . '</strong>: ' . $product_price . '</p>';
          }
        
    }

  
    return ob_get_clean();
}
add_shortcode('show_eb_course_order', 'shortcode_show_eb_course_order');

