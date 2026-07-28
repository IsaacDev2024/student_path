<?php
/**
 * Student Path Block Library Functions
 *
 * @package    block_student_path
 * @copyright  2026 SAVIO - Sistema de Aprendizaje Virtual Interactivo (UTB)
 * @author     SAVIO Development Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Obtiene el mapeo de preguntas CHASIDE
 */
function get_chaside_questions_mapping() {
    // Interest questions (70 total) - MAPEO OFICIAL CORRECTO
    $interest_questions = [
        'C' => [1, 12, 20, 53, 64, 71, 78, 85, 91, 98],
        'H' => [9, 25, 34, 41, 56, 67, 74, 80, 89, 95],
        'A' => [3, 11, 21, 28, 36, 45, 50, 57, 81, 96],
        'S' => [8, 16, 23, 33, 44, 52, 62, 70, 87, 92],
        'I' => [6, 19, 27, 38, 47, 54, 60, 75, 83, 97],
        'D' => [5, 14, 24, 31, 37, 48, 58, 65, 73, 84],
        'E' => [17, 32, 35, 42, 49, 61, 68, 77, 88, 93]
    ];
    
    // Aptitude questions (28 total) - MAPEO OFICIAL CORRECTO  
    $aptitude_questions = [
        'C' => [2, 15, 46, 51],
        'H' => [30, 63, 72, 86],
        'A' => [22, 39, 76, 82],
        'S' => [4, 29, 40, 69],
        'I' => [10, 26, 59, 90],
        'D' => [13, 18, 43, 66],
        'E' => [7, 55, 79, 94]
    ];

    return ['interest' => $interest_questions, 'aptitude' => $aptitude_questions];
}

/**
 * Calcula los resultados de CHASIDE
 */
function calculate_chaside_results_simple($data) {
    // Mapeo oficial de preguntas CHASIDE a áreas (EXACTO como en el bloque CHASIDE)
    $mapping = get_chaside_questions_mapping();
    $interest_questions = $mapping['interest'];
    $aptitude_questions = $mapping['aptitude'];
    
    // Inicializar puntuaciones
    $scores = [];
    foreach (['C', 'H', 'A', 'S', 'I', 'D', 'E'] as $area) {
        $scores[$area] = [
            'interes_score' => 0,
            'aptitud_score' => 0
        ];
    }
    
    // Calcular puntuaciones de intereses
    foreach ($interest_questions as $area => $questions) {
        foreach ($questions as $q_num) {
            $field_name = 'q' . $q_num;
            if (isset($data[$field_name]) && $data[$field_name] == 1) {
                $scores[$area]['interes_score']++;
            }
        }
    }
    
    // Calcular puntuaciones de aptitudes
    foreach ($aptitude_questions as $area => $questions) {
        foreach ($questions as $q_num) {
            $field_name = 'q' . $q_num;
            if (isset($data[$field_name]) && $data[$field_name] == 1) {
                $scores[$area]['aptitud_score']++;
            }
        }
    }
    
    // Calcular porcentajes EXACTAMENTE como en el bloque CHASIDE
    $percentages = [];
    foreach ($scores as $area => $area_scores) {
        $total_score = $area_scores['interes_score'] + $area_scores['aptitud_score'];
        
        $percentages[$area] = round(100 * $total_score / 14, 1); // Max 14 total per area
    }
    
    return ['areas' => $percentages, 'detailed_scores' => $scores];
}

/**
 * Obtiene el nombre completo del área CHASIDE
 */
function get_chaside_area_name($area_code) {
    // Nombres exactos como aparecen en el bloque CHASIDE (basados en las cadenas de idioma más recientes)
    $area_names = [
        'C' => get_string('area_c', 'block_student_path'),
        'H' => get_string('area_h', 'block_student_path'),
        'A' => get_string('area_a', 'block_student_path'),
        'S' => get_string('area_s', 'block_student_path'),
        'I' => get_string('area_i', 'block_student_path'),
        'D' => get_string('area_d', 'block_student_path'),
        'E' => get_string('area_e', 'block_student_path')
    ];
    
    return isset($area_names[$area_code]) ? $area_names[$area_code] : $area_code;
}

/**
 * Genera un resumen completo del test CHASIDE para vista detallada
 */
function get_chaside_summary_complete($chaside_data) {
    global $PAGE;
    $renderer = $PAGE->get_renderer('block_student_path');

    if (!$chaside_data) {
        return $renderer->render_not_started_view('chaside');
    }
    
    $data = json_decode($chaside_data, true);
    if (!$data) {
        if (is_array($chaside_data)) {
            $data = $chaside_data;
        } else {
            return $renderer->render_not_started_view('chaside');
        }
    }

    // Check completion
    if (empty($data['is_completed'])) {
        // Calculate progress
        $answered = 0;
        $total_questions = 98;
        for ($i = 1; $i <= $total_questions; $i++) {
            if (isset($data['q'.$i]) && $data['q'.$i] !== null) {
                $answered++;
            }
        }
        return $renderer->render_in_progress_view($answered, $total_questions, 'chaside-progress');
    }
    
    // Procesar datos directamente
    $results = calculate_chaside_results_simple($data);
    
    if (!$results || !isset($results['areas'])) {
        return $renderer->render_alert(get_string('error_processing', 'block_student_path'));
    }
    
    $areas = $results['areas'];
    $detailed_scores = isset($results['detailed_scores']) ? $results['detailed_scores'] : [];
    
    arsort($areas); // Ordenar por porcentaje descendente
    
    // Identificar las dos áreas principales
    $area_keys = array_keys($areas);
    $first_area_code = isset($area_keys[0]) ? $area_keys[0] : null;
    $second_area_code = isset($area_keys[1]) ? $area_keys[1] : null;
    
    $area_icons = [
        'C' => 'fa-calculator',
        'H' => 'fa-university',
        'A' => 'fa-paint-brush',
        'S' => 'fa-user-md',
        'I' => 'fa-cogs',
        'D' => 'fa-shield',
        'E' => 'fa-leaf'
    ];
    
    $processed_areas = [];
    foreach ($areas as $area_code => $percentage) {
        $area_name = get_chaside_area_name($area_code);
        $level_text = get_level_text($percentage);
        $icon = isset($area_icons[$area_code]) ? $area_icons[$area_code] : 'fa-star';
        
        $scores = isset($detailed_scores[$area_code]) ? $detailed_scores[$area_code] : ['interes_score' => 0, 'aptitud_score' => 0];
        $int_pct = ($scores['interes_score'] / 10) * 100;
        $apt_pct = ($scores['aptitud_score'] / 4) * 100;
        
        // Determinar si es ganadora o segunda
        $is_first = ($area_code === $first_area_code);
        $is_second = ($area_code === $second_area_code);
        
        // Colores de texto para el porcentaje (Nivel)
        $pct_color = '#6c757d'; // Default gris oscuro (más visible)
        if ($percentage >= 80.0) $pct_color = '#28a745';
        elseif ($percentage >= 60.0) $pct_color = '#17a2b8';
        elseif ($percentage >= 40.0) $pct_color = '#ffc107';
        
        // Estilo unificado Amarillo para todas las cards
        $card_border_top = '#ffc107'; // Amarillo oficial
        
        // Colores de barras (Amarillos oficiales)
        $bar_color_int = '#ffc107'; // Amarillo principal
        $bar_color_apt = '#ffca28'; // Amarillo secundario (Amber 400)
        
        // Gap Analysis (Brechas)
        $gap = $int_pct - $apt_pct;
        $gap_abs = round(abs($gap));
        
        $show_gap = false;
        $gap_icon = '';
        $gap_text = '';
        $gap_color = '';
        $is_balanced = false;

        if ($gap_abs >= 20) { // Only show if gap is significant
            $show_gap = true;
            $gap_icon = $gap > 0 ? 'fa-heart' : 'fa-graduation-cap';
            $gap_text = $gap > 0 ? get_string('chaside_dominant_interest', 'block_student_path') : get_string('chaside_dominant_aptitude', 'block_student_path');
            $gap_color = $gap > 0 ? '#ffc107' : '#ffca28'; // Match bar colors
        } else {
             $is_balanced = true;
        }

        $processed_areas[] = [
            'card_border_top' => $card_border_top,
            'icon_bg' => 'rgba(255, 193, 7, 0.1)',
            'icon_color' => '#b08d00',
            'icon' => $icon,
            'is_first' => $is_first,
            'is_second' => $is_second,
            'area_name' => $area_name,
            'level_text' => $level_text,
            'pct_color' => $pct_color,
            'percentage' => $percentage,
            'str_interests' => get_string('chaside_interests', 'block_student_path'),
            'interes_score' => $scores['interes_score'],
            'int_pct' => $int_pct,
            'bar_color_int' => $bar_color_int,
            'str_aptitudes' => get_string('chaside_aptitudes', 'block_student_path'),
            'aptitud_score' => $scores['aptitud_score'],
            'apt_pct' => $apt_pct,
            'bar_color_apt' => $bar_color_apt,
            'show_gap' => $show_gap,
            'str_gap' => get_string('chaside_gap', 'block_student_path'),
            'gap_abs' => $gap_abs,
            'gap_color' => $gap_color,
            'gap_icon' => $gap_icon,
            'gap_text' => $gap_text,
            'is_balanced' => $is_balanced,
            'str_balanced' => get_string('chaside_balanced', 'block_student_path')
        ];
    }
    
    return $renderer->render_chaside_summary(['areas' => $processed_areas]);
}

/**
 * Obtiene el texto del nivel según el porcentaje
 */
function get_level_text($percentage) {
    if ($percentage >= 80.0) {
        return get_string('chaside_level_high', 'block_student_path');
    } elseif ($percentage >= 60.0) {
        return get_string('chaside_level_medium', 'block_student_path');
    } elseif ($percentage >= 40.0) {
        return get_string('chaside_level_emergent', 'block_student_path');
    } else {
        return get_string('chaside_level_low', 'block_student_path');
    }
}

/**
 * Obtiene datos integrados de student_path, learning_style, personality_test, tmms_24 y chaside para un estudiante
 */
function get_integrated_student_profile($user_id, $course_id = null) {
    global $DB, $USER;
    
    // Security Check: BOLA Prevention
    $context = $course_id ? context_course::instance($course_id) : context_system::instance();
    if ($USER->id != $user_id && !has_capability('block/student_path:viewstudentdata', $context)) {
         // Security check failed
         throw new moodle_exception('nopermissions', 'error', '', null, 'view student profile');
    }
    
    $profile = new stdClass();
    
    // Datos básicos de student_path (independiente del curso)
    $student_path = $DB->get_record("block_student_path", array("user" => $user_id));
    $profile->student_path_data = $student_path ? json_encode($student_path) : null;
    
    // Extraer información específica de student_path para mejor presentación
    if ($student_path) {
        $profile->program = $student_path->program ?? '';
        $profile->admission_year = $student_path->admission_year ?? '';
        $profile->code = $student_path->code ?? '';
        $profile->personality_strengths = $student_path->personality_strengths ?? '';
        $profile->personality_weaknesses = $student_path->personality_weaknesses ?? '';
        $profile->vocational_description = $student_path->vocational_description ?? '';
        $profile->emotional_skills_level = $student_path->emotional_skills_level ?? '';
        $profile->goals_short = $student_path->goal_short_term ?? '';
        $profile->goals_medium = $student_path->goal_medium_term ?? '';
        $profile->goals_long = $student_path->goal_long_term ?? '';
        $profile->actions_short = $student_path->action_short_term ?? '';
        $profile->actions_medium = $student_path->action_medium_term ?? '';
        $profile->actions_long = $student_path->action_long_term ?? '';
    }
    
    // Datos de learning_style (independiente del curso)
    $learning_style = $DB->get_record("learning_style", array("user" => $user_id));
    $profile->learning_style = $learning_style ? 'completed' : null;
    $profile->learning_style_data = $learning_style ? json_encode($learning_style) : null;
    
    // Datos de personality_test (independiente del curso)
    $personality_test = $DB->get_record("personality_test", array("user" => $user_id));
    $profile->personality_traits = $personality_test ? 'completed' : null;
    $profile->personality_data = $personality_test ? json_encode($personality_test) : null;
    
    // Datos de tmms_24 (Inteligencia Emocional) (independiente del curso)
    $tmms_24 = $DB->get_record("tmms_24", array("user" => $user_id));
    $profile->emotional_intelligence = $tmms_24 ? 'completed' : null;
    $profile->tmms_24_data = $tmms_24 ? json_encode($tmms_24) : null;
    
    // Datos de CHASIDE (Test Vocacional) (independiente del curso)
    $chaside = $DB->get_record("block_chaside_responses", array("userid" => $user_id));
    $profile->chaside_completed = ($chaside && !empty($chaside->is_completed));
    $profile->chaside_data = $chaside ? json_encode($chaside) : null;
    
    // Extraer tipo Holland y puntuación de student_path
    if ($student_path && isset($student_path->vocational_areas)) {
        $profile->holland_type = $student_path->vocational_areas;
        $profile->holland_score = 100; // Placeholder, ajustar según datos reales
    } else {
        $profile->holland_type = null;
        $profile->holland_score = null;
    }
    
    // Calcular porcentaje de finalización
    $completed_tests = 0;
    if ($student_path && !empty($student_path->is_completed)) $completed_tests++;
    if ($learning_style && !empty($learning_style->is_completed)) $completed_tests++;
    if ($personality_test && !empty($personality_test->is_completed)) $completed_tests++;
    if ($tmms_24 && !empty($tmms_24->is_completed)) $completed_tests++;
    if ($chaside && !empty($chaside->is_completed)) $completed_tests++;
    
    $profile->completion_percentage = round(($completed_tests / 5) * 100);
    
    return $profile;
}

/**
 * Obtiene estadísticas integradas del curso para los cinco tipos de evaluaciones
 */
function get_integrated_course_stats($course_id, $users_array = null) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/lib/enrollib.php');
    
    $stats = new stdClass();
    $context = context_course::instance($course_id);

    // Security Check
    if (!has_capability('block/student_path:viewstudentdata', $context)) {
        throw new moodle_exception('nopermissions', 'error', '', null, 'view course stats');
    }

    if ($users_array === null) {
        $users_array = get_course_users_with_test_progress($course_id);
    }
    
    // 1. Total Students
    $stats->total_users = count($users_array);
    $stats->total_students = $stats->total_users; // Alias
    
    // Initialize counters
    $stats->chaside_completed = 0; $stats->chaside_in_progress = 0;
    $stats->learning_style_completed = 0; $stats->learning_style_in_progress = 0;
    $stats->personality_completed = 0; $stats->personality_in_progress = 0;
    $stats->tmms24_completed = 0; $stats->tmms24_in_progress = 0;
    $stats->student_path_completed = 0; $stats->student_path_in_progress = 0;
    
    $stats->active_users = 0;
    $stats->users_with_in_progress = 0;
    $stats->complete_profiles = 0;
    
    foreach ($users_array as $u) {
        // CHASIDE
        if ($u->chaside_status === 'completed') $stats->chaside_completed++;
        elseif ($u->chaside_status === 'in-progress') $stats->chaside_in_progress++;
        
        // Learning Style
        if ($u->learning_style_status === 'completed') $stats->learning_style_completed++;
        elseif ($u->learning_style_status === 'in-progress') $stats->learning_style_in_progress++;
        
        // Personality
        if ($u->personality_status === 'completed') $stats->personality_completed++;
        elseif ($u->personality_status === 'in-progress') $stats->personality_in_progress++;
        
        // TMMS-24
        if ($u->tmms24_status === 'completed') $stats->tmms24_completed++;
        elseif ($u->tmms24_status === 'in-progress') $stats->tmms24_in_progress++;
        
        // Student Path
        if ($u->student_path_status === 'completed') $stats->student_path_completed++;
        elseif ($u->student_path_status === 'in-progress') $stats->student_path_in_progress++;
        
        // Aggregate per user logic
        // Active if any test is completed or in progress
        if ($u->total_completed > 0 || $u->total_in_progress > 0) {
            $stats->active_users++;
        }
        
        if ($u->total_in_progress > 0) {
            $stats->users_with_in_progress++;
        }
        
        // Completed profile -> All 5 completely finished
        if ($u->total_completed == 5) {
            $stats->complete_profiles++;
        }
    }
    
    $stats->chaside_not_started = $stats->total_users - ($stats->chaside_completed + $stats->chaside_in_progress);
    $stats->learning_style_not_started = $stats->total_users - ($stats->learning_style_completed + $stats->learning_style_in_progress);
    $stats->personality_not_started = $stats->total_users - ($stats->personality_completed + $stats->personality_in_progress);
    $stats->tmms24_not_started = $stats->total_users - ($stats->tmms24_completed + $stats->tmms24_in_progress);
    $stats->student_path_not_started = $stats->total_users - ($stats->student_path_completed + $stats->student_path_in_progress);
    
    $stats->chaside_percentage = $stats->total_users > 0 ? ($stats->chaside_completed / $stats->total_users) * 100 : 0;
    $stats->learning_style_percentage = $stats->total_users > 0 ? ($stats->learning_style_completed / $stats->total_users) * 100 : 0;
    $stats->personality_percentage = $stats->total_users > 0 ? ($stats->personality_completed / $stats->total_users) * 100 : 0;
    $stats->tmms24_percentage = $stats->total_users > 0 ? ($stats->tmms24_completed / $stats->total_users) * 100 : 0;
    $stats->student_path_percentage = $stats->total_users > 0 ? ($stats->student_path_completed / $stats->total_users) * 100 : 0;
    
    // Aggregates over all tests
    $stats->total_completed = $stats->chaside_completed + $stats->learning_style_completed + 
                              $stats->personality_completed + $stats->tmms24_completed + 
                              $stats->student_path_completed;
                              
    $stats->total_in_progress = $stats->chaside_in_progress + $stats->learning_style_in_progress + 
                                $stats->personality_in_progress + $stats->tmms24_in_progress + 
                                $stats->student_path_in_progress;
                                
    $total_possible = $stats->total_users * 5;
    $stats->completion_percentage = $total_possible > 0 ? ($stats->total_completed / $total_possible) * 100 : 0;
    
    $stats->participation_rate = $stats->total_users > 0 ? ($stats->active_users / $stats->total_users) * 100 : 0;
    $stats->complete_profiles_percentage = $stats->total_users > 0 ? ($stats->complete_profiles / $stats->total_users) * 100 : 0;

    return $stats;
}

/**
 * Renderiza la vista de "No iniciado"
 */
function render_not_started_view($test_type) {
    global $PAGE;
    $renderer = $PAGE->get_renderer('block_student_path');
    return $renderer->render_not_started_view($test_type);
}

/**
 * Renderiza la vista de "En Progreso"
 */
function render_in_progress_view($answered, $total, $color_class) {
    global $PAGE;
    $renderer = $PAGE->get_renderer('block_student_path');
    return $renderer->render_in_progress_view($answered, $total, $color_class);
}

/**
 * Genera un resumen legible del estilo de aprendizaje usando la misma visualización del bloque
 */
function get_learning_style_summary($learning_style_data) {
    global $PAGE;

    if (!$learning_style_data) {
        return render_not_started_view('learning_style');
    }
    
    $data = json_decode($learning_style_data, true);
    if (!$data) {
        return render_not_started_view('learning_style');
    }

    // Check completion
    if (empty($data['is_completed'])) {
        // Calculate progress
        $answered = 0;
        $total_questions = 44;
        for ($i = 1; $i <= $total_questions; $i++) {
            if (isset($data['q'.$i]) && $data['q'.$i] !== null) {
                $answered++;
            }
        }
        return render_in_progress_view($answered, $total_questions, 'learning-style-progress');
    }
    
    // Colors from admin view overlay
    $color_left = '#0054ce';
    $color_right = '#66a3ff';
    
    // Definir las dimensiones del estilo de aprendizaje
    $dimensions_data = array(
        array(
            'name' => get_string('ls_processing', 'block_student_path'),
            'active' => isset($data['ap_active']) ? intval($data['ap_active']) : 0,
            'reflexive' => isset($data['ap_reflexivo']) ? intval($data['ap_reflexivo']) : 0,
            'active_label' => get_string('ls_active', 'block_student_path'),
            'reflexive_label' => get_string('ls_reflective', 'block_student_path')
        ),
        array(
            'name' => get_string('ls_perception', 'block_student_path'),
            'active' => isset($data['ap_sensorial']) ? intval($data['ap_sensorial']) : 0,
            'reflexive' => isset($data['ap_intuitivo']) ? intval($data['ap_intuitivo']) : 0,
            'active_label' => get_string('ls_sensing', 'block_student_path'),
            'reflexive_label' => get_string('ls_intuitive', 'block_student_path')
        ),
        array(
            'name' => get_string('ls_input', 'block_student_path'),
            'active' => isset($data['ap_visual']) ? intval($data['ap_visual']) : 0,
            'reflexive' => isset($data['ap_verbal']) ? intval($data['ap_verbal']) : 0,
            'active_label' => get_string('ls_visual', 'block_student_path'),
            'reflexive_label' => get_string('ls_verbal', 'block_student_path')
        ),
        array(
            'name' => get_string('ls_comprehension', 'block_student_path'),
            'active' => isset($data['ap_secuencial']) ? intval($data['ap_secuencial']) : 0,
            'reflexive' => isset($data['ap_global']) ? intval($data['ap_global']) : 0,
            'active_label' => get_string('ls_sequential', 'block_student_path'),
            'reflexive_label' => get_string('ls_global', 'block_student_path')
        )
    );
    
    // Calculate Predominant Styles
    $predominant_styles = [];
    foreach ($dimensions_data as $dim) {
        if ($dim['active'] > $dim['reflexive']) {
            $predominant_styles[] = $dim['active_label'];
        } elseif ($dim['reflexive'] > $dim['active']) {
            $predominant_styles[] = $dim['reflexive_label'];
        } else {
            $predominant_styles[] = $dim['active_label'] . '/' . $dim['reflexive_label']; // Balanced
        }
    }
    
    $dimensions_output = [];
    foreach ($dimensions_data as $dimension) {
        $total = $dimension['active'] + $dimension['reflexive'];
        $active_percentage = $total > 0 ? round(($dimension['active'] / $total) * 100, 1) : 0;
        $reflexive_percentage = $total > 0 ? round(($dimension['reflexive'] / $total) * 100, 1) : 0;
        
        $dimensions_output[] = [
            'name' => $dimension['name'],
            'active_label' => $dimension['active_label'],
            'active' => $dimension['active'],
            'active_percentage' => $active_percentage,
            'reflexive_label' => $dimension['reflexive_label'],
            'reflexive' => $dimension['reflexive'],
            'reflexive_percentage' => $reflexive_percentage,
            'color_left' => $color_left,
            'color_right' => $color_right
        ];
    }
    
    // Profile Descriptions
    $profile_descriptions = [];
    $profile_keys = [
        0 => ['active' => 'active_profile_prof', 'reflexive' => 'reflexive_profile_prof'],
        1 => ['active' => 'sensorial_profile_prof', 'reflexive' => 'intuitive_profile_prof'],
        2 => ['active' => 'visual_profile_prof', 'reflexive' => 'verbal_profile_prof'],
        3 => ['active' => 'sequential_profile_prof', 'reflexive' => 'global_profile_prof']
    ];

    foreach ($dimensions_data as $index => $dim) {
        $desc_key = '';
        if ($dim['active'] > $dim['reflexive']) {
            $desc_key = $profile_keys[$index]['active'];
        } elseif ($dim['reflexive'] > $dim['active']) {
            $desc_key = $profile_keys[$index]['reflexive'];
        }
        
        if ($desc_key) {
            $profile_descriptions[] = get_string($desc_key, 'block_student_path');
        }
    }
    
    $template_data = [
        'predominant_title' => get_string('predominant_styles', 'block_student_path'),
        'predominant_styles' => $predominant_styles,
        'dimensions' => $dimensions_output,
        'translates_to_title' => get_string('which_translates_to', 'block_student_path'),
        'profile_descriptions' => $profile_descriptions
    ];
    
    $renderer = $PAGE->get_renderer('block_student_path');
    return $renderer->render_learning_style_summary($template_data);
}

/**
 * Genera un resumen legible del perfil de personalidad usando la misma visualización del bloque
 */
function get_personality_summary($personality_data) {
    if (!$personality_data) {
        return render_not_started_view('personality');
    }
    
    global $PAGE;
    $renderer = $PAGE->get_renderer('block_student_path');

    $data = json_decode($personality_data, true);
    if (!$data) {
        return $renderer->render_not_started_view('personality');
    }

    // Check completion
    if (empty($data['is_completed'])) {
        // Calculate progress
        $answered = 0;
        $total_questions = 72;
        for ($i = 1; $i <= $total_questions; $i++) {
            if (isset($data['q'.$i]) && $data['q'.$i] !== null) {
                $answered++;
            }
        }
        return $renderer->render_in_progress_view($answered, $total_questions, 'personality-progress');
    }
    
    // Official Colors
    $color_primary = '#00bf91';
    
    // Dimensiones de personalidad
    $extraversion = isset($data['extraversion']) ? intval($data['extraversion']) : 0;
    $introversion = isset($data['introversion']) ? intval($data['introversion']) : 0;
    
    $sensing = isset($data['sensing']) ? intval($data['sensing']) : 0;
    $intuition = isset($data['intuition']) ? intval($data['intuition']) : 0;
    
    $thinking = isset($data['thinking']) ? intval($data['thinking']) : 0;
    $feeling = isset($data['feeling']) ? intval($data['feeling']) : 0;
    
    $judging = isset($data['judging']) ? intval($data['judging']) : 0;
    $perceptive = isset($data['perceptive']) ? intval($data['perceptive']) : 0;
    
    // Calcular tipo MBTI
    $mbti_type = '';
    $mbti_type .= $extraversion >= $introversion ? 'E' : 'I';
    $mbti_type .= $sensing >= $intuition ? 'S' : 'N';
    $mbti_type .= $thinking >= $feeling ? 'T' : 'F';
    $mbti_type .= $judging >= $perceptive ? 'J' : 'P';
    
    // Usar cadenas de idioma para las descripciones MBTI
    $mbti_key = 'mbti_' . strtolower($mbti_type);
    $mbti_dimensions_key = 'mbti_dimensions_' . strtolower($mbti_type);
    
    // Dimensions Data
    $raw_dimensions = [
        [
            'left_label' => get_string('extraversion', 'block_student_path'), 'left_val' => $extraversion,
            'right_label' => get_string('introversion', 'block_student_path'), 'right_val' => $introversion,
            'left_code' => 'E', 'right_code' => 'I'
        ],
        [
            'left_label' => get_string('ls_sensing', 'block_student_path'), 'left_val' => $sensing,
            'right_label' => get_string('intuition', 'block_student_path'), 'right_val' => $intuition,
            'left_code' => 'S', 'right_code' => 'N'
        ],
        [
            'left_label' => get_string('thinking', 'block_student_path'), 'left_val' => $thinking,
            'right_label' => get_string('feeling', 'block_student_path'), 'right_val' => $feeling,
            'left_code' => 'T', 'right_code' => 'F'
        ],
        [
            'left_label' => get_string('judging', 'block_student_path'), 'left_val' => $judging,
            'right_label' => get_string('ls_perception', 'block_student_path'), 'right_val' => $perceptive,
            'left_code' => 'J', 'right_code' => 'P'
        ]
    ];

    $processed_dimensions = [];
    foreach ($raw_dimensions as $dim) {
        $total = $dim['left_val'] + $dim['right_val'];
        $left_pct = $total > 0 ? round(($dim['left_val'] / $total) * 100, 1) : 50;
        $right_pct = $total > 0 ? round(($dim['right_val'] / $total) * 100, 1) : 50;
        
        $processed_dimensions[] = [
            'left_label' => $dim['left_label'],
            'left_val' => $dim['left_val'],
            'left_pct' => $left_pct,
            'left_code' => $dim['left_code'],
            'right_label' => $dim['right_label'],
            'right_val' => $dim['right_val'],
            'right_pct' => $right_pct,
            'right_code' => $dim['right_code'],
            'color_primary' => $color_primary
        ];
    }

    $template_data = [
        'mbti_type' => $mbti_type,
        'mbti_title' => get_string($mbti_dimensions_key, 'block_student_path'),
        'mbti_description' => get_string($mbti_key, 'block_student_path'),
        'dimensions' => $processed_dimensions
    ];
    
    return $renderer->render_personality_summary($template_data);
}


/**
 * Calcula los puntajes del TMMS-24 desde las respuestas individuales
 */
function calculate_tmms24_scores($responses) {
    $perception = array_sum(array_slice($responses, 0, 8));
    $comprehension = array_sum(array_slice($responses, 8, 8));
    $regulation = array_sum(array_slice($responses, 16, 8));
    
    return [
        'perception' => $perception,
        'comprehension' => $comprehension,
        'regulation' => $regulation
    ];
}

/**
 * Obtiene las claves de interpretación (corta y larga) para TMMS-24
 */
function get_tmms24_interpretation_keys($dimension, $score, $gender) {
    $base_key = '';
    
    if ($dimension === 'perception') {
        if ($gender === 'M') {
            if ($score < 22) $base_key = 'perception_difficulty_feeling';
            elseif ($score <= 32) $base_key = 'perception_adequate_feeling';
            else $base_key = 'perception_excessive_attention';
        } else {
            if ($score < 25) $base_key = 'perception_difficulty_feeling';
            elseif ($score <= 35) $base_key = 'perception_adequate_feeling';
            else $base_key = 'perception_excessive_attention';
        }
    } elseif ($dimension === 'comprehension') {
        if ($gender === 'M') {
            if ($score < 26) $base_key = 'comprehension_difficulty_understanding';
            elseif ($score <= 35) $base_key = 'comprehension_adequate_with_difficulties';
            else $base_key = 'comprehension_great_clarity';
        } else {
            if ($score < 24) $base_key = 'comprehension_difficulty_understanding';
            elseif ($score <= 34) $base_key = 'comprehension_adequate_with_difficulties';
            else $base_key = 'comprehension_great_clarity';
        }
    } elseif ($dimension === 'regulation') {
        if ($gender === 'M') {
            if ($score < 24) $base_key = 'regulation_difficulty_managing';
            elseif ($score <= 35) $base_key = 'regulation_adequate_balance';
            else $base_key = 'regulation_great_capacity';
        } else {
            if ($score < 24) $base_key = 'regulation_difficulty_managing';
            elseif ($score <= 34) $base_key = 'regulation_adequate_balance';
            else $base_key = 'regulation_great_capacity';
        }
    }
    
    return [
        'short' => $base_key,
        'long' => $base_key . '_long'
    ];
}

/**
 * Obtiene el string de meta formateado para TMMS-24
 */
function get_tmms24_goal_string($dimension, $gender) {
    $a = new stdClass();
    
    if ($dimension === 'perception') {
        if ($gender === 'M') {
            $a->range = '22 ' . get_string('goal_to', 'block_student_path') . ' 32';
            $a->optimal = '27';
        } else {
            $a->range = '25 ' . get_string('goal_to', 'block_student_path') . ' 35';
            $a->optimal = '30';
        }
        return get_string('goal_perception', 'block_student_path', $a);
    } else {
        if ($gender === 'M') {
            $a->range = '36 ' . get_string('goal_to', 'block_student_path') . ' 40';
        } else {
            $a->range = '35 ' . get_string('goal_to', 'block_student_path') . ' 40';
        }
        return get_string('goal_linear', 'block_student_path', $a);
    }     
}

function interpret_tmms24_score($dimension, $score, $gender) {
    $keys = get_tmms24_interpretation_keys($dimension, $score, $gender);
    return get_string($keys['short'], 'block_student_path');
}

/**
 * Calcula un puntaje normalizado (0-100) para comparación justa entre dimensiones.
 * Basado en la lógica del bloque TMMS-24.
 */
function calculate_tmms24_normalized_score($dimension, $score, $gender) {
    $score = (int)$score;
    
    if ($dimension === 'perception') {
        // Lógica de Percepción (Parabólica)
        if ($gender === 'M') {
            $optimal = 27;
            $adequate_min = 22;
            $adequate_max = 32;
        } else {
            $optimal = 30;
            $adequate_min = 25;
            $adequate_max = 35;
        }
        
        // Check if in Adequate Range (Tier 1 - Best State for Perception)
        if ($score >= $adequate_min && $score <= $adequate_max) {
            // Map to [80, 100] based on distance to optimal
            if ($score <= $optimal) {
                $side_range = $optimal - $adequate_min;
                $dist = $optimal - $score;
            } else {
                $side_range = $adequate_max - $optimal;
                $dist = $score - $optimal;
            }
            
            if ($side_range > 0) {
                return 100 - ($dist / $side_range) * 20;
            } else {
                return 100;
            }
        } else {
            // Outside Adequate Range (Tier 3 - Bad) -> [0, 59]
            if ($score < $adequate_min) {
                // Lower side: 0 to adequate_min-1
                if ($adequate_min > 0) {
                    return ($score / $adequate_min) * 59;
                }
                return 0;
            } else {
                // Upper side (Excessive): adequate_max+1 to 40
                $range = 40 - $adequate_max;
                $dist_from_edge = $score - $adequate_max;
                if ($range > 0) {
                    return 59 - (($dist_from_edge / $range) * 30);
                }
                return 59;
            }
        }
    } else {
        // Comprensión y Regulación (Lineal)
        if ($dimension === 'comprehension') {
            if ($gender === 'M') {
                $great_min = 36;
                $adequate_min = 26;
                $adequate_max = 35;
            } else {
                $great_min = 35;
                $adequate_min = 24;
                $adequate_max = 34;
            }
        } else { // regulacion
            if ($gender === 'M') {
                $great_min = 36;
                $adequate_min = 24;
                $adequate_max = 35;
            } else {
                $great_min = 35;
                $adequate_min = 24;
                $adequate_max = 34;
            }
        }
        
        if ($score >= $great_min) {
            // Tier 1 (Great): [80, 100]
            $range = 40 - $great_min;
            $dist = $score - $great_min;
            if ($range > 0) {
                return 80 + ($dist / $range) * 20;
            }
            return 100;
        } elseif ($score >= $adequate_min) {
            // Tier 2 (Adequate): [60, 79]
            $range = $adequate_max - $adequate_min;
            $dist = $score - $adequate_min;
            if ($range > 0) {
                return 60 + ($dist / $range) * 19;
            }
            return 79;
        } else {
            // Tier 3 (Bad): [0, 59]
            if ($adequate_min > 0) {
                return ($score / $adequate_min) * 59;
            }
            return 0;
        }
    }
}

/**
 * Genera un resumen legible completo del TMMS-24
 */
function get_tmms24_summary($tmms_24_data, $user = null, $courseid = null) {
    global $USER, $PAGE;
    $renderer = $PAGE->get_renderer('block_student_path');

    // Security Check: BOLA Prevention
    if ($user && $USER->id != $user->id) {
        $context = ($courseid && $courseid != SITEID) ? context_course::instance($courseid) : context_system::instance();
        if (!has_capability('block/student_path:viewstudentdata', $context)) {
             return $renderer->render_alert(get_string('nopermissions', 'error'));
        }
    }

    if (!$tmms_24_data) {
        return $renderer->render_not_started_view('tmms24');
    }
    
    $data = json_decode($tmms_24_data, true);
    if (!$data) {
        return $renderer->render_not_started_view('tmms24');
    }

    // Check completion
    if (empty($data['is_completed'])) {
        // Calculate progress
        $answered = 0;
        $total_questions = 24;
        for ($i = 1; $i <= $total_questions; $i++) {
            if (isset($data['item'.$i]) && $data['item'.$i] !== null) {
                $answered++;
            }
        }
        return $renderer->render_in_progress_view($answered, $total_questions, 'tmms24-progress');
    }
    
    // Calcular puntajes desde las respuestas individuales
    $responses = [];
    for ($i = 1; $i <= 24; $i++) {
        $responses[] = $data['item' . $i] ?? 0;
    }
    $scores = calculate_tmms24_scores($responses);
    
    $gender = $data['gender'] ?? 'F';
    
    // Calcular puntajes normalizados para determinar estrellas
    $normalized_scores = [];
    foreach ($scores as $dim => $score) {
        $normalized_scores[$dim] = calculate_tmms24_normalized_score($dim, $score, $gender);
    }
    
    // Determinar dimensiones estrella (Max score y >= 60)
    $max_n_score = max($normalized_scores);
    $star_dimensions = [];
    if ($max_n_score >= 60) {
        foreach ($normalized_scores as $dim => $n_score) {
            if (abs($n_score - $max_n_score) < 0.1) {
                $star_dimensions[] = $dim;
            }
        }
    }
    
    // Get Age and Gender Display
    $user_info = null;
    if ($user) {
        // Gender
        if ($gender == 'M') {
            $gender_text = get_string('gender_male', 'block_student_path');
            $gender_icon = 'fa-mars';
            $gender_color = '#42a5f5'; // Blue
        } elseif ($gender == 'F') {
            $gender_text = get_string('gender_female', 'block_student_path');
            $gender_icon = 'fa-venus';
            $gender_color = '#ec407a'; // Pink
        } else {
            $gender_text = get_string('other_gender', 'block_student_path');
            $gender_icon = 'fa-genderless';
            $gender_color = '#ab47bc'; // Purple
        }
        
        // Age Calculation
        $age_text = '';
        if (isset($data['age'])) {
             $age_text = (int)$data['age'] . ' ' . get_string('years', 'block_student_path');
        } elseif (!empty($user->birthday)) {
             $age = date_diff(date_create("@$user->birthday"), date_create('now'))->y;
             $age_text = $age . ' ' . get_string('years', 'block_student_path');
        } else {
             $age_text = 'N/A';
        }

        $user_info = [
            'gender_color' => $gender_color,
            'gender_icon' => $gender_icon,
            'str_gender' => get_string('gender', 'block_student_path'),
            'gender_text' => $gender_text,
            'str_age' => get_string('age', 'block_student_path'),
            'age_text' => $age_text
        ];
    }
    
    // Official Colors and Icons
    $dimensions_config = [
        'perception' => [
            'icon' => 'fa-eye', 
            'color' => '#ff6600', 
            'bg_light' => '#fff0e6',
            'meaning' => get_string('tmms24_perception_meaning', 'block_student_path'),
        ],
        'comprehension' => [
            'icon' => 'fa-lightbulb-o', 
            'color' => '#ff8533', 
            'bg_light' => '#fff5eb',
            'meaning' => get_string('tmms24_comprehension_meaning', 'block_student_path'),
        ],
        'regulation' => [
            'icon' => 'fa-balance-scale', 
            'color' => '#ffaa66', 
            'bg_light' => '#fff9f2',
            'meaning' => get_string('tmms24_regulation_meaning', 'block_student_path'),
        ]
    ];

    // Colors for gradients
    $c_low = '#ff8e8e'; // Soft Red
    $c_mid = '#4cd137'; // Bright Green
    $c_high = '#48dbfb'; // Bright Blue
    $c_excess = '#ff6b6b'; // Red for excessive

    $processed_dimensions = [];
    foreach ($dimensions_config as $key => $props) {
        $score = $scores[$key];
        
        // Get Interpretations and Goals
        $interpretation_keys = get_tmms24_interpretation_keys($key, $score, $gender);
        $short_interpretation = get_string($interpretation_keys['short'], 'block_student_path');
        $long_interpretation = get_string($interpretation_keys['long'], 'block_student_path');
        $goal_string = get_tmms24_goal_string($key, $gender);
        
        $is_star = in_array($key, $star_dimensions);
        
        // Determine range position for visual bar
        $range_info = get_tmms24_range_info($key, $score, $gender);
        $range_label = $range_info['range_label'];
        
        // Calculate Gradient and Width
        $progress_width = ($score / 40) * 100;
        // Calculate background size to ensure gradient maps to 0-40 scale regardless of width
        $bg_size = ($progress_width > 0) ? (100 / $progress_width * 100) : 100;
        $bar_gradient_css = '';

        if ($key === 'perception') {
            // Perception: Low (Red) -> Adequate (Green) -> Excessive (Red)
            if ($gender === 'M') { 
                $p_start_green = (22/40)*100; 
                $p_optimal = (27/40)*100; 
                $p_end_green = (32/40)*100; 
            } else { 
                $p_start_green = (25/40)*100; 
                $p_optimal = (30/40)*100; 
                $p_end_green = (35/40)*100; 
            }
            
            $bar_gradient_css = "background-image: linear-gradient(to right, 
                $c_low 0%, 
                $c_low " . ($p_start_green - 10) . "%, 
                $c_mid " . $p_optimal . "%, 
                $c_excess " . ($p_end_green + 10) . "%, 
                $c_excess 100%) !important; background-size: {$bg_size}% 100% !important;";
                
        } else {
            // Linear: Low (Red) -> Adequate (Green) -> Excellent (Blue)
            if ($key === 'comprehension') {
                if ($gender === 'M') { $t1 = 26; $t2 = 35; }
                else { $t1 = 24; $t2 = 34; }
            } else { // regulation
                if ($gender === 'M') { $t1 = 23; $t2 = 35; }
                else { $t1 = 23; $t2 = 34; }
            }
            
            $p_t1 = ($t1/40)*100;
            $p_t2 = ($t2/40)*100;
            
            $bar_gradient_css = "background-image: linear-gradient(to right, 
                $c_low 0%, 
                $c_low " . ($p_t1 - 5) . "%, 
                $c_mid " . ($p_t1 + 5) . "%, 
                $c_mid " . $p_t2 . "%, 
                $c_high 100%) !important; background-size: {$bg_size}% 100% !important;";
        }

        $processed_dimensions[] = [
            'color' => $props['color'],
            'bg_light' => $props['bg_light'],
            'icon' => $props['icon'],
            'name' => get_string($key, 'block_student_path'),
            'meaning' => $props['meaning'],
            'score' => $score,
            'short_interpretation' => $short_interpretation,
            'long_interpretation' => $long_interpretation,
            'goal_string' => $goal_string,
            'is_star' => $is_star,
            'str_star_dimension' => get_string('star_dimension', 'block_student_path'),
            'range_label' => $range_label,
            'str_range' => get_string('range', 'block_student_path'),
            'progress_width' => $progress_width,
            'bar_gradient_css' => $bar_gradient_css
        ];
    }
    
    return $renderer->render_tmms24_summary([
        'user_info' => $user_info,
        'dimensions' => $processed_dimensions
    ]);
}

/**
 * Helper para determinar la posición visual en el rango
 */
function get_tmms24_range_info($dimension, $score, $gender) {
    $info = ['position_pct' => 50, 'range_label' => ''];
    
    // Logic based on TMMS-24 cutoffs
    if ($dimension == 'perception') {
        if ($gender == 'M') {
            if ($score <= 21) {
                $info['range_label'] = get_string('perception_comprehension_regulation_range_low', 'block_student_path');
            } elseif ($score <= 32) {
                $info['range_label'] = get_string('perception_comprehension_regulation_range_adequate', 'block_student_path');
            } else {
                $info['range_label'] = get_string('perception_range_excessive', 'block_student_path');
            }
        } else {
            if ($score <= 24) {
                $info['range_label'] = get_string('perception_comprehension_regulation_range_low', 'block_student_path');
            } elseif ($score <= 35) {
                $info['range_label'] = get_string('perception_comprehension_regulation_range_adequate', 'block_student_path');
            } else {
                $info['range_label'] = get_string('perception_range_excessive', 'block_student_path');
            }
        }
    } else {
        if ($gender == 'M') {
            if ($dimension == 'comprehension') {
                if ($score <= 25) {
                    $info['range_label'] = get_string('perception_comprehension_regulation_range_low', 'block_student_path');
                } elseif ($score <= 35) {
                    $info['range_label'] = get_string('perception_comprehension_regulation_range_adequate', 'block_student_path');
                } else {
                    $info['range_label'] = get_string('perception_comprehension_regulation_range_excellent', 'block_student_path');
                }
            } else { // regulation
                if ($score <= 23) {
                    $info['range_label'] = get_string('perception_comprehension_regulation_range_low', 'block_student_path');
                } elseif ($score <= 35) {
                    $info['range_label'] = get_string('perception_comprehension_regulation_range_adequate', 'block_student_path');
                } else {
                    $info['range_label'] = get_string('perception_comprehension_regulation_range_excellent', 'block_student_path');
                }
            }
        } else { // female and others
            if ($score <= 23) {
                $info['range_label'] = get_string('perception_comprehension_regulation_range_low', 'block_student_path');
            } elseif ($score <= 34) {
                $info['range_label'] = get_string('perception_comprehension_regulation_range_adequate', 'block_student_path');
            } else {
                $info['range_label'] = get_string('perception_comprehension_regulation_range_excellent', 'block_student_path');
            }
        }
    }
    
    return $info;
}

/**
 * Obtiene todos los usuarios de un curso con su progreso en los tests
 */
function get_course_users_with_test_progress($courseid) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/lib/enrollib.php');
    
    try {
        // Obtener usuarios del curso (estudiantes)
        $context = context_course::instance($courseid);

        // Security Check
        if (!has_capability('block/student_path:viewstudentdata', $context)) {
             throw new moodle_exception('nopermissions', 'error', '', null, 'view course users');
        }

        list($esql, $params) = get_enrolled_sql($context, 'block/student_path:makemap', 0, true);
        
        // Optimized Query: Fetch all data in one go using LEFT JOINs
        $sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.lastaccess,
                       c.is_completed AS chaside_completed, c.timemodified AS chaside_date,
                       ls.is_completed AS ls_completed, ls.updated_at AS ls_date,
                       pt.is_completed AS pt_completed, pt.updated_at AS pt_date,
                       tm.is_completed AS tm_completed, tm.updated_at AS tm_date,
                       sp.is_completed AS sp_completed, sp.updated_at AS sp_date,
                       sp.name, sp.program, sp.admission_year, sp.admission_semester, sp.code,
                       sp.personality_strengths, sp.personality_weaknesses, 
                       sp.vocational_areas, sp.vocational_areas_secondary, sp.vocational_description,
                       sp.emotional_skills_level,
                       sp.goal_short_term, sp.goal_medium_term, sp.goal_long_term,
                       sp.action_short_term, sp.action_medium_term, sp.action_long_term
                FROM {user} u
                JOIN ($esql) eu ON u.id = eu.id
                LEFT JOIN {block_chaside_responses} c ON u.id = c.userid
                LEFT JOIN {learning_style} ls ON u.id = ls.user
                LEFT JOIN {personality_test} pt ON u.id = pt.user
                LEFT JOIN {tmms_24} tm ON u.id = tm.user
                LEFT JOIN {block_student_path} sp ON u.id = sp.user
                ORDER BY u.lastname, u.firstname";

        $users = $DB->get_records_sql($sql, $params);
        
        $result_users = array();
        
        // Fields to check for student path progress (same as get_student_path_progress)
        $sp_fields_to_check = [
            'name', 'program', 'admission_year', 'admission_semester', 'email', 'code',
            'personality_strengths', 'personality_weaknesses', 
            'vocational_areas', 'vocational_areas_secondary', 'vocational_description',
            'emotional_skills_level',
            'goal_short_term', 'goal_medium_term', 'goal_long_term',
            'action_short_term', 'action_medium_term', 'action_long_term'
        ];
        $sp_total_fields = count($sp_fields_to_check);

        // Procesar cada usuario para calcular estadísticas
        foreach ($users as $user) {
            try {
                // CHASIDE status
                $user->chaside_status_raw = isset($user->chaside_completed) ? $user->chaside_completed : -1;
                $user->chaside_status = get_test_status($user->chaside_status_raw);
                $user->chaside_date = isset($user->chaside_date) ? (int)$user->chaside_date : 0;
                
                // Learning Style status
                $user->learning_style_status_raw = isset($user->ls_completed) ? $user->ls_completed : -1;
                $user->learning_style_status = get_test_status($user->learning_style_status_raw);
                $user->learning_style_date = isset($user->ls_date) ? (int)$user->ls_date : 0;
                
                // Personality status
                $user->personality_status_raw = isset($user->pt_completed) ? $user->pt_completed : -1;
                $user->personality_status = get_test_status($user->personality_status_raw);
                $user->personality_date = isset($user->pt_date) ? (int)$user->pt_date : 0;
                
                // TMMS-24 status
                $user->tmms24_status_raw = isset($user->tm_completed) ? $user->tm_completed : -1;
                $user->tmms24_status = get_test_status($user->tmms24_status_raw);
                $user->tmms24_date = isset($user->tm_date) ? (int)$user->tm_date : 0;
                
                // Student Path status (Calculated in-memory)
                $sp_filled_count = 0;
                // Check if record exists (if any field is not null, record exists because of LEFT JOIN)
                // But sp_completed is a good indicator if record exists or not. If null, no record.
                $sp_exists = !is_null($user->sp_completed); 
                
                if ($sp_exists) {
                    foreach ($sp_fields_to_check as $field) {
                        if (!empty($user->$field)) {
                            $sp_filled_count++;
                        }
                    }
                }

                if (!$sp_exists) {
                    $sp_status = 'not-started';
                } elseif ($user->sp_completed == 1) {
                    $sp_status = 'completed';
                } elseif ($sp_filled_count >= $sp_total_fields) {
                    $sp_status = 'completed';
                } else {
                    $sp_status = 'in-progress';
                }

                $user->student_path_status = $sp_status;
                $user->student_path_status_raw = ($sp_status == 'completed') ? 1 : (($sp_status == 'in-progress') ? 0 : -1);
                $user->student_path_date = isset($user->sp_date) ? (int)$user->sp_date : 0;
                
                // Contar completados y en progreso
                $user->total_completed = 0;
                $user->total_in_progress = 0;
                
                if ($user->chaside_status == 'completed') $user->total_completed++;
                else if ($user->chaside_status == 'in-progress') $user->total_in_progress++;
                
                if ($user->learning_style_status == 'completed') $user->total_completed++;
                else if ($user->learning_style_status == 'in-progress') $user->total_in_progress++;
                
                if ($user->personality_status == 'completed') $user->total_completed++;
                else if ($user->personality_status == 'in-progress') $user->total_in_progress++;
                
                if ($user->tmms24_status == 'completed') $user->total_completed++;
                else if ($user->tmms24_status == 'in-progress') $user->total_in_progress++;
                
                if ($user->student_path_status == 'completed') $user->total_completed++;
                else if ($user->student_path_status == 'in-progress') $user->total_in_progress++;
                
                // Calcular porcentaje de finalización
                $user->completion_percentage = ($user->total_completed / 5) * 100;
                
                // Última actividad (Calculated in-memory)
                $latest = 0;
                if ($user->chaside_date > $latest) $latest = $user->chaside_date;
                if ($user->learning_style_date > $latest) $latest = $user->learning_style_date;
                if ($user->personality_date > $latest) $latest = $user->personality_date;
                if ($user->tmms24_date > $latest) $latest = $user->tmms24_date;
                if ($user->student_path_date > $latest) $latest = $user->student_path_date;
                
                $user->last_activity = $latest;
                
                $result_users[] = $user;
            } catch (Exception $e) {
                // Si hay error con un usuario específico, continuar con el siguiente
                error_log('Error processing user ' . $user->id . ': ' . $e->getMessage());
                continue;
            }
        }
        
        return $result_users;
    } catch (Exception $e) {
        error_log('Error in get_course_users_with_test_progress: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Helper: Determina el estado de un test basado en is_completed
 */
function get_test_status($is_completed) {
    if ($is_completed == 1) {
        return 'completed';
    } else if ($is_completed == 0) {
        return 'in-progress';
    } else {
        return 'not-started';
    }
}

/**
 * Helper: Obtiene la última actividad de un usuario en los tests
 */
function get_latest_test_activity($userid, $courseid = null) {
    global $DB, $USER;
    
    // Security Check
    if ($userid != $USER->id) {
        if ($courseid) {
            $context = context_course::instance($courseid);
            if (!has_capability('block/student_path:viewstudentdata', $context)) {
                 return 0;
            }
        } else {
            // Fallback to system context if no course provided
            $context = context_system::instance();
            if (!has_capability('block/student_path:viewstudentdata', $context)) {
                 return 0;
            }
        }
    }

    $latest = 0;
    
    try {
        // Verificar cada tabla de tests - usar try-catch para cada uno por si no existe el campo
        try {
            $timemodified = $DB->get_field('block_chaside_responses', 'timemodified', array('userid' => $userid));
            if ($timemodified && $timemodified > $latest) {
                $latest = $timemodified;
            }
        } catch (Exception $e) {
            // Campo timemodified no existe en chaside o tabla no existe
        }
        
        try {
            $updated_at = $DB->get_field('learning_style', 'updated_at', array('user' => $userid));
            if ($updated_at && $updated_at > $latest) {
                $latest = $updated_at;
            }
        } catch (Exception $e) {
            // Campo updated_at no existe en learning_style
        }
        
        try {
            $updated_at = $DB->get_field('personality_test', 'updated_at', array('user' => $userid));
            if ($updated_at && $updated_at > $latest) {
                $latest = $updated_at;
            }
        } catch (Exception $e) {
            // Campo updated_at no existe en personality_test
        }
        
        try {
            $updated_at = $DB->get_field('tmms_24', 'updated_at', array('user' => $userid));
            if ($updated_at && $updated_at > $latest) {
                $latest = $updated_at;
            }
        } catch (Exception $e) {
            // Campo updated_at no existe en tmms_24
        }
        
        try {
            $updated_at = $DB->get_field('block_student_path', 'updated_at', array('user' => $userid));
            if ($updated_at && $updated_at > $latest) {
                $latest = $updated_at;
            }
        } catch (Exception $e) {
            // Campo updated_at no existe en student_path
        }
    } catch (Exception $e) {
        error_log('Error in get_latest_test_activity for user ' . $userid . ': ' . $e->getMessage());
    }
    
    return $latest;
}


/**
 * Helper to calculate semester from timestamp
 * @param int $timestamp
 * @return string "Semestre YYYY-P"
 */
function block_student_path_get_semester($timestamp) {
    $year = date('Y', $timestamp);
    $month = date('n', $timestamp);
    $period = ($month <= 6) ? 1 : 2;
    return "$year-$period";
}

function block_student_path_format_period($period) {
    return get_string('semester', 'block_student_path') . ' ' . $period;
}

/**
 * Calculates the progress of the Student Path for a given user.
 * 
 * @param int|stdClass $user_or_id User object or ID
 * @param int|null $courseid Course ID for context check
 * @return array ['status' => 'not-started'|'in-progress'|'completed', 'filled' => int, 'total' => int]
 */
function get_student_path_progress($user_or_id, $courseid = null) {
    global $DB, $USER;
    
    $userid = is_object($user_or_id) ? $user_or_id->id : $user_or_id;

    // Security Check
    if ($userid != $USER->id) {
        $context = ($courseid && $courseid != SITEID) ? context_course::instance($courseid) : context_system::instance();
        if (!has_capability('block/student_path:viewstudentdata', $context)) {
             throw new moodle_exception('nopermissions', 'error', '', null, 'view student progress');
        }
    }
    
    $entry = $DB->get_record('block_student_path', array('user' => $userid));
    
    $fields_to_check = [
        'name', 'program', 'admission_year', 'admission_semester', 'email', 'code',
        'personality_strengths', 'personality_weaknesses', 
        'vocational_areas', 'vocational_areas_secondary', 'vocational_description',
        'emotional_skills_level',
        'goal_short_term', 'goal_medium_term', 'goal_long_term',
        'action_short_term', 'action_medium_term', 'action_long_term'
    ];
    
    $total_fields = count($fields_to_check);
    $filled_count = 0;
    
    if ($entry) {
        foreach ($fields_to_check as $field) {
            if (!empty($entry->$field)) {
                $filled_count++;
            }
        }
    }
    
    if (!$entry) {
        $status = 'not-started';
    } elseif (isset($entry->is_completed) && $entry->is_completed == 1) {
        $status = 'completed';
    } elseif ($filled_count >= $total_fields) {
        $status = 'completed';
    } else {
        $status = 'in-progress';
    }
    
    return [
        'status' => $status,
        'filled' => $filled_count,
        'total' => $total_fields
    ];
}

/**
 * Renders the Student Path Identity Map content.
 * Used in both view_profile.php and ajax_get_test_details.php to avoid duplication.
 *
 * @param stdClass|null $record The student path record (or history content).
 * @param bool $is_history_view Whether this is a history view.
 * @return string HTML content.
 */
function render_student_path_content($record, $is_history_view = false) {
    global $PAGE;
    $renderer = $PAGE->get_renderer('block_student_path');
    
    if (!$record) {
        return $renderer->render_student_path_content(['no_data' => true, 'str_no_path_data' => get_string('no_path_data', 'block_student_path')]);
    }

    // Helper for vocational area name
    $get_voc_name = function($letter) {
        if (empty($letter)) return '-';
        $key = 'area_' . strtolower($letter);
        if (get_string_manager()->string_exists($key, 'block_student_path')) {
            return get_string($key, 'block_student_path');
        }
        return $letter;
    };

    // Helper for program name
    $program_display = '-';
    if (isset($record->program) && !empty($record->program)) {
        // Sanitize program key before checking string manager to avoid potential issues
        $clean_program = clean_param($record->program, PARAM_ALPHANUMEXT);
        if (strpos($clean_program, 'prog_') === 0 && get_string_manager()->string_exists($clean_program, 'block_student_path')) {
            $program_display = get_string($clean_program, 'block_student_path');
        } else {
            $program_display = s($record->program);
        }
    }

    $general_fields = [
        ['label' => get_string('program', 'block_student_path'), 'value' => $program_display, 'icon' => 'fa-graduation-cap'],
        ['label' => get_string('code', 'block_student_path'), 'value' => (isset($record->code) ? s($record->code) : '-'), 'icon' => 'fa-barcode'],
        ['label' => get_string('admission_year', 'block_student_path'), 'value' => (isset($record->admission_year) ? s($record->admission_year) : '-'), 'icon' => 'fa-calendar'],
        ['label' => get_string('admission_semester', 'block_student_path'), 'value' => (isset($record->admission_semester) ? get_string('semester_' . $record->admission_semester, 'block_student_path') : '-'), 'icon' => 'fa-clock-o'],
        ['label' => get_string('email', 'block_student_path'), 'value' => (isset($record->email) ? s($record->email) : '-'), 'icon' => 'fa-envelope'],
    ];

    // Helper for vocational icons and colors
    $get_voc_style = function($letter) {
        $letter = strtoupper($letter);
        $styles = [
            'C' => ['icon' => 'fa-calculator', 'color' => '#5e35b1', 'bg' => '#ede7f6'], // Admin/Contable - Deep Purple
            'H' => ['icon' => 'fa-users', 'color' => '#fb8c00', 'bg' => '#fff3e0'], // Humanistic - Orange
            'A' => ['icon' => 'fa-paint-brush', 'color' => '#d81b60', 'bg' => '#fce4ec'], // Arts - Pink
            'S' => ['icon' => 'fa-user-md', 'color' => '#00897b', 'bg' => '#e0f2f1'], // Health - Teal
            'I' => ['icon' => 'fa-cogs', 'color' => '#1e88e5', 'bg' => '#e3f2fd'], // Engineering - Blue
            'D' => ['icon' => 'fa-shield', 'color' => '#43a047', 'bg' => '#e8f5e9'], // Defense - Green
            'E' => ['icon' => 'fa-flask', 'color' => '#3949ab', 'bg' => '#e8eaf6'], // Exact Sciences - Indigo
        ];
        return isset($styles[$letter]) ? $styles[$letter] : ['icon' => 'fa-star', 'color' => '#757575', 'bg' => '#f5f5f5'];
    };

    $vocational_areas = [];
    // Primary Area
    if (isset($record->vocational_areas) && !empty($record->vocational_areas)) {
        $style = $get_voc_style($record->vocational_areas);
        $vocational_areas[] = [
            'bg' => $style['bg'],
            'color' => $style['color'],
            'icon' => $style['icon'],
            'label' => get_string('vocational_areas', 'block_student_path'),
            'name' => $get_voc_name($record->vocational_areas)
        ];
    }

    // Secondary Area
    if (!empty($record->vocational_areas_secondary) && $record->vocational_areas_secondary !== 'none') {
        $style = $get_voc_style($record->vocational_areas_secondary);
        $vocational_areas[] = [
            'bg' => $style['bg'],
            'color' => $style['color'],
            'icon' => $style['icon'],
            'label' => get_string('vocational_areas_secondary', 'block_student_path'),
            'name' => $get_voc_name($record->vocational_areas_secondary)
        ];
    }

    $goals_data = [
        ['term' => 'short', 'label' => get_string('short_term', 'block_student_path'), 'goal' => isset($record->goal_short_term) ? $record->goal_short_term : '', 'action' => isset($record->action_short_term) ? $record->action_short_term : '', 'color' => '#48dbfb'],
        ['term' => 'medium', 'label' => get_string('medium_term', 'block_student_path'), 'goal' => isset($record->goal_medium_term) ? $record->goal_medium_term : '', 'action' => isset($record->action_medium_term) ? $record->action_medium_term : '', 'color' => '#0054ce'],
        ['term' => 'long', 'label' => get_string('long_term', 'block_student_path'), 'goal' => isset($record->goal_long_term) ? $record->goal_long_term : '', 'action' => isset($record->action_long_term) ? $record->action_long_term : '', 'color' => '#5f27cd'],
    ];

    $processed_goals = [];
    foreach ($goals_data as $g) {
        $processed_goals[] = [
            'color' => $g['color'],
            'label' => $g['label'],
            'str_goal' => get_string('goal', 'block_student_path'),
            'goal' => (empty($g['goal']) ? '-' : nl2br(s($g['goal']))),
            'str_action_plan' => get_string('action_plan_vp', 'block_student_path'),
            'action' => (empty($g['action']) ? '-' : nl2br(s($g['action'])))
        ];
    }

    $template_data = [
        'no_data' => false,
        'is_history_view' => $is_history_view,
        'str_history_view_msg' => get_string('history_view_msg_for_teacher', 'block_student_path'),
        'str_general_info' => get_string('general_info', 'block_student_path'),
        'general_fields' => $general_fields,
        'str_self_discovery' => get_string('self_discovery', 'block_student_path'),
        'str_personality_strengths' => get_string('personality_strengths', 'block_student_path'),
        'personality_strengths' => (isset($record->personality_strengths) ? nl2br(s($record->personality_strengths)) : '-'),
        'str_personality_weaknesses' => get_string('personality_weaknesses', 'block_student_path'),
        'personality_weaknesses' => (isset($record->personality_weaknesses) ? nl2br(s($record->personality_weaknesses)) : '-'),
        'str_vocational_profile' => get_string('vocational_profile', 'block_student_path'),
        'vocational_areas' => $vocational_areas,
        'str_emotional_skills_level' => get_string('emotional_skills_level', 'block_student_path'),
        'emotional_skills_level' => (isset($record->emotional_skills_level) ? nl2br(s($record->emotional_skills_level)) : '-'),
        'vocational_description' => (isset($record->vocational_description) ? s($record->vocational_description) : '-'),
        'str_goals_and_actions' => get_string('goals_and_actions', 'block_student_path'),
        'goals' => $processed_goals
    ];

    return $renderer->render_student_path_content($template_data);
}

/**
 * Helper to filter users by test status
 *
 * @param array $users Array of user objects
 * @param string $test_key Test key (e.g., 'learning_style')
 * @param string $status Status to filter by ('completed', 'in-progress', 'not-started')
 * @return array Filtered array of users
 */
function get_users_by_test_status($users, $test_key, $status) {
    $filtered = [];
    foreach ($users as $user) {
        $status_prop = $test_key . '_status';
        if (isset($user->$status_prop) && $user->$status_prop === $status) {
            $filtered[] = $user;
        }
    }
    return $filtered;
}

/**
 * Helper to prepare data for card back
 *
 * @param array $users Array of user objects
 * @param string $test_key Test key
 * @return array Data for card back template
 */
function prepare_card_back_data($users, $test_key) {
    $completed = get_users_by_test_status($users, $test_key, 'completed');
    $in_progress = get_users_by_test_status($users, $test_key, 'in-progress');
    $not_started = get_users_by_test_status($users, $test_key, 'not-started');
    
    $process_user = function($u) use ($test_key) {
        $date_prop = $test_key . '_date';
        $date_str = (isset($u->$date_prop) && $u->$date_prop > 0) ? userdate($u->$date_prop, '%d/%m/%Y') : '';
        // For in-progress, if date is 0, maybe show 'Sin actividad reciente' as in original code?
        // Original code: $date_str = ($u->$date_prop > 0) ? userdate($u->$date_prop, '%d/%m/%Y') : 'Sin actividad reciente';
        // But for completed it was 'Fecha desconocida'.
        
        return [
            'id' => $u->id,
            'fullname' => fullname($u),
            'email' => $u->email,
            'date_str' => $date_str
        ];
    };

    return [
        'completed' => array_map($process_user, $completed),
        'in_progress' => array_map($process_user, $in_progress),
        'not_started' => array_map($process_user, $not_started)
    ];
}

/**
 * Prepares data for the view_profile template.
 *
 * @param stdClass $user The user object.
 * @param stdClass $course The course object.
 * @param stdClass $profile The integrated student profile data.
 * @param array $history_records Array of history records.
 * @return array Data for the mustache template.
 */
function block_student_path_prepare_profile_data($user, $course, $profile, $history_records) {
    global $OUTPUT, $CFG, $DB;

    // User Picture
    $user_picture = $OUTPUT->user_picture($user, array('size' => 100, 'class' => 'rounded-circle', 'style' => 'width: 100px; height: 100px;'));

    // Icons
    $student_path_icon_url = $OUTPUT->image_url('icon', 'block_student_path');
    $learning_style_icon_url = $OUTPUT->image_url('learning_style_icon', 'block_student_path');
    $personality_icon_url = $OUTPUT->image_url('personality_test_icon', 'block_student_path');
    $chaside_icon_url = $OUTPUT->image_url('chaside_icon', 'block_student_path');
    $tmms24_icon_url = $OUTPUT->image_url('tmms_24_icon', 'block_student_path');

    // Summaries
    $learning_style_summary = get_learning_style_summary(isset($profile->learning_style_data) ? $profile->learning_style_data : null);
    $personality_summary = get_personality_summary(isset($profile->personality_data) ? $profile->personality_data : null);
    
    $chaside_record = isset($profile->chaside_data) ? json_decode($profile->chaside_data) : null;
    $chaside_input = isset($profile->chaside_data) ? $profile->chaside_data : null;
    if ($chaside_record && isset($chaside_record->responses)) {
        $chaside_input = $chaside_record->responses;
    }
    $chaside_summary = get_chaside_summary_complete($chaside_input);
    
    $tmms24_summary = get_tmms24_summary(isset($profile->tmms_24_data) ? $profile->tmms_24_data : null, $user, $course->id);

    // History Selector
    $history_options = [];
    $selected_history = optional_param('history_id', 0, PARAM_INT);
    if ($history_records) {
        foreach ($history_records as $h) {
            $history_options[] = [
                'id' => $h->id,
                'period' => block_student_path_format_period($h->period),
                'date' => userdate($h->timecreated, '%d/%m/%Y'),
                'selected' => ($selected_history == $h->id)
            ];
        }
    }

    // Identity Map Content
    $record_to_show = $DB->get_record('block_student_path', array('user' => $user->id));
    $is_history_view = false;

    if ($selected_history && $history_records && isset($history_records[$selected_history])) {
        $h_record = $history_records[$selected_history];
        $content = json_decode($h_record->content);
        if ($content) {
            $record_to_show = $content;
            $is_history_view = true;
        }
    }

    $student_path_content = '';
    if ($record_to_show) {
        if ($is_history_view || (isset($record_to_show->is_completed) && $record_to_show->is_completed)) {
            $student_path_content = render_student_path_content($record_to_show, $is_history_view);
        } else {
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
            foreach ($fields_to_check as $field) {
                if (!empty($record_to_show->$field)) {
                    $filled++;
                }
            }
            $student_path_content = render_in_progress_view($filled, $total, 'student-path-progress');
        }
    } else {
        $student_path_content = render_not_started_view('student_path');
    }

    return [
        'userid' => $user->id,
        'courseid' => $course->id,
        'fullname' => fullname($user),
        'email' => $user->email,
        'idnumber' => $user->idnumber,
        'user_picture' => $user_picture,
        'student_path_icon_url' => $student_path_icon_url,
        'learning_style_icon_url' => $learning_style_icon_url,
        'personality_icon_url' => $personality_icon_url,
        'chaside_icon_url' => $chaside_icon_url,
        'tmms24_icon_url' => $tmms24_icon_url,
        'completion_percentage' => $profile->completion_percentage,
        'learning_style_summary' => $learning_style_summary,
        'personality_summary' => $personality_summary,
        'chaside_summary' => $chaside_summary,
        'tmms24_summary' => $tmms24_summary,
        'has_history' => !empty($history_options),
        'history_options' => array_values($history_options), // Ensure it's a list
        'is_history_view' => $is_history_view,
        'student_path_content' => $student_path_content,
        'back_to_admin_url' => $CFG->wwwroot . '/blocks/student_path/admin_view.php?cid=' . $course->id,
        'back_to_course_url' => $CFG->wwwroot . '/course/view.php?id=' . $course->id,
        'ajax_url' => $CFG->wwwroot . '/blocks/student_path/ajax_get_test_details.php',
        
        // Strings
        'str_progress' => get_string('progress', 'block_student_path'),
        'str_learning_style' => get_string('learning_style_test', 'block_student_path'),
        'str_personality' => get_string('personality_test', 'block_student_path'),
        'str_chaside' => get_string('chaside_test', 'block_student_path'),
        'str_tmms24' => get_string('tmms_24_test', 'block_student_path'),
        'str_student_path_map' => get_string('student_path_map', 'block_student_path'),
        'str_current_version' => get_string('current_version', 'block_student_path'),
        'str_history_view_msg' => get_string('history_view_msg_for_teacher', 'block_student_path'),
        'str_back_to_admin' => get_string('back_to_admin', 'block_student_path'),
        'str_back_to_course' => get_string('back_to_course', 'block_student_path'),
    ];
}

/**
 * Prepares the user table data for the admin dashboard.
 *
 * @param array $users List of users.
 * @param int $courseid The course ID.
 * @return array The prepared table data.
 */
function block_student_path_prepare_users_table_data(array $users, int $courseid): array {
    $table_data = [
        'str_user_name' => get_string('user_name', 'block_student_path'),
        'str_test_progress' => get_string('test_progress', 'block_student_path'),
        'str_tests_completed' => get_string('tests_completed', 'block_student_path'),
        'str_completion_percentage' => get_string('completion_percentage', 'block_student_path'),
        'str_last_activity' => get_string('last_activity', 'block_student_path'),
        'str_actions' => get_string('actions', 'block_student_path'),
        'str_no_users_found' => get_string('no_users_found', 'block_student_path'),
        'str_view_details' => get_string('view_details', 'block_student_path'),
        'users' => []
    ];

    if (!empty($users)) {
        foreach ($users as $user) {
            $status_class = 'not-started';
            if ($user->total_completed == 5) {
                $status_class = 'completed';
            } else if ($user->total_completed > 0 || $user->total_in_progress > 0) {
                $status_class = 'in-progress';
            }

            $indicators = [
                [
                    'status' => $user->learning_style_status,
                    'userid' => $user->id,
                    'test' => 'learning_style',
                    'tooltip' => get_string('learning_styles', 'block_student_path') . ': ' . get_string(str_replace('-', '_', $user->learning_style_status), 'block_student_path'),
                    'label' => 'LS'
                ],
                [
                    'status' => $user->personality_status,
                    'userid' => $user->id,
                    'test' => 'personality',
                    'tooltip' => get_string('personality', 'block_student_path') . ': ' . get_string(str_replace('-', '_', $user->personality_status), 'block_student_path'),
                    'label' => 'PT'
                ],
                [
                    'status' => $user->chaside_status,
                    'userid' => $user->id,
                    'test' => 'chaside',
                    'tooltip' => 'CHASIDE: ' . get_string(str_replace('-', '_', $user->chaside_status), 'block_student_path'),
                    'label' => 'CH'
                ],
                [
                    'status' => $user->tmms24_status,
                    'userid' => $user->id,
                    'test' => 'tmms24',
                    'tooltip' => 'TMMS-24: ' . get_string(str_replace('-', '_', $user->tmms24_status), 'block_student_path'),
                    'label' => 'TM'
                ],
                [
                    'status' => $user->student_path_status,
                    'userid' => $user->id,
                    'test' => 'student_path',
                    'tooltip' => get_string('student_path_map', 'block_student_path') . ': ' . get_string(str_replace('-', '_', $user->student_path_status), 'block_student_path'),
                    'label' => 'IM'
                ]
            ];

            $table_data['users'][] = [
                'status_class' => $status_class,
                'fullname' => fullname($user),
                'email' => $user->email,
                'indicators' => $indicators,
                'total_completed' => $user->total_completed,
                'completion_percentage' => $user->completion_percentage,
                'completion_percentage_rounded' => round($user->completion_percentage, 0),
                'last_activity_str' => ($user->last_activity > 0) ? userdate($user->last_activity, get_string('strftimedatetime')) : get_string('no_activity', 'block_student_path'),
                'view_profile_url' => (new moodle_url('/blocks/student_path/view_profile.php', array('uid' => $user->id, 'cid' => $courseid)))->out(false),
                'str_view_details' => get_string('view_details', 'block_student_path')
            ];
        }
    }

    return $table_data;
}
