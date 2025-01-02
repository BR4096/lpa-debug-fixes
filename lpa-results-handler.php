<?php
class LPA_Results_Handler {
    private $wpdb;
    private $data_processor;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->data_processor = new LPA_Data_Processor();
        
        add_shortcode('lpa_results', array($this, 'render_results_page'));
        add_action('wp_ajax_get_lpa_results', array($this, 'get_results_data'));
        add_action('wp_ajax_nopriv_get_lpa_results', array($this, 'get_results_data'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('gform_after_submission_' . LPA_Constants::FORM_ID, array($this, 'process_submission'), 10, 2);
    }

    public function process_submission($entry, $form) {
        try {
            $assessment_data = [
                'entry_id' => $entry['id'],
                'respondent_name' => $entry[LPA_Constants::NAME . '.3'] . ' ' . $entry[LPA_Constants::NAME . '.6'],
                'respondent_email' => $entry[LPA_Constants::EMAIL],
                'job_title' => $entry[LPA_Constants::JOB_TITLE],
                'company_size' => $entry[LPA_Constants::COMPANY_SIZE],
                'tech_team_size' => intval($entry[LPA_Constants::TECH_TEAM_SIZE]),
                'business_model' => $entry[LPA_Constants::BUSINESS_MODEL],
                'tech_complexity' => $entry[LPA_Constants::TECH_COMPLEXITY],
                'workforce_deployment' => $entry[38]
            ];

            $table_name = $this->wpdb->prefix . 'zwFlSeMJu_lpa_assessments';
            $result = $this->wpdb->insert($table_name, $assessment_data);
            
            if ($result === false) {
                throw new Exception($this->wpdb->last_error);
            }

            $assessment_id = $this->wpdb->insert_id;
            gform_update_meta($entry['id'], 'lpa_assessment_id', $assessment_id);
            
            return $assessment_id;
        } catch (Exception $e) {
            error_log('LPA Error: ' . $e->getMessage());
            return false;
        }
    }

    public function render_results_page($atts) {
        $assessment_id = isset($_GET['assessment']) ? intval($_GET['assessment']) : 0;
        if (!$assessment_id) {
            $entry_id = isset($_GET['entry']) ? intval($_GET['entry']) : 0;
            if ($entry_id) {
                $assessment_id = gform_get_meta($entry_id, 'lpa_assessment_id');
            }
        }
        
        if (!$assessment_id) {
            return '<p>No assessment specified.</p>';
        }

        wp_enqueue_script('lpa-results-app');
        wp_localize_script('lpa-results-app', 'lpaData', [
            'assessment_id' => $assessment_id,
            'nonce' => wp_create_nonce('lpa_results_nonce'),
            'ajaxurl' => admin_url('admin-ajax.php')
        ]);

        return sprintf(
            '<div class="lpa-results" data-assessment="%d">
                <h2>Assessment Results</h2>
                <div id="lpa-results-container"></div>
            </div>',
            $assessment_id
        );
    }

    public function get_results_data() {
        check_ajax_referer('lpa_results_nonce', 'nonce');
        
        $assessment_id = isset($_GET['assessment']) ? intval($_GET['assessment']) : 0;
        if (!$assessment_id) {
            wp_send_json_error('No assessment specified');
        }
        
        try {
            $results = $this->get_assessment_results($assessment_id);
            if (!$results) {
                wp_send_json_error('Results not found');
            }
            
            wp_send_json_success($results);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    private function get_assessment_results($assessment_id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT r.*, a.company_size, a.business_model, a.tech_complexity
             FROM {$this->wpdb->prefix}zwFlSeMJu_lpa_results r
             JOIN {$this->wpdb->prefix}zwFlSeMJu_lpa_assessments a ON r.assessment_id = a.assessment_id
             WHERE r.assessment_id = %d",
            $assessment_id
        ));
    }

    public function enqueue_scripts() {
        if (is_page('lpa-results') || has_shortcode(get_post()->post_content, 'lpa_results')) {
            wp_enqueue_script(
                'lpa-results-app',
                plugins_url('js/results-app.js', dirname(__FILE__)),
                ['wp-element', 'wp-api-fetch'],
                LPA_VERSION,
                true
            );
        }
    }
}

// Initialize the handler
add_action('plugins_loaded', function() {
    new LPA_Results_Handler();
});