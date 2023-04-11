<?php
/*
Plugin Name: CNP Validator
Plugin URI: https://github.com/calinzabalt
Description: CNP Validator for Romania
Version: 1.0
Author: Cozma Calin
Author URI: https://github.com/calinzabalt
License: GPL2
*/

add_action('admin_menu', 'cnp_admin_menu');

function cnp_admin_menu(){
    add_menu_page( 
        'CNP',  // page title
        'CNP-Validator',  // menu title
        'manage_options',   // capability
        'cnp-validator',  // menu slug
        'cpn_validator_admin_content' // function to display page
    );
}

function cpn_validator_admin_content(){
    echo '
        <div class="cnp_validator">
            <div class="welcome_meesage">
                Welcome !
            </div>
            <div class="cnp_shorcode">
                [cnp_validator]
            </div>
        </div>
    ';
}

function cnp_validator_shortcode_content(){
    echo '
        <div class="cnp_validator_shorcode">
            <div class="form">
                <form id="cnp-form">
                    <div class="item">
                        <label>Validare CNP</label>
                        <input type="text" id="cnp-input"/>
                    </div>
                    <div class="item">
                        <input type="submit" value="Trimite"/>
                    </div>
                </form>
            </div>
        </div>
    ';
}

// Define the shortcode
function cnp_validator_shortcode() {
    ob_start();
    cnp_validator_shortcode_content(); // Call the function that displays the page content
    $output = ob_get_clean();
    return $output;
}
add_shortcode( 'cnp_validator', 'cnp_validator_shortcode' );

/* Validator Function */
add_action( 'wp_ajax_isCnpValid', 'isCnpValid' );
add_action( 'wp_ajax_nopriv_isCnpValid', 'isCnpValid' );

function isCnpValid() {
    $cnp = $_POST['cnp'];

    // Check if CNP is eqaul with 13 - step 1
    if (strlen($cnp) == 13) {
        $result = true;
    } else {
        $result = false;
    }

    // Check sex and century of birth - step 2
    $first_digit = intval(substr($cnp, 0, 1));
    $sex = $first_digit % 2 == 1 ? 'M' : 'F';
    $century = '';
    if ($first_digit == 1 || $first_digit == 2) {
        $century = '19';
    } elseif ($first_digit == 3 || $first_digit == 4) {
        $century = '18';
    } elseif ($first_digit == 5 || $first_digit == 6) {
        $century = '20';
    } elseif ($first_digit == 7 || $first_digit == 8) {
        $century = 'foreign in Romania';
    } elseif ($first_digit == 9) {
        $century = 'foreign';
    } else {
        $result = false;
    }

    if ($century != 'foreign') {
        $year = intval($century . substr($cnp, 1, 2));
        $month = intval(substr($cnp, 3, 2));
        $day = intval(substr($cnp, 5, 2));
        $birthdate = date_create($year . '-' . $month . '-' . $day);
        if ($sex == 'M' && $first_digit % 2 == 0) {
            $result = false;
        } elseif ($sex == 'F' && $first_digit % 2 == 1) {
            $result = false;
        } elseif (!$birthdate) {
            $result = false;
        } else {
            $result = true;
        }
    } else {
        $result = true;
    }

    // Extract birth year - step 3
    $second_digit = intval(substr($cnp, 1, 2)); 
    $birth_year = '';
    if ($century == 'foreign' || $first_digit == 7 || $first_digit == 8 || $first_digit == 9) {
        $birth_year = '20' . $second_digit;
    } else {
        $birth_year = $century . $second_digit;
    }

    if ($birth_year) {
        $birthdate = date_create($birth_year . '-' . $month . '-' . $day);
        if (!$birthdate) {
            $result = false;
        } else {
            $result = true;
        }
    } else {
        $result = false;
    }

    // Extract birth month - step 4
    $third_digit = intval(substr($cnp, 3, 2));
    if ($third_digit > 12) {
        $result = false;
    } else {
        $month = str_pad($third_digit, 2, '0', STR_PAD_LEFT);
    }

    // Extract birth day from CNP - step 5
    $day = substr($cnp, 5, 2);

    if ($day < 10) {
        $day = '0' . $day;
    }

    // Extract county/sector code from CNP - step 6
    $code = substr($cnp, 7, 2);

    // Convert sector codes for Bucharest to county codes
    if ($code >= 41 && $code <= 46) {
        $code = 'B' . ($code - 40);
    }

    // Check if code is valid
    $valid_codes = array(
        '01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13', '14', '15',
        '16', '17', '18', '19', '20', '21', '22', '23', '24', '25', '26', '27', '28', '29', '30',
        '31', '32', '33', '34', '35', '36', '37', '38', '39', '40', 'B1', 'B2', 'B3', 'B4', 'B5', 'B6'
    );

    $result = in_array($code, $valid_codes);

    //Validate NNN - step 7
    $nnn = substr($cnp, 7, 3);

    if (is_numeric($nnn) && $nnn >= 1 && $nnn <= 999) {
        $result = true;
    } else {
        $result = false;
    }

    // Last Step
    $multipliers = "279146358279";
    $sum = 0;

    for ($i = 0; $i < 12; $i++) {
        $sum += intval($cnp[$i]) * intval($multipliers[$i]);
    }

    $remainder = $sum % 11;

    if ($remainder == 10) {
        $control_digit = 1;
    } else {
        $control_digit = $remainder;
    }

    $is_valid = intval($cnp[12]) === $control_digit;

    if ($is_valid) {
        $result = true;
    } else {
        $result = false;
    }

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode(array('valid' => $result));
    exit;
}


/* Add css to cnp plugin in admin */
function cnp_styles() {
    wp_enqueue_style( 'cnp_styles', plugins_url( 'css/cnp.css', __FILE__ ) );
}
add_action( 'admin_print_styles', 'cnp_styles' );

/* Add front-end css to cnp plugin */
function cnp_front_end_styles() {
    wp_enqueue_style( 'cnp_front_end_styles', plugins_url( 'css/front-end.css', __FILE__ ) );
}
add_action( 'wp_enqueue_scripts', 'cnp_front_end_styles' );

/* Add js */
function my_plugin_enqueue_scripts() {
    wp_enqueue_script( 'jquery' );
    wp_enqueue_script( 'my-plugin-script', plugins_url( 'js/main.js', __FILE__ ), array( 'jquery' ));
    wp_localize_script( 'my-plugin-script', 'ajaxurl', admin_url( 'admin-ajax.php' ) );
}
add_action( 'wp_enqueue_scripts', 'my_plugin_enqueue_scripts' );

  