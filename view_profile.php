<?php
/**
 * View Profile Page - Student Path Block
 *
 * @package    block_student_path
 * @copyright  2026 SAVIO - Sistema de Aprendizaje Virtual Interactivo (UTB)
 * @author     SAVIO Development Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');

require_login();

$userid = required_param('uid', PARAM_INT);
$courseid = required_param('cid', PARAM_INT);

// Verify Teacher Access
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($courseid);
$PAGE->set_course($course);

// Check if the block is added to the course
if (!$DB->record_exists('block_instances', array('blockname' => 'student_path', 'parentcontextid' => $context->id))) {
    redirect(new moodle_url('/course/view.php', array('id' => $courseid)));
}

if (!has_capability('block/student_path:viewstudentdata', $context)) {
    // Si es estudiante, redirigir a su vista
    if (has_capability('block/student_path:makemap', $context)) {
        redirect(new moodle_url('/blocks/student_path/view.php', ['cid' => $courseid]));
    }
    // Si no, error estándar
    require_capability('block/student_path:viewstudentdata', $context);
}

// Check if the student belongs to this course (Anti-Gossip)
if (!is_enrolled($context, $userid, '', true)) {
    print_error('user_not_enrolled', 'block_student_path');
}

// Get Student Data
$user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);

// Get Integrated Profile
$profile = get_integrated_student_profile($userid, $courseid);

// Get History Records for this student
$history_records = $DB->get_records('block_student_path_history', array('userid' => $userid), 'period DESC');

// Page Setup
$PAGE->set_url(new moodle_url('/blocks/student_path/view_profile.php', array('uid' => $userid, 'cid' => $courseid)));
$PAGE->set_context($context);
$PAGE->set_title(get_string('integrated_student_profile', 'block_student_path') . ': ' . fullname($user));
$PAGE->set_heading(get_string('integrated_student_profile', 'block_student_path'));
$PAGE->set_pagelayout('incourse');

// Add CSS
$PAGE->requires->css('/blocks/student_path/styles.css');

echo $OUTPUT->header();

$data = block_student_path_prepare_profile_data($user, $course, $profile, $history_records);
echo $OUTPUT->render_from_template('block_student_path/view_profile', $data);

echo $OUTPUT->footer();
?>
