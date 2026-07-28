<?php
/**
 * Student Path Block
 *
 * @package    block_student_path
 * @copyright  2026 SAVIO - Sistema de Aprendizaje Virtual Interactivo (UTB)
 * @author     SAVIO Development Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/lib.php');

class block_student_path extends block_base
{

    public function init()
    {
        $this->title = get_string('pluginname', 'block_student_path');
    }

    public function instance_allow_multiple()
    {
        return false;
    }

    public function get_content()
    {
        global $OUTPUT, $CFG, $DB, $USER, $COURSE;

        if ($COURSE->id == SITEID) {
            return $this->content;
        }

        if ($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->text = "";
        $this->content->footer = '';

        if (empty($this->instance)) {
            return $this->content;
        }

        if (!isloggedin()) {
            return $this->content;
        }

        // Load new styles
        $this->page->requires->css('/blocks/student_path/styles.css');

        // Check permissions
        $context = context_course::instance($COURSE->id);
        
        // Check if user is student (using capability)
        $is_student = has_capability('block/student_path:makemap', $context);

        // Los datos integrados solo son visibles con una autorización expresa.
        $is_teacher = has_capability('block/student_path:viewstudentdata', $context);

        if (!$is_teacher && !$is_student) {
            return $this->content;
        }

        $this->content->text .= '<div class="block_student_path">';

        if ($is_teacher) {
            $this->content->text .= $this->get_secure_admin_launcher($COURSE);
        } else if ($is_student) {
            $this->content->text .= $this->get_student_content($DB, $USER, $COURSE);
        }

        $this->content->text .= '</div>';

        return $this->content;
    }

    private function get_secure_admin_launcher($COURSE) {
        global $OUTPUT;

        return $OUTPUT->render_from_template('block_student_path/admin_launcher', [
            'icon_html' => '<img src="' . $this->get_icon_url()->out(false) . '" alt="" style="width: 4.25rem; height: 4.25rem; display: block; margin: 0 auto;">',
            'security_label' => get_string('sensitive_data', 'block_student_path'),
            'title' => get_string('integrated_dashboard', 'block_student_path'),
            'admin_url' => (new moodle_url('/blocks/student_path/admin_view.php', ['cid' => $COURSE->id]))->out(false),
            'button_label' => get_string('open_admin_panel', 'block_student_path'),
        ]);
    }

    /**
     * Generates the content for the student view
     */
    private function get_student_content($DB, $USER, $COURSE) {
        global $OUTPUT;
        
        // Use the centralized function to get progress
        $progress = get_student_path_progress($USER->id);
        $status = $progress['status'];
        $filled_count = $progress['filled'];
        $total_fields = $progress['total'];
        
        $is_completed = ($status === 'completed');
        $in_progress = ($status === 'in-progress');

        // Check if show description is enabled
        $showdescriptions = 0;
        if (isset($this->config->showdescriptions)) {
            $showdescriptions = $this->config->showdescriptions;
        }
        
        // Check if entry exists for other data
        $entry = $DB->get_record('block_student_path', array('user' => $USER->id));
        
        $data = [
            'is_completed' => $is_completed,
            'in_progress' => $in_progress,
            'show_descriptions' => $showdescriptions,
            'icon_url' => $this->get_icon_url(),
            'str_profile_completed' => get_string('profile_completed', 'block_student_path'),
            'str_profile_completed_small' => get_string('profile_completed_small', 'block_student_path'),
            'str_pluginname' => get_string('pluginname', 'block_student_path'),
            'str_discover_path' => get_string('discover_path', 'block_student_path'),
            'str_student_path_intro' => get_string('student_path_intro', 'block_student_path'),
            'str_in_progress' => get_string('in_progress', 'block_student_path'),
            'filled_count' => $filled_count,
            'total_fields' => $total_fields,
            'percentage' => ($filled_count / $total_fields) * 100,
            'percentage_formatted' => number_format(($filled_count / $total_fields) * 100, 1),
            'str_completed' => get_string('completed', 'block_student_path'),
            'str_for_what_map' => get_string('for_what_map', 'block_student_path'),
            'str_for_what_map_one' => get_string('for_what_map_one', 'block_student_path'),
            'str_for_what_map_two' => get_string('for_what_map_two', 'block_student_path'),
            'str_for_what_map_three' => get_string('for_what_map_three', 'block_student_path'),
            'str_student_path_title' => get_string('student_path_title', 'block_student_path'),
            'str_name' => get_string('name', 'block_student_path'),
            'user_fullname' => s($USER->firstname . ' ' . $USER->lastname),
            'str_program' => get_string('program', 'block_student_path'),
            'str_admission_year' => get_string('admission_year', 'block_student_path'),
        ];

        // Program display logic
        $program_display = '-';
        if (isset($entry->program) && !empty($entry->program)) {
            if (strpos($entry->program, 'prog_') === 0 && get_string_manager()->string_exists($entry->program, 'block_student_path')) {
                $program_display = get_string($entry->program, 'block_student_path');
            } else {
                $program_display = s($entry->program);
            }
        }
        $data['program_display'] = $program_display;
        $data['admission_year'] = isset($entry->admission_year) ? s($entry->admission_year) : '-';

        // Button logic
        $url = new moodle_url('/blocks/student_path/view.php', array('cid' => $COURSE->id));
        if ($is_completed) {
             $url->param('edit', 1);
        }
        $data['action_url'] = $url->out();

        if (!$entry) {
            // Not Started
            $data['btn_text'] = get_string('start_map', 'block_student_path');
            $data['btn_class'] = 'btn-primary-custom';
            $data['btn_icon'] = 'fa-rocket';
        } elseif ($in_progress) {
            // In Progress
            $data['btn_text'] = get_string('continue_profile', 'block_student_path');
            $data['btn_class'] = 'btn-primary-custom';
            $data['btn_icon'] = 'fa-play';
        } else {
            // Completed
            $data['btn_text'] = get_string('view_edit_map', 'block_student_path');
            $data['btn_class'] = 'btn-primary-custom'; 
            $data['btn_icon'] = 'fa-eye';
        }
        
        return $OUTPUT->render_from_template('block_student_path/student_view', $data);
    }

    /**
     * Generates the content for the teacher view
     */
    private function get_teacher_content($COURSE) {
        global $OUTPUT;
        
        // Get users and stats efficiently
        $users = [];
        try {
            $users = get_course_users_with_test_progress($COURSE->id);
        } catch (Exception $e) {
            // Se manejará silenciosamente
        }
        $stats = get_integrated_course_stats($COURSE->id, $users);
        
        $data = [
            'icon_url' => $this->get_icon_url(),
            'str_integrated_dashboard' => get_string('integrated_dashboard', 'block_student_path'),
            'str_course_overview' => get_string('course_overview', 'block_student_path'),
            'total_students' => (int)$stats->total_students,
            'str_total_students' => get_string('total_students', 'block_student_path'),
            'complete_profiles' => (int)$stats->complete_profiles,
            'str_complete_profiles' => get_string('complete_profiles', 'block_student_path'),
            'completion_rate_formatted' => number_format($stats->complete_profiles_percentage, 1),
            'str_completion_rate' => get_string('completion_rate', 'block_student_path'),
            'str_evaluation_breakdown' => get_string('evaluation_breakdown', 'block_student_path'),
            'str_view_identity_map' => get_string('view_identity_map', 'block_student_path'),
        ];

        $data['progress_bars'] = [
            [
                'label' => get_string('learning_style_test', 'block_student_path'),
                'completed' => (int)$stats->learning_style_completed,
                'total' => (int)$stats->total_students,
                'percentage' => number_format($stats->learning_style_percentage, 1),
                'color_class' => 'bg-learning-style'
            ],
            [
                'label' => get_string('personality_test', 'block_student_path'),
                'completed' => (int)$stats->personality_completed,
                'total' => (int)$stats->total_students,
                'percentage' => number_format($stats->personality_percentage, 1),
                'color_class' => 'bg-personality'
            ],
            [
                'label' => get_string('chaside_test', 'block_student_path'),
                'completed' => (int)$stats->chaside_completed,
                'total' => (int)$stats->total_students,
                'percentage' => number_format($stats->chaside_percentage, 1),
                'color_class' => 'bg-chaside'
            ],
            [
                'label' => get_string('tmms_24_test', 'block_student_path'),
                'completed' => (int)$stats->tmms24_completed,
                'total' => (int)$stats->total_students,
                'percentage' => number_format($stats->tmms24_percentage, 1),
                'color_class' => 'bg-tmms-24'
            ],
            [
                'label' => get_string('student_profile', 'block_student_path'),
                'completed' => (int)$stats->student_path_completed,
                'total' => (int)$stats->total_students,
                'percentage' => number_format($stats->student_path_percentage, 1),
                'color_class' => 'bg-student-path'
            ]
        ];
        
        $url = new moodle_url('/blocks/student_path/admin_view.php', array('cid' => $COURSE->id));
        $data['action_url'] = $url->out();
        
        return $OUTPUT->render_from_template('block_student_path/teacher_view', $data);
    }

    /**
     * Helper to get the icon URL
     */
    private function get_icon_url() {
        return new moodle_url('/blocks/student_path/pix/icon.svg');
    }
}
