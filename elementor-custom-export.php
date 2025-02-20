<?php
/*
Plugin Name: Elementor Custom Export
Description: Adds a custom export button to Elementor form submissions to export data in a single CSV file.
Version: 1.1
Author: Your Name
*/

// Exit if accessed directly.
use Elementor\Core\Utils\Collection;
use ElementorPro\Modules\Forms\Submissions\Database\Query;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Enqueue the custom script for handling the export button.
add_action('admin_enqueue_scripts', 'custom_export_enqueue_scripts');
function custom_export_enqueue_scripts($hook) {

    if ($hook != 'elementor_page_e-form-submissions') {
        return;
    }

    wp_enqueue_script('custom-export-script', plugin_dir_url(__FILE__) . 'custom-export.js', array('jquery'), null, true);

    wp_localize_script('custom-export-script', 'customExport', array(
        'ajax_url' => rest_url('custom/v1/export'),
        'nonce' => wp_create_nonce('custom_export_nonce'),
    ));
}

// Add a custom export button to Elementor form submissions page.
add_action('admin_footer', 'add_custom_export_button');
function add_custom_export_button() {
    $screen = get_current_screen();
    if ($screen->id === 'elementor_page_e-form-submissions') {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('<button style="margin: 0px 10px;" class="button action" id="custom-export">Custom Export</button>').insertAfter('.e-export-button');
            });
        </script>
        <?php
    }
}


// Add action to initialize the REST API route.
add_action('rest_api_init', function () {

    register_rest_route('custom/v1', '/export', array(
        'methods' => 'GET',
        'callback' => 'custom_export_callback',
        'permission_callback' => '__return_true',
        // all permissions are implimented inside the callback action
    ));
});


 function get_submissions_by_filter( $request ) {
    $args = $request->get_attributes()['args'];

    $filters = ( new Collection( $request->get_query_params() ) )
        ->filter(function ( $value, $key ) use ( $args ) {
            return isset( $args[ $key ]['additionalProperties']['context'] ) &&
                'filter' === $args[ $key ]['additionalProperties']['context'];
        })
        ->map( function ( $value ) use ( $request ) {
            return [ 'value' => $value ];
        } )
        ->all();

    return Query::get_instance()->get_submissions(
        [
            'page' => $request->get_param( 'page' ),
            'per_page' => $request->get_param( 'per_page' ),
            'filters' => $filters,
            'order' => [
                'order' => $request->get_param( 'order' ),
                'by' => $request->get_param( 'order_by' ),
            ],
            'with_meta' => true,
        ]
    )['data'];
}



// Callback function to handle the export and generate the CSV.
function custom_export_callback($request)
{

    $submissions = new Collection(get_submissions_by_filter($request));
    $csv_content = generate_csv($submissions);
    return new WP_REST_Response(array('success' => true, 'csv_content' => $csv_content), 200);
}



// Generate CSV content from submissions.
function generate_csv($submissions)
{
    $csv_data = [];
    $field_keys = [];

    // Determine all unique form field keys to create dynamic headers
    foreach ($submissions as $submission) {
        foreach ($submission['values'] as $field) {
            if (!in_array($field['key'], $field_keys)) {
                $field_keys[] = $field['key'];
            }
        }
    }

    // Group submissions by element_id
    $grouped_submissions = [];
    foreach ($submissions as $submission) {
        $element_id = $submission['form']['element_id'];
        if (!isset($grouped_submissions[$element_id])) {
            $grouped_submissions[$element_id] = [];
        }
        $grouped_submissions[$element_id][] = $submission;
    }

    // Generate CSV content
    foreach ($grouped_submissions as $element_id => $group) {
        // Add a header for each group
        $csv_data[] = [$element_id];

        // Add the column headers
        $header = array_merge(['ID', 'Form Name', 'Created At'], $field_keys);
        $csv_data[] = $header;

        foreach ($group as $submission) {
            $row = [
                $submission['id'],
                $submission['form']['name'],
                $submission['created_at']
            ];

            // Fill dynamic form fields
            $field_values = [];
            foreach ($field_keys as $key) {
                $field_values[$key] = '';
                foreach ($submission['values'] as $field) {
                    if ($field['key'] === $key) {
                        $field_values[$key] = $field['value'];
                    }
                }
            }

            // Add dynamic field values to the row
            $row = array_merge($row, $field_values);
            $csv_data[] = $row;
        }
    }

    $output = fopen('php://output', 'w');
    ob_start();
    foreach ($csv_data as $row) {
        fputcsv($output, $row);
    }
    fclose($output);

    return ob_get_clean();
}




