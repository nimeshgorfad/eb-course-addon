<?php
add_action('admin_menu', 'court_free_update_menu');
function court_free_update_menu() {
    add_menu_page(
        'Court Free Update',
        'Court Free Update',
        'manage_options',
        'court-free-update',
        'court_free_update_page',
        'dashicons-update',
        26
    );
}

function court_free_update_page() {
    $fields = [
        'school_fee' => 'School Fee',
        'court_fee' => 'Court Fee',
        'process_fee' => 'Process Fee',
        'state_surcharge_fee' => 'State Surcharge Fee',
        'state_fee' => 'State Fee',
        //'online_fee' => 'Online Fee',
    ];

    // Handle form submission
    if (isset($_POST['court_free_update_nonce']) && wp_verify_nonce($_POST['court_free_update_nonce'], 'court_free_update_action')) {

        $updated_fields = 0;

         $court_names = get_posts([
                'post_type' => 'court-name',
                'post_status' => 'publish',
                'numberposts' => -1,
            ]);

            foreach ($court_names as $court) {

                    $school_fee  = (float) $_POST['school_fee'] ?? '';
                    if(empty($school_fee)) {
                        $school_fee = (float) get_post_meta($court->ID, 'school_fee', true);
                    }
                    $court_fee   = (float) $_POST['court_fee'] ?? '';
                    if(empty($court_fee)) {
                        $court_fee = (float) get_post_meta($court->ID, 'court_fee', true);
                    }
                    $process_fee = $_POST['process_fee'] ?? '';
                    if(empty($process_fee)) {
                        $process_fee = (float) get_post_meta($court->ID, 'process_fee', true);
                    }
                    $state_surcharge_fee = (float) $_POST['state_surcharge_fee'] ?? '';
                    if(empty($state_surcharge_fee)) {
                        $state_surcharge_fee = (float) get_post_meta($court->ID, 'state_surcharge_fee', true);
                    }
                    $state_fee   = (float) $_POST['state_fee'] ?? '';
                    if(empty($state_fee)) {
                        $state_fee = (float)get_post_meta($court->ID, 'state_fee', true);
                    }
                    $online_fee  = $school_fee + $court_fee + $process_fee + $state_surcharge_fee + $state_fee;
                     update_post_meta($court->ID, 'school_fee', $school_fee);
                    update_post_meta($court->ID, 'court_fee', $court_fee);
                    update_post_meta($court->ID, 'process_fee', $process_fee);
                    update_post_meta($court->ID, 'state_surcharge_fee', $state_surcharge_fee);
                    update_post_meta($court->ID, 'state_fee', $state_fee);
                    update_post_meta($court->ID, 'online_fee', $online_fee);                    
                      $updated_fields++;
                
            }

        if (!empty($updated_fields)) {
            echo '<div class="updated notice"><p><strong>Updated successfully:</strong> </p></div>';
        } else {
            echo '<div class="error notice"><p>No fields selected for update.</p></div>';
        }
    }

    ?>

    <div class="wrap">
        <h1>Court Free Update</h1>
        <form method="post">
            <?php wp_nonce_field('court_free_update_action', 'court_free_update_nonce'); ?>
            <table class="form-table">
                <?php foreach ($fields as $key => $label): ?>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
                        </th>
                        <td>
                            <input type="number" name="<?php echo esc_attr($key); ?>" step="0.01" class="regular-text" />
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <?php submit_button('Update All Court Posts'); ?>
        </form>
    </div>

    <?php
}
