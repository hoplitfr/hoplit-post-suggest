<?php
/**
 * Plugin Name: Hoplit Post Suggest
 * Description: Submit articles ready for publication through a simple form.
 * Version: 1.0.0
 * Author: Hoplit.fr
 * Author URI: https://www.hoplit.fr/
 * License: GPL v3
 * Text Domain: hoplit-post-suggest
 */

// Check if ReallySimpleCaptcha is active, then display a captcha form
function rsc_captcha_display() {
    if (class_exists('ReallySimpleCaptcha')) {
        $captcha_instance = new ReallySimpleCaptcha();

        // Generate a random word and prefix for the captcha
        $word = $captcha_instance->generate_random_word();
        $prefix = mt_rand();

        // Generate the captcha image
        $captcha_instance->generate_image($prefix, $word);
        $image_url = plugins_url("really-simple-captcha/tmp/{$prefix}.png");

        // Display the captcha image and response field in the comment form
        echo '<p>';
        echo '<label for="captcha">Captcha:</label>';
        echo '<img src="' . esc_url($image_url) . '" alt="Captcha Image" />';
        echo '<input type="text" name="captcha" id="captcha" />';
        echo '</p>';

        // Add a hidden field to store the captcha prefix
        echo '<input type="hidden" name="captcha_prefix" value="' . esc_attr($prefix) . '" />';
    }
}

// Create form shortcode
function post_suggest_form_shortcode() {
    ob_start(); ?>
    <style>
        /* Form CSS */
        form {
            max-width: 600px;
            margin: 0 auto;
            background-color: #f4f4f4;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        label {
            display: block;
            margin-bottom: 8px;
        }

        input[type="text"],
        textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        input[type="submit"] {
            background-color: #4caf50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        input[type="submit"]:hover {
            background-color: #45a049;
        }
    </style>

    <form method="post" action="">
        <?php
        wp_nonce_field('suggest_post_nonce', 'suggest_post_nonce');
        ?>
        <label for="post_title">Title:</label>
        <input type="text" name="post_title" required>
        <br>
        <label for="post_content">Content:</label>
        <?php
        $content = ''; // Initialize an empty variable for content
        wp_editor($content, 'post_content', array('textarea_name' => 'post_content', 'editor_height' => 300));
        ?>        <br>
        <?php rsc_captcha_display(); ?>
        <input type="submit" name="suggest_post" value="Suggest">
    </form>
    <?php // Check whether the form submission was successful or not
    if (isset($_GET['success']) && $_GET['success'] === 'true') {
        echo '<p style="color: green;">The article has been sent.</p>';
    } 
    if (isset($_GET['success']) && $_GET['success'] === 'false') {
        echo '<p style="color: red;">The article has not been sent...</p>';
    }
    return ob_get_clean();
}
add_shortcode('post_suggest_form', 'post_suggest_form_shortcode');

// Processing submitted form
function process_post_suggest_form() {
    if (isset($_POST['suggest_post'])) {

        if (!isset($_POST['suggest_post_nonce']) || !wp_verify_nonce($_POST['suggest_post_nonce'], 'suggest_post_nonce')) {
            wp_die('Security check failed. Please try again.');
        }
        
        // Sanitize fields
        $post_title = sanitize_text_field($_POST['post_title']);
        $post_content = wp_kses_post($_POST['post_content']);

        if (class_exists('ReallySimpleCaptcha')) {
            $captcha_instance = new ReallySimpleCaptcha();
            // Get the user's answer
            $user_answer = sanitize_text_field($_POST['captcha']);
            // Get the captcha prefix from the hidden field
            $captcha_prefix = sanitize_text_field($_POST['captcha_prefix']);
            // Check the user's answer
            $correct = $captcha_instance->check($captcha_prefix, $user_answer);
            // If the answer is incorrect, return an error
            if (!$correct) {
                wp_die('Error: The captcha is incorrect. Please try again.');
            }
            // Remove temporary files after verification
            $captcha_instance->remove($captcha_prefix);
        }

        // Array for new article data
        $new_post = array(
            'post_title'   => $post_title,
            'post_content' => $post_content,
            'post_status'  => 'pending', // Important : Not published
            'post_author'  => get_current_user_id(),
            'post_type'    => 'post',
        );

        // Insert new article in db
        $post_id = wp_insert_post($new_post);

        if (!is_wp_error($post_id)) {
            wp_redirect(add_query_arg('success', 'true', get_permalink()));
            exit;
        } else {
            wp_redirect(add_query_arg('success', 'false', get_permalink()));
            exit;
        }
    }
}
add_action('init', 'process_post_suggest_form');

?>
