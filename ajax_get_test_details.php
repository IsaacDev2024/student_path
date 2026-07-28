<?php
/**
 * AJAX Handler to Get Test Details for Student Path Block
 *
 * @package    block_student_path
 * @copyright  2026 SAVIO - Sistema de Aprendizaje Virtual Interactivo (UTB)
 * @author     SAVIO Development Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(dirname(__FILE__) . '/lib.php');

// Check permissions
require_login();

$user_id = required_param('user_id', PARAM_INT);
$test_type = required_param('test_type', PARAM_TEXT);
$course_id = required_param('course_id', PARAM_INT); // New required param
$history_id = optional_param('history_id', 0, PARAM_INT);
$no_header = optional_param('no_header', 0, PARAM_BOOL);

// Verify Context and Permissions
$context = context_course::instance($course_id);
$PAGE->set_context($context);

require_capability('block/student_path:viewstudentdata', $context);

// Anti-Gossip Check: Ensure student is enrolled in this course
if (!is_enrolled($context, $user_id, '', true)) {
    echo json_encode([
        'status' => 'error',
        'message' => get_string('user_not_enrolled', 'block_student_path')
    ]);
    exit;
}

// Validate sesskey
if (!confirm_sesskey()) {
    echo json_encode([
        'status' => 'error',
        'message' => get_string('sesskey_error', 'block_student_path') // Or a generic error
    ]);
    exit;
}

$response = [
    'status' => 'error',
    'html' => '',
    'message' => ''
];

try {
    $user = $DB->get_record('user', ['id' => $user_id], '*', MUST_EXIST);
    
    // Header Info for Teacher (Basic)
    $header_html = '';
    if (!$no_header) {
        $header_html = "<div class='student-header'>";
        $header_html .= $OUTPUT->user_picture($user, ['size' => 100, 'class' => 'student-avatar']);
        $header_html .= "<div class='student-info'>"; // Wrapper for text
        $header_html .= "<h4>" . fullname($user) . "</h4>";
        $header_html .= "</div></div>"; // Close wrapper and header
    }

    switch ($test_type) {
        case 'chaside':
            handle_chaside($user_id, $response, $header_html);
            break;

        case 'learning_style':
            handle_learning_style($user_id, $response, $header_html);
            break;

        case 'personality':
            handle_personality($user_id, $response, $header_html);
            break;

        case 'tmms24':
            handle_tmms24($user_id, $response, $header_html, $user);
            break;

        case 'student_path':
            handle_student_path($user_id, $response, $header_html, $history_id);
            break;

        default:
            throw new Exception("Tipo de test desconocido: $test_type");
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);

// -------------------------------------------------------------------------
// Handler Functions
// -------------------------------------------------------------------------

function handle_chaside($user_id, &$response, $header) {
    global $DB;
    $record = $DB->get_record('block_chaside_responses', ['userid' => $user_id]);
    
    // Calculate progress
    $answered = 0;
    $total_questions = 98;
    if ($record) {
        for ($i = 1; $i <= $total_questions; $i++) {
            if (isset($record->{'q'.$i}) && $record->{'q'.$i} !== null) {
                $answered++;
            }
        }
    }

    if ($record && $record->is_completed) {
        $areas = ['C', 'H', 'A', 'S', 'I', 'D', 'E'];
        $scores = [];
        
        // We need to calculate detailed scores for the tie-breaker
        $mapping = get_chaside_questions_mapping();
        $interest_questions = $mapping['interest'];
        $aptitude_questions = $mapping['aptitude'];

        $detailed = [];
        foreach ($areas as $area) {
            $int_score = 0;
            foreach ($interest_questions[$area] as $q) {
                if (isset($record->{'q'.$q}) && $record->{'q'.$q} == 1) $int_score++;
            }
            $apt_score = 0;
            foreach ($aptitude_questions[$area] as $q) {
                if (isset($record->{'q'.$q}) && $record->{'q'.$q} == 1) $apt_score++;
            }
            $total = $int_score + $apt_score;
            $detailed[] = [
                'area' => $area,
                'total' => $total,
                'aptitud' => $apt_score,
                'interes' => $int_score,
                'gap' => abs($int_score - $apt_score)
            ];
            $scores[$area] = $total;
        }

        // Official Tie-breaker Sort
        usort($detailed, function($a, $b) {
            if ($a['total'] != $b['total']) return $b['total'] - $a['total'];
            if ($a['aptitud'] != $b['aptitud']) return $b['aptitud'] - $a['aptitud'];
            if ($a['gap'] != $b['gap']) return $a['gap'] - $b['gap'];
            return strcmp($a['area'], $b['area']);
        });

        $top_areas = [$detailed[0]['area'], $detailed[1]['area']];
        
        $html = $header;
        $html .= '<div class="test-result-container chaside-theme">';
        $html .= '<h3>' . get_string('vocational_results', 'block_student_path') . ' (CHASIDE)</h3>';
        
        $html .= '<div class="chaside-grid-container">';
        foreach ($detailed as $index => $item) {
            $area = $item['area'];
            $is_top = in_array($area, $top_areas);
            $class = $is_top ? 'chaside-card highlight' : 'chaside-card';
            $area_name = get_string('area_'.strtolower($area), 'block_student_path');
            $desc = get_chaside_area_desc($area);
            $score = $item['total'];
            
            $html .= "<div class='$class'>";
            
            // Add trophy icon for 1st and 2nd place
            if ($index === 0) {
                $html .= '<div class="rank-icon gold"><i class="fa fa-trophy"></i></div>';
            } elseif ($index === 1) {
                $html .= '<div class="rank-icon silver"><i class="fa fa-trophy"></i></div>';
            }

            $html .= "<div class='card-header'><span class='letter'>$area</span> <span class='score'>$score pts</span></div>";
            $html .= "<div class='card-body'><strong>$area_name</strong><p class='small-desc'>$desc</p></div>";
            $html .= "</div>";
        }
        $html .= '</div>';
        
        $html .= '</div>';
        
        $response['html'] = $html;
        $response['status'] = 'success';
    } else {
        $response['html'] = $header . render_in_progress_view($answered, $total_questions, 'chaside-progress');
        $response['status'] = 'in-progress';
    }
}

function handle_tmms24($user_id, &$response, $header, $user) {
    global $DB;
    $record = $DB->get_record('tmms_24', ['user' => $user_id]);
    
    // Calculate progress
    $answered = 0;
    $total_questions = 24;
    if ($record) {
        for ($i = 1; $i <= $total_questions; $i++) {
            if (isset($record->{'item'.$i}) && $record->{'item'.$i} !== null) {
                $answered++;
            }
        }
    }

    if ($record && $record->is_completed) {
        // Use demographics from record directly
        $age_display = !empty($record->age) ? $record->age . ' ' . get_string('years', 'block_student_path') : get_string('unknown', 'block_student_path');
        
        $gender_map = [
            'M' => get_string('gender_male', 'block_student_path'),
            'F' => get_string('gender_female', 'block_student_path'),
            'prefiero_no_decir' => get_string('not_specified', 'block_student_path')
        ];
        $gender_display = isset($gender_map[$record->gender]) ? $gender_map[$record->gender] : get_string('unknown', 'block_student_path');

        $is_male = ($record->gender == 'M');
        $gender_key = $is_male ? 'male' : 'female';
        
        $dimensions = [
            'perception' => $record->percepcion_score,
            'comprehension' => $record->comprension_score,
            'regulation' => $record->regulacion_score
        ];
        
        $html = $header;
        // Add meta info to header
        $html = str_replace("</h4>", "</h4><p class='student-meta'>" . get_string('age', 'block_student_path') . ": $age_display | " . get_string('gender', 'block_student_path') . ": $gender_display</p>", $html);
        
        $html .= '<div class="test-result-container tmms24-theme">';
        $html .= '<h3>' . get_string('emotional_intelligence_results', 'block_student_path') . ' (TMMS-24)</h3>';
        
        foreach ($dimensions as $dim => $score) {
            $level_info = get_tmms24_level_info($dim, $score, $gender_key);
            $lang_key = $level_info['lang_key'];
            
            $title = get_string('tmms_'.$dim, 'block_student_path');
            
            if (get_string_manager()->string_exists($lang_key, 'block_tmms_24')) {
                $interpretation = get_string($lang_key, 'block_tmms_24');
            } else {
                $interpretation = "Interpretación no disponible.";
            }

            $html .= "<div class='dimension-block'>";
            $html .= "<div class='dim-header'><h4>$title</h4> <span class='dim-score'>" . get_string('score_pts', 'block_student_path', $score) . "</span></div>";
            $html .= "<div class='interpretation-text'>$interpretation</div>";
            $html .= "</div>";
        }
        
        $html .= '</div>';
        $response['html'] = $html;
        $response['status'] = 'success';
    } else {
        $html = $header;
        if ($record && (!empty($record->age) || !empty($record->gender))) {
            $age_display = !empty($record->age) ? $record->age . ' ' . get_string('years', 'block_student_path') : get_string('unknown', 'block_student_path');
            $gender_map = ['M' => get_string('gender_male', 'block_student_path'), 'F' => get_string('gender_female', 'block_student_path'), 'prefiero_no_decir' => get_string('not_specified', 'block_student_path')];
            $gender_display = isset($gender_map[$record->gender]) ? $gender_map[$record->gender] : get_string('unknown', 'block_student_path');
            $html = str_replace("</h4>", "</h4><p class='student-meta'>" . get_string('age', 'block_student_path') . ": $age_display | " . get_string('gender', 'block_student_path') . ": $gender_display</p>", $html);
        }
        $response['html'] = $html . render_in_progress_view($answered, $total_questions, 'tmms24-progress');
        $response['status'] = 'in-progress';
    }
}

function handle_learning_style($user_id, &$response, $header) {
    global $DB;
    $record = $DB->get_record('learning_style', ['user' => $user_id]);
    
    $answered = 0;
    $total_questions = 44; 
    if ($record) {
        for ($i = 1; $i <= $total_questions; $i++) {
            if (isset($record->{'q'.$i}) && $record->{'q'.$i} !== null) {
                $answered++;
            }
        }
    }

    if ($record && $record->is_completed) {
        $dimensions = [
            ['left' => get_string('ls_active', 'block_student_path'), 'right' => get_string('ls_reflective', 'block_student_path'), 'l_val' => $record->ap_active, 'r_val' => $record->ap_reflexivo, 'key' => 'active_reflexive'],
            ['left' => get_string('ls_sensing', 'block_student_path'), 'right' => get_string('ls_intuitive', 'block_student_path'), 'l_val' => $record->ap_sensorial, 'r_val' => $record->ap_intuitivo, 'key' => 'sensing_intuitive'],
            ['left' => get_string('ls_visual', 'block_student_path'), 'right' => get_string('ls_verbal', 'block_student_path'), 'l_val' => $record->ap_visual, 'r_val' => $record->ap_verbal, 'key' => 'visual_verbal'],
            ['left' => get_string('ls_sequential', 'block_student_path'), 'right' => get_string('ls_global', 'block_student_path'), 'l_val' => $record->ap_secuencial, 'r_val' => $record->ap_global, 'key' => 'sequential_global']
        ];
        
        // Construct summary sentence
        $winners = [];
        foreach ($dimensions as $dim) {
            $winners[] = ($dim['l_val'] >= $dim['r_val']) ? $dim['left'] : $dim['right'];
        }
        $summary_text = implode(", ", $winners);
        $summary = get_string('predominant_style', 'block_student_path', $summary_text);

        $html = $header;
        $html .= '<div class="test-result-container learning-style-theme">';
        $html .= '<h3>' . get_string('learning_styles_results', 'block_student_path') . ' (Felder-Silverman)</h3>';
        $html .= "<div class='ls-summary-sentence'>$summary</div>";
        
        foreach ($dimensions as $dim) {
            $total = $dim['l_val'] + $dim['r_val'];
            $l_pct = $total > 0 ? ($dim['l_val'] / $total) * 100 : 50;
            $r_pct = $total > 0 ? ($dim['r_val'] / $total) * 100 : 50;
            
            $html .= "<div class='ls-dimension-box'>";
            $html .= "<div class='ls-chart'>";
            $html .= "<span class='ls-label left'>{$dim['left']} ({$dim['l_val']})</span>";
            $html .= "<div class='ls-bar-container'><div class='ls-bar-fill left' style='width: {$l_pct}%'></div><div class='ls-bar-fill right' style='width: {$r_pct}%'></div></div>";
            $html .= "<span class='ls-label right'>{$dim['right']} ({$dim['r_val']})</span>";
            $html .= "</div>";
            $html .= "</div>";
        }
        
        $html .= '</div>';
        $response['html'] = $html;
        $response['status'] = 'success';
    } else {
        $response['html'] = $header . render_in_progress_view($answered, $total_questions, 'learning-style-progress');
        $response['status'] = 'in-progress';
    }
}

function handle_personality($user_id, &$response, $header) {
    global $DB;
    $record = $DB->get_record('personality_test', ['user' => $user_id]);
    
    $answered = 0;
    $total_questions = 72;
    if ($record) {
        for ($i = 1; $i <= $total_questions; $i++) {
            if (isset($record->{'q'.$i}) && $record->{'q'.$i} !== null) {
                $answered++;
            }
        }
    }

    if ($record && $record->is_completed) {
        // Calculate Type if not stored (though it usually is)
        // But let's recalculate to be safe or use stored
        $type = '';
        $type .= ($record->extraversion >= $record->introversion) ? 'E' : 'I';
        $type .= ($record->sensing >= $record->intuition) ? 'S' : 'N';
        $type .= ($record->thinking >= $record->feeling) ? 'T' : 'F';
        $type .= ($record->judging >= $record->perceptive) ? 'J' : 'P';
        
        $mbti_type = strtolower($type);
        
        $html = $header;
        $html .= '<div class="test-result-container personality-theme">';
        $html .= '<h3>' . get_string('personality_mbti_results', 'block_student_path') . '</h3>';
        
        $html .= '<div class="mbti-hero">';
        $html .= '<div class="mbti-badge">' . strtoupper($type) . '</div>';
        $html .= '<div class="mbti-title">' . get_string('mbti_dimensions_' . $mbti_type, 'block_student_path') . '</div>';
        $html .= '</div>';
        
        $description = get_string('mbti_' . $mbti_type, 'block_student_path');
        $html .= "<div class='mbti-content'>";
        $html .= "<h4>" . get_string('profile', 'block_student_path') . "</h4>";
        $html .= "<p>$description</p>";
        $html .= "</div>";
        
        $html .= '</div>';
        $response['html'] = $html;
        $response['status'] = 'success';
    } else {
        $response['html'] = $header . render_in_progress_view($answered, $total_questions, 'personality-progress');
        $response['status'] = 'in-progress';
    }
}

function handle_student_path($user_id, &$response, $header, $history_id = 0) {
    global $DB;
    
    $record = null;
    $is_history_view = false;

    if ($history_id > 0) {
        $h_record = $DB->get_record('block_student_path_history', ['id' => $history_id, 'userid' => $user_id]);
        if ($h_record) {
            $content = json_decode($h_record->content);
            if ($content) {
                $record = $content;
                $is_history_view = true;
                // History records are snapshots of completed states usually
                $record->is_completed = 1; 
            }
        }
    }

    if (!$record) {
        $record = $DB->get_record('block_student_path', ['user' => $user_id]);
    }
    
    // Calculate progress locally to avoid dependency issues
    $fields_to_check = [
        'name', 'program', 'admission_year', 'admission_semester', 'email', 'code',
        'personality_strengths', 'personality_weaknesses', 
        'vocational_areas', 'vocational_areas_secondary', 'vocational_description',
        'emotional_skills_level',
        'goal_short_term', 'goal_medium_term', 'goal_long_term',
        'action_short_term', 'action_medium_term', 'action_long_term'
    ];
    
    $total = count($fields_to_check);
    $filled = 0;
    $is_completed = false;

    if ($record) {
        foreach ($fields_to_check as $field) {
            if (!empty($record->$field)) {
                $filled++;
            }
        }
        if (isset($record->is_completed) && $record->is_completed == 1) {
            $is_completed = true;
        } elseif ($filled >= $total) {
            $is_completed = true;
        }
    }

    if ($record && $is_completed) {
        // If it's an AJAX call for history, we might not want the header if we are just replacing the body
        // But the current structure expects header. We can strip it in JS or just include it.
        // For the modal view, we need the header. For the card update, we might not.
        // However, let's keep it consistent.
        
        $html = $header;

        // Use the shared helper function to render content
        $html .= render_student_path_content($record, $is_history_view);

        $response['html'] = $html;
        $response['status'] = 'success';
    } elseif ($record) {
        // In Progress
        $response['html'] = $header . render_in_progress_view($filled, $total, 'student-path-progress');
        $response['status'] = 'in-progress';
    } else {
        $response['html'] = '<div class="alert alert-warning">' . get_string('no_data', 'block_student_path') . '</div>';
        $response['status'] = 'not-started';
    }
}

// -------------------------------------------------------------------------
// Helpers
// -------------------------------------------------------------------------



function get_chaside_area_desc($letter) {
    return get_string('area_' . strtolower($letter) . '_desc', 'block_student_path');
}

function get_tmms24_level_info($dim, $score, $gender) {
    // Returns ['lang_key' => '...']
    // Dimensions: perception, comprehension, regulation
    
    $key = 'adequate'; // default
    
    if ($dim == 'perception') {
        if ($gender == 'male') {
            if ($score <= 21) $key = 'difficulty_feeling'; // Low
            elseif ($score <= 32) $key = 'adequate_feeling'; // Adequate
            else $key = 'excessive_attention'; // High
        } else {
            if ($score <= 24) $key = 'difficulty_feeling';
            elseif ($score <= 35) $key = 'adequate_feeling';
            else $key = 'excessive_attention';
        }
    } elseif ($dim == 'comprehension') {
        if ($gender == 'male') {
            if ($score <= 25) $key = 'difficulty_understanding';
            elseif ($score <= 35) $key = 'adequate_with_difficulties';
            else $key = 'great_clarity';
        } else {
            if ($score <= 23) $key = 'difficulty_understanding';
            elseif ($score <= 34) $key = 'adequate_with_difficulties';
            else $key = 'great_clarity';
        }
    } elseif ($dim == 'regulation') {
        if ($gender == 'male') {
            if ($score <= 23) $key = 'difficulty_managing';
            elseif ($score <= 35) $key = 'adequate_balance';
            else $key = 'great_capacity';
        } else {
            if ($score <= 23) $key = 'difficulty_managing';
            elseif ($score <= 34) $key = 'adequate_balance';
            else $key = 'great_capacity';
        }
    }
    
    // Construct full lang key
    // Format: dimension_key
    // e.g. perception_difficulty_feeling
    return ['lang_key' => "{$dim}_{$key}"];
}
