<?php
/**
 * Export Functionality - Student Path Block
 *
 * @package    block_student_path
 * @copyright  2026 SAVIO - Sistema de Aprendizaje Virtual Interactivo (UTB)
 * @author     SAVIO Development Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(dirname(__FILE__) . '/lib.php');

require_login();

$courseid = required_param('cid', PARAM_INT);
$format = optional_param('format', 'excel', PARAM_ALPHA);
$status_filter = optional_param('status', 'all', PARAM_ALPHANUM);

// Verify permissions
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($courseid);
require_capability('block/student_path:viewstudentdata', $context);

// Security Check
confirm_sesskey();

$filename = get_string('export_student_path', 'block_student_path') . '_' . clean_filename($course->shortname) . '_' . date('Y-m-d');
if ($status_filter !== 'all') {
    $filename .= '_' . $status_filter;
}

// Define Columns (Associative array: key => label)
$columns = [
    'fullname' => get_string('fullname'),
    'email' => get_string('email'),
    
    'program' => get_string('program', 'block_student_path'),
    'code' => get_string('code', 'block_student_path'),
    'admission_year' => get_string('admission_year', 'block_student_path'),
    'admission_semester' => get_string('admission_semester', 'block_student_path'),

    'status_ls' => get_string('learning_style_test', 'block_student_path') . ' - ' . get_string('status', 'block_student_path'),
    'status_pt' => get_string('personality_test', 'block_student_path') . ' - ' . get_string('status', 'block_student_path'),
    'status_ch' => get_string('chaside_test', 'block_student_path') . ' - ' . get_string('status', 'block_student_path'),
    'status_tm' => get_string('tmms_24_test', 'block_student_path') . ' - ' . get_string('status', 'block_student_path'),
    'status_sp' => get_string('student_profile', 'block_student_path') . ' - ' . get_string('status', 'block_student_path'),
    
    'personality_strengths' => get_string('personality_strengths', 'block_student_path'),
    'personality_weaknesses' => get_string('personality_weaknesses', 'block_student_path'),
    
    'vocational_areas' => get_string('vocational_areas', 'block_student_path'),
    'vocational_areas_secondary' => get_string('vocational_areas_secondary', 'block_student_path'),
    'vocational_description' => get_string('vocational_description', 'block_student_path'),
    
    'emotional_skills_level' => get_string('emotional_skills_level', 'block_student_path'),
    
    'goal_short_term' => get_string('goal_short_term', 'block_student_path'),
    'action_short_term' => get_string('action_short_term', 'block_student_path'),
    'goal_medium_term' => get_string('goal_medium_term', 'block_student_path'),
    'action_medium_term' => get_string('action_medium_term', 'block_student_path'),
    'goal_long_term' => get_string('goal_long_term', 'block_student_path'),
    'action_long_term' => get_string('action_long_term', 'block_student_path'),
    
    'ls_active' => get_string('learning_style_test', 'block_student_path') . ' - ' . get_string('ls_active', 'block_student_path'),
    'ls_reflective' => get_string('learning_style_test', 'block_student_path') . ' - ' . get_string('ls_reflective', 'block_student_path'),
    'ls_sensing' => get_string('learning_style_test', 'block_student_path') . ' - ' . get_string('ls_sensing', 'block_student_path'),
    'ls_intuitive' => get_string('learning_style_test', 'block_student_path') . ' - ' . get_string('ls_intuitive', 'block_student_path'),
    'ls_visual' => get_string('learning_style_test', 'block_student_path') . ' - ' . get_string('ls_visual', 'block_student_path'),
    'ls_verbal' => get_string('learning_style_test', 'block_student_path') . ' - ' . get_string('ls_verbal', 'block_student_path'),
    'ls_sequential' => get_string('learning_style_test', 'block_student_path') . ' - ' . get_string('ls_sequential', 'block_student_path'),
    'ls_global' => get_string('learning_style_test', 'block_student_path') . ' - ' . get_string('ls_global', 'block_student_path'),
    'ls_result' => get_string('learning_style_test', 'block_student_path') . ' - ' . get_string('result', 'block_student_path'),
    
    'pt_e' => get_string('personality_test', 'block_student_path') . ' - E',
    'pt_i' => get_string('personality_test', 'block_student_path') . ' - I',
    'pt_s' => get_string('personality_test', 'block_student_path') . ' - S',
    'pt_n' => get_string('personality_test', 'block_student_path') . ' - N',
    'pt_t' => get_string('personality_test', 'block_student_path') . ' - T',
    'pt_f' => get_string('personality_test', 'block_student_path') . ' - F',
    'pt_j' => get_string('personality_test', 'block_student_path') . ' - J',
    'pt_p' => get_string('personality_test', 'block_student_path') . ' - P',
    'pt_result' => get_string('personality_test', 'block_student_path') . ' - ' . get_string('result', 'block_student_path'),
    
    'ch_c' => get_string('chaside_test', 'block_student_path') . ' - C',
    'ch_h' => get_string('chaside_test', 'block_student_path') . ' - H',
    'ch_a' => get_string('chaside_test', 'block_student_path') . ' - A',
    'ch_s' => get_string('chaside_test', 'block_student_path') . ' - S',
    'ch_i' => get_string('chaside_test', 'block_student_path') . ' - I',
    'ch_d' => get_string('chaside_test', 'block_student_path') . ' - D',
    'ch_e' => get_string('chaside_test', 'block_student_path') . ' - E',
    'ch_top1' => get_string('chaside_test', 'block_student_path') . ' - Top 1',
    'ch_top2' => get_string('chaside_test', 'block_student_path') . ' - Top 2',
    
    'tm_p' => get_string('tmms_24_test', 'block_student_path') . ' - ' . get_string('tmms_perception', 'block_student_path'),
    'tm_c' => get_string('tmms_24_test', 'block_student_path') . ' - ' . get_string('tmms_comprehension', 'block_student_path'),
    'tm_r' => get_string('tmms_24_test', 'block_student_path') . ' - ' . get_string('tmms_regulation', 'block_student_path'),
];

// Get Users
$users = get_course_users_with_test_progress($courseid);

// Filter Users if status is set
if ($status_filter !== 'all') {
    $users = array_filter($users, function($user) use ($status_filter) {
        // Calculate global status (same logic as admin_view.php)
        $global_status = 'not-started';
        if ($user->total_completed == 5) {
            $global_status = 'completed';
        } else if ($user->total_completed > 0 || $user->total_in_progress > 0) {
            $global_status = 'in-progress';
        }
        
        return $global_status === $status_filter;
    });
}

// Optimization: Bulk fetch detailed test data to avoid N+1 queries
$userids = array_map(function($u) { return $u->id; }, $users);
$ls_by_user = [];
$pt_by_user = [];
$tm_by_user = [];
$ch_by_user = [];

if (!empty($userids)) {
    // Learning Style
    $records = $DB->get_records_list('learning_style', 'user', $userids);
    foreach ($records as $r) $ls_by_user[$r->user] = $r;

    // Personality Test
    $records = $DB->get_records_list('personality_test', 'user', $userids);
    foreach ($records as $r) $pt_by_user[$r->user] = $r;

    // TMMS-24
    $records = $DB->get_records_list('tmms_24', 'user', $userids);
    foreach ($records as $r) $tm_by_user[$r->user] = $r;

    // CHASIDE
    $records = $DB->get_records_list('block_chaside_responses', 'userid', $userids);
    foreach ($records as $r) $ch_by_user[$r->userid] = $r;
}

// Callback function to process each user
$callback = function($user) use ($ls_by_user, $pt_by_user, $tm_by_user, $ch_by_user) {
    $row = [];
    
    // --- User Info ---
    $row['fullname'] = fullname($user);
    $row['email'] = $user->email;
    
    // --- Student Path Data ---
    // Data is already in $user object from get_course_users_with_test_progress
    $row['program'] = $user->program ?? '';
    $row['code'] = $user->code ?? '';
    $row['admission_year'] = $user->admission_year ?? '';
    $row['admission_semester'] = $user->admission_semester ?? '';

    // --- Statuses ---
    $row['status_ls'] = get_string(str_replace('-', '_', $user->learning_style_status), 'block_student_path');
    $row['status_pt'] = get_string(str_replace('-', '_', $user->personality_status), 'block_student_path');
    $row['status_ch'] = get_string(str_replace('-', '_', $user->chaside_status), 'block_student_path');
    $row['status_tm'] = get_string(str_replace('-', '_', $user->tmms24_status), 'block_student_path');
    $row['status_sp'] = get_string(str_replace('-', '_', $user->student_path_status), 'block_student_path');
    
    $row['personality_strengths'] = $user->personality_strengths ?? '';
    $row['personality_weaknesses'] = $user->personality_weaknesses ?? '';
    
    $row['vocational_areas'] = $user->vocational_areas ?? '';
    $row['vocational_areas_secondary'] = $user->vocational_areas_secondary ?? '';
    $row['vocational_description'] = $user->vocational_description ?? '';
    
    $row['emotional_skills_level'] = $user->emotional_skills_level ?? '';
    
    $row['goal_short_term'] = $user->goal_short_term ?? '';
    $row['action_short_term'] = $user->action_short_term ?? '';
    $row['goal_medium_term'] = $user->goal_medium_term ?? '';
    $row['action_medium_term'] = $user->action_medium_term ?? '';
    $row['goal_long_term'] = $user->goal_long_term ?? '';
    $row['action_long_term'] = $user->action_long_term ?? '';
    
    // --- Learning Style ---
    $ls_record = $ls_by_user[$user->id] ?? null;
    if ($ls_record && $ls_record->is_completed) {
        $row['ls_active'] = $ls_record->ap_active;
        $row['ls_reflective'] = $ls_record->ap_reflexivo;
        $row['ls_sensing'] = $ls_record->ap_sensorial;
        $row['ls_intuitive'] = $ls_record->ap_intuitivo;
        $row['ls_visual'] = $ls_record->ap_visual;
        $row['ls_verbal'] = $ls_record->ap_verbal;
        $row['ls_sequential'] = $ls_record->ap_secuencial;
        $row['ls_global'] = $ls_record->ap_global;
        
        // Calculate Dominant Style String
        $styles = [];
        if ($ls_record->ap_active >= $ls_record->ap_reflexivo) $styles[] = get_string('ls_active', 'block_student_path');
        else $styles[] = get_string('ls_reflective', 'block_student_path');
        
        if ($ls_record->ap_sensorial >= $ls_record->ap_intuitivo) $styles[] = get_string('ls_sensing', 'block_student_path');
        else $styles[] = get_string('ls_intuitive', 'block_student_path');
        
        if ($ls_record->ap_visual >= $ls_record->ap_verbal) $styles[] = get_string('ls_visual', 'block_student_path');
        else $styles[] = get_string('ls_verbal', 'block_student_path');
        
        if ($ls_record->ap_secuencial >= $ls_record->ap_global) $styles[] = get_string('ls_sequential', 'block_student_path');
        else $styles[] = get_string('ls_global', 'block_student_path');
        
        $row['ls_result'] = implode(', ', $styles);
    } else {
        $row['ls_active'] = '';
        $row['ls_reflective'] = '';
        $row['ls_sensing'] = '';
        $row['ls_intuitive'] = '';
        $row['ls_visual'] = '';
        $row['ls_verbal'] = '';
        $row['ls_sequential'] = '';
        $row['ls_global'] = '';
        $row['ls_result'] = '';
    }
    
    // --- Personality (MBTI) ---
    $pt_record = $pt_by_user[$user->id] ?? null;
    if ($pt_record && $pt_record->is_completed) {
        $row['pt_e'] = $pt_record->extraversion;
        $row['pt_i'] = $pt_record->introversion;
        $row['pt_s'] = $pt_record->sensing;
        $row['pt_n'] = $pt_record->intuition;
        $row['pt_t'] = $pt_record->thinking;
        $row['pt_f'] = $pt_record->feeling;
        $row['pt_j'] = $pt_record->judging;
        $row['pt_p'] = $pt_record->perceptive;
        
        // Calculate Type
        $type = '';
        $type .= ($pt_record->extraversion >= $pt_record->introversion) ? 'E' : 'I';
        $type .= ($pt_record->sensing >= $pt_record->intuition) ? 'S' : 'N';
        $type .= ($pt_record->thinking >= $pt_record->feeling) ? 'T' : 'F';
        $type .= ($pt_record->judging >= $pt_record->perceptive) ? 'J' : 'P';
        $row['pt_result'] = $type;
    } else {
        $row['pt_e'] = '';
        $row['pt_i'] = '';
        $row['pt_s'] = '';
        $row['pt_n'] = '';
        $row['pt_t'] = '';
        $row['pt_f'] = '';
        $row['pt_j'] = '';
        $row['pt_p'] = '';
        $row['pt_result'] = '';
    }
    
    // --- CHASIDE ---
    $ch_record = $ch_by_user[$user->id] ?? null;
    if ($ch_record && $ch_record->is_completed) {
        $ch_data = (array)$ch_record;
        $results = calculate_chaside_results_simple($ch_data);
        $areas = $results['areas']; // Percentages
        
        $row['ch_c'] = $areas['C'] . '%';
        $row['ch_h'] = $areas['H'] . '%';
        $row['ch_a'] = $areas['A'] . '%';
        $row['ch_s'] = $areas['S'] . '%';
        $row['ch_i'] = $areas['I'] . '%';
        $row['ch_d'] = $areas['D'] . '%';
        $row['ch_e'] = $areas['E'] . '%';
        
        // Top 2
        arsort($areas);
        $keys = array_keys($areas);
        $row['ch_top1'] = $keys[0] ?? '';
        $row['ch_top2'] = $keys[1] ?? '';
        
    } else {
        $row['ch_c'] = '';
        $row['ch_h'] = '';
        $row['ch_a'] = '';
        $row['ch_s'] = '';
        $row['ch_i'] = '';
        $row['ch_d'] = '';
        $row['ch_e'] = '';
        $row['ch_top1'] = '';
        $row['ch_top2'] = '';
    }
    
    // --- TMMS-24 ---
    $tm_record = $tm_by_user[$user->id] ?? null;
    if ($tm_record && $tm_record->is_completed) {
        $row['tm_p'] = $tm_record->percepcion_score;
        $row['tm_c'] = $tm_record->comprension_score;
        $row['tm_r'] = $tm_record->regulacion_score;
    } else {
        $row['tm_p'] = '';
        $row['tm_c'] = '';
        $row['tm_r'] = '';
    }
    
    return $row;
};

// Send download
\core\dataformat::download_data($filename, $format, $columns, $users, $callback);
