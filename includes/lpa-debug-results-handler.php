/**
 * Leadership Pipeline Assessment Debug Results Handler
 * 
 * @package LeadershipPipelineAssessment
 * @version 1.2.3
 */

class LPA_Debug_Results_Handler {
    private $wpdb;
    private $data_processor;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->data_processor = new LPA_Data_Processor();
        
        add_shortcode('lpa_results', array($this, 'render_results_page'));
        add_action('gform_after_submission_' . LPA_Constants::FORM_ID, array($this, 'debug_form_submission'), 10, 2);
    }

    public function debug_form_submission($entry, $form) {
        error_log('LPA Debug: Form submission received for entry ' . $entry['id']);
        error_log('LPA Debug: Full entry data: ' . print_r($entry, true));
        
        try {
            $this->validate_tables();
            $assessment_id = $this->process_submission_with_debug($entry);
            
            if ($assessment_id) {
                gform_update_meta($entry['id'], 'lpa_assessment_id', $assessment_id);
                error_log('LPA Debug: Successfully processed entry, assessment_id: ' . $assessment_id);
            }
        } catch (Exception $e) {
            error_log('LPA Error: ' . $e->getMessage());
        }
    }

    private function validate_tables() {
        $tables = [
            'zwFlSeMJu_lpa_assessments',
            'zwFlSeMJu_lpa_metrics',
            'zwFlSeMJu_lpa_results'
        ];
        
        foreach ($tables as $table) {
            $exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$this->wpdb->prefix}{$table}'");
            if (!$exists) {
                error_log("LPA Debug: Table {$table} does not exist");
                throw new Exception("Required table {$table} is missing");
            }
        }
    }

    private function process_submission_with_debug($entry) {
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
        
        error_log('LPA Debug: Workforce Deployment value: ' . $entry[38]);
        error_log('LPA Debug: Assessment data to insert: ' . print_r($assessment_data, true));
        
        $table_name = $this->wpdb->prefix . 'zwFlSeMJu_lpa_assessments';
        $result = $this->wpdb->insert($table_name, $assessment_data);
        
        if ($result === false) {
            throw new Exception('Failed to insert assessment data: ' . $this->wpdb->last_error);
        }
        
        $assessment_id = $this->wpdb->insert_id;
        error_log('LPA Debug: Assessment inserted with ID: ' . $assessment_id);
        
        return $assessment_id;
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
            error_log('LPA Debug: No assessment ID found in request');
            return '<p>No assessment specified.</p>';
        }

        error_log('LPA Debug: Rendering results for assessment ID: ' . $assessment_id);
        
        return sprintf(
            '<div class="lpa-results" data-assessment="%d">
                <h2>Assessment Results (Debug Mode)</h2>
                <div id="lpa-results-container"></div>
            </div>',
            $assessment_id
        );
    }
}

// Initialize debug handler
add_action('plugins_loaded', function() {
    new LPA_Debug_Results_Handler();
});