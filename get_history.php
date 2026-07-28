<?php
/**
 * Get History - Student Path Block
 *
 * @package    block_student_path
 * @copyright  2026 SAVIO - Sistema de Aprendizaje Virtual Interactivo (UTB)
 * @author     SAVIO Development Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');

// Check if user is logged in
if (!isloggedin()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$id = required_param('id', PARAM_INT);
$courseid = optional_param('courseid', SITEID, PARAM_INT);

// Check permissions
// We ensure the user can only access their own history records via the query below
$context = context_system::instance();
if ($courseid && $courseid != SITEID) {
    $context = context_course::instance($courseid);
}

$is_teacher = has_capability('block/student_path:viewstudentdata', $context);
$can_make_map = has_capability('block/student_path:makemap', $context);

if (!$is_teacher && !$can_make_map) {
    echo json_encode(['success' => false, 'message' => 'No permission']);
    exit;
}

$history = $DB->get_record('block_student_path_history', array('id' => $id));

if ($history) {
    // Allow if teacher OR if user owns the record
    if ($is_teacher || $history->userid == $USER->id) {
        echo json_encode(['success' => true, 'content' => $history->content]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No permission to view this record']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Not found']);
}
