<?php
/**
 * My Identity Map Page - Student Path Block
 *
 * @package    block_student_path
 * @copyright  2026 SAVIO - Sistema de Aprendizaje Virtual Interactivo (UTB)
 * @author     SAVIO Development Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/lib.php');

if (!isloggedin()) {
    redirect($CFG->wwwroot);
}

$courseid = optional_param('cid', 0, PARAM_INT);
if (!$courseid) {
    $courseid = SITEID;
}

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$PAGE->set_course($course);
$PAGE->set_url('/blocks/student_path/view.php', array('cid' => $courseid));
$PAGE->set_pagelayout('incourse'); // Modern incourse layout
$PAGE->set_title(get_string('pluginname', 'block_student_path'));
$PAGE->set_heading(get_string('pluginname', 'block_student_path'));

$context = context_course::instance($courseid);
$PAGE->set_context($context);

// Check if the block is added to the course
if (!$DB->record_exists('block_instances', array('blockname' => 'student_path', 'parentcontextid' => $context->id))) {
    redirect(new moodle_url('/course/view.php', array('id' => $courseid)));
}

// Prevent teachers from accessing the student view (redirect to teacher view)
if (has_capability('block/student_path:viewstudentdata', $context)) {
    redirect(new moodle_url('/blocks/student_path/admin_view.php', ['cid' => $courseid]));
}

// Check permissions for students
require_capability('block/student_path:makemap', $context);

// Add custom CSS
$PAGE->requires->css(new moodle_url('/blocks/student_path/styles.css'));

// Get existing data - Longitudinal access (by user only, ignoring course)
$entry = $DB->get_record('block_student_path', array('user' => $USER->id));

// Check for history snapshots
$history_records = $DB->get_records('block_student_path_history', array('userid' => $USER->id), 'period DESC');

// Initialize default values if no entry exists
if (!$entry) {
    $entry = new stdClass();
    $entry->program = '';
    $entry->admission_year = '';
    $entry->admission_semester = '';
    $entry->code = $USER->idnumber;
    $entry->personality_strengths = '';
    $entry->personality_weaknesses = '';
    $entry->vocational_areas = '';
    $entry->vocational_areas_secondary = '';
    $entry->vocational_description = '';
    $entry->emotional_skills_level = '';
    $entry->goal_short_term = '';
    $entry->goal_medium_term = '';
    $entry->goal_long_term = '';
    $entry->action_short_term = '';
    $entry->action_medium_term = '';
    $entry->action_long_term = '';
}

// Ensure admission_semester exists (for old records)
if (!isset($entry->admission_semester)) {
    $entry->admission_semester = '';
}

echo $OUTPUT->header();

// Colors for sections (used in template)
$colors = [
    'personal' => '#f60',
    'discovery' => '#ffb600',
    'goals' => '#0054ce',
    'action' => '#00bf91'
];

// Programs Structure
$programs_data = [
    'school_engineering' => [
        'prog_arquitectura', 'prog_diseno', 'prog_ing_civil', 'prog_ing_industrial',
        'prog_ing_ambiental', 'prog_ing_quimica', 'prog_ing_naval', 'prog_ing_mecanica',
        'prog_ing_electrica', 'prog_ing_electronica', 'prog_ing_mecatronica', 'prog_ing_biomedica'
    ],
    'school_digital' => [
        'prog_ciencia_datos', 'prog_comunicacion', 'prog_ing_sistemas', 'prog_marketing'
    ],
    'school_business' => [
        'prog_admin_empresas', 'prog_ciencia_politica', 'prog_contaduria', 'prog_derecho',
        'prog_economia', 'prog_finanzas', 'prog_psicologia'
    ]
];

// SMART Goals Data
$smart_data_config = [
    'specific' => ['letter' => 'S', 'icon' => 'fa-bullseye', 'color' => '#e74c3c'],
    'measurable' => ['letter' => 'M', 'icon' => 'fa-bar-chart', 'color' => '#3498db'],
    'achievable' => ['letter' => 'A', 'icon' => 'fa-check-square-o', 'color' => '#2ecc71'],
    'relevant' => ['letter' => 'R', 'icon' => 'fa-star', 'color' => '#f1c40f'],
    'temporal' => ['letter' => 'T', 'icon' => 'fa-clock-o', 'color' => '#9b59b6']
];

// ----------- Prepare Context for Mustache -----------

// 1. History items
$history_items = [];
if ($history_records) {
    foreach ($history_records as $history) {
        $history_content = json_decode($history->content);
        $history_date = isset($history_content->updated_at) ? $history_content->updated_at : $history->timecreated;
        $history_items[] = [
            'id' => $history->id,
            'period_formatted' => block_student_path_format_period($history->period),
            'date_formatted' => userdate($history_date)
        ];
    }
}

// 2. Tabs
$tabs = [
    [
        'target' => 'section-personal', 
        'label' => get_string('personal_info', 'block_student_path'),
        'icon' => 'fa-user',
        'color' => $colors['personal'],
        'active' => 'active'
    ],
    [
        'target' => 'section-discovery', 
        'label' => get_string('self_discovery', 'block_student_path'),
        'icon' => 'fa-lightbulb-o',
        'color' => $colors['discovery'],
        'active' => ''
    ],
    [
        'target' => 'section-goals', 
        'label' => get_string('goals_aspirations', 'block_student_path'),
        'icon' => 'fa-bullseye',
        'color' => $colors['goals'],
        'active' => ''
    ],
    [
        'target' => 'section-action', 
        'label' => get_string('action_plan', 'block_student_path'),
        'icon' => 'fa-tasks',
        'color' => $colors['action'],
        'active' => ''
    ]
];

// 3. Program Groups
$program_groups = [];
$program_found = false;
foreach ($programs_data as $school_key => $programs) {
    $group = [
        'label' => get_string($school_key, 'block_student_path'),
        'options' => []
    ];
    foreach ($programs as $prog_key) {
        $is_selected = ($entry->program == $prog_key);
        if ($is_selected) $program_found = true;
        
        $group['options'][] = [
            'value' => $prog_key,
            'name' => get_string($prog_key, 'block_student_path'),
            'selected' => $is_selected
        ];
    }
    $program_groups[] = $group;
}
$is_other_program = (!$program_found && !empty($entry->program));

// 4. Vocational Options
$areas = ['C', 'H', 'A', 'S', 'I', 'D', 'E'];
$vocational_options = [];
foreach ($areas as $area) {
    $vocational_options[] = [
        'value' => $area,
        'name' => get_string('vocational_area_' . strtolower($area), 'block_student_path'),
        'selected' => ($entry->vocational_areas == $area)
    ];
}

$vocational_options_secondary = [];
foreach ($areas as $area) {
    $vocational_options_secondary[] = [
        'value' => $area,
        'name' => get_string('vocational_area_' . strtolower($area), 'block_student_path'),
        'selected' => ($entry->vocational_areas_secondary == $area)
    ];
}

// 5. SMART Goals
$smart_goals_list = [];
foreach ($smart_data_config as $key => $data) {
    $smart_goals_list[] = [
        'letter' => $data['letter'],
        'icon' => $data['icon'],
        'color' => $data['color'],
        'meaning' => get_string('smart_' . $key . '_meaning', 'block_student_path'),
        'question' => get_string('smart_' . $key . '_question', 'block_student_path')
    ];
}

// 6. Action Plan Formula
$template_str = get_string('action_plan_template_desc', 'block_student_path');
$parts = explode('+', $template_str);
$formula_parts = [];
foreach ($parts as $index => $part) {
    $formula_parts[] = [
        'text' => trim($part),
        'has_next' => ($index < count($parts) - 1)
    ];
}

// 7. Notification Logic
$show_notification = false;
$notification_title = '';
$notification_msg = '';

$last_updated = isset($entry->updated_at) ? $entry->updated_at : 0;
// Helper to get semester code
$get_semester = function($timestamp) {
    $year = date('Y', $timestamp);
    $month = date('n', $timestamp);
    $sem = ($month <= 6) ? 1 : 2;
    return $year . '-' . $sem;
};

$current_semester = $get_semester(time());
$last_semester = ($last_updated > 0) ? $get_semester($last_updated) : '';

if ($last_updated > 0 && $current_semester != $last_semester) {
    $show_notification = true;
    $notification_title = get_string('welcome_back_title', 'block_student_path');
    $msg_key = !empty($history_records) ? 'welcome_back_msg' : 'welcome_back_msg_new_semester';
    $notification_msg = get_string($msg_key, 'block_student_path');
}

$data = [
    'icon_url' => $OUTPUT->image_url('icon', 'block_student_path'),
    'courseid' => $courseid,
    'sesskey' => sesskey(),
    'has_history' => !empty($history_records),
    'history_items' => $history_items,
    'tabs' => $tabs,
    'color_personal' => $colors['personal'],
    'color_discovery' => $colors['discovery'],
    'color_goals' => $colors['goals'],
    'color_action' => $colors['action'],
    
    // User Data
    'user_fullname' => fullname($USER),
    'user_email' => $USER->email,
    'user_idnumber' => $USER->idnumber,
    'admission_year' => $entry->admission_year,
    'current_year' => date('Y'),
    'sem_1_selected' => ($entry->admission_semester == 1),
    'sem_2_selected' => ($entry->admission_semester == 2),
    
    // Program Data
    'program_groups' => $program_groups,
    'program_value' => htmlspecialchars($entry->program),
    'is_other_program' => $is_other_program,
    'other_program_value' => $is_other_program ? htmlspecialchars($entry->program) : '',
    
    // Content Data
    'personality_strengths' => htmlspecialchars($entry->personality_strengths),
    'personality_weaknesses' => htmlspecialchars($entry->personality_weaknesses),
    'vocational_options' => $vocational_options,
    'sec_none_selected' => ($entry->vocational_areas_secondary == 'none'),
    'vocational_options_secondary' => $vocational_options_secondary,
    'vocational_description' => htmlspecialchars($entry->vocational_description),
    'emotional_skills_level' => htmlspecialchars($entry->emotional_skills_level),
    
    'smart_goals' => $smart_goals_list,
    'goal_short_term' => htmlspecialchars($entry->goal_short_term),
    'goal_medium_term' => htmlspecialchars($entry->goal_medium_term),
    'goal_long_term' => htmlspecialchars($entry->goal_long_term),
    
    'formula_parts' => $formula_parts,
    'action_short_term' => htmlspecialchars($entry->action_short_term),
    'action_medium_term' => htmlspecialchars($entry->action_medium_term),
    'action_long_term' => htmlspecialchars($entry->action_long_term),
    
    // Notification
    'show_notification' => $show_notification,
    'notification_title' => $notification_title,
    'notification_msg' => $notification_msg,
    
    // JS Data
    'json_entry' => json_encode($entry),
];

echo $OUTPUT->render_from_template('block_student_path/student_path_form', $data);

echo $OUTPUT->footer();
