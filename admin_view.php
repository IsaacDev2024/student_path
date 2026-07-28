<?php
/**
 * Admin Dashboard for Student Path Block
 *
 * @package    block_student_path
 * @copyright  2026 SAVIO - Sistema de Aprendizaje Virtual Interactivo (UTB)
 * @author     SAVIO Development Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once(dirname(__FILE__) . '/lib.php');

// Verificar login y obtener curso
require_login();
$courseid = required_param('cid', PARAM_INT);
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

// Configurar contexto de curso
$context = context_course::instance($courseid);
$PAGE->set_course($course);
$PAGE->set_context($context);
$PAGE->set_url('/blocks/student_path/admin_view.php', array('cid' => $courseid));
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('admin_dashboard_title', 'block_student_path'));
$PAGE->set_heading(get_string('admin_dashboard_title', 'block_student_path'));

// Check if the block is added to the course
if (!$DB->record_exists('block_instances', array('blockname' => 'student_path', 'parentcontextid' => $context->id))) {
    redirect(new moodle_url('/course/view.php', array('id' => $courseid)));
}

// Verificar permisos (profesor o admin)
if (!has_capability('block/student_path:viewstudentdata', $context)) {
    // Si es estudiante, redirigir a su vista
    if (has_capability('block/student_path:makemap', $context)) {
        redirect(new moodle_url('/blocks/student_path/view.php', ['cid' => $courseid]));
    }
    // Si no, error estándar
    require_capability('block/student_path:viewstudentdata', $context);
}

// Incluir CSS personalizado del admin dashboard
$PAGE->requires->css(new moodle_url('/blocks/student_path/styles.css'));

// Obtener usuarios del curso para poblar las tarjetas (y la tabla más abajo)
$users = [];
try {
    $users = get_course_users_with_test_progress($courseid);
} catch (Exception $e) {
    // Se manejará el error al mostrar la tabla
}

// Obtener estadísticas del curso
$system_stats = get_integrated_course_stats($courseid, $users);

// Sort users by last activity (descending), putting those with no activity at the bottom
usort($users, function($a, $b) {
    if ($a->last_activity == $b->last_activity) {
        return 0;
    }
    return ($a->last_activity > $b->last_activity) ? -1 : 1;
});

// --- PAGINATION & FILTERING LOGIC ---
$perpage = optional_param('perpage', 50, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$status_filter = optional_param('status', 'all', PARAM_ALPHANUMEXT);
$search = optional_param('search', '', PARAM_NOTAGS);

// Filter users if status is set
if ($status_filter !== 'all') {
    $users = array_filter($users, function($u) use ($status_filter) {
        $s = 'not-started';
        if ($u->total_completed == 5) {
            $s = 'completed';
        } else if ($u->total_completed > 0 || $u->total_in_progress > 0) {
            $s = 'in-progress';
        }
        return $s === $status_filter;
    });
}

// Filter users by Search Term
if (!empty($search)) {
    $search = core_text::strtolower($search);
    $users = array_filter($users, function($u) use ($search) {
        return (strpos(core_text::strtolower(fullname($u)), $search) !== false) || 
               (strpos(core_text::strtolower($u->email), $search) !== false);
    });
}

$total_users = count($users);
$baseurl = new moodle_url('/blocks/student_path/admin_view.php', array('cid' => $courseid));
if ($status_filter !== 'all') {
    $baseurl->param('status', $status_filter);
}
if (!empty($search)) {
    $baseurl->param('search', $search);
}
if ($perpage != 50) {
    $baseurl->param('perpage', $perpage);
}

// Slice users for the table
$table_users = array_slice($users, $page * $perpage, $perpage);
$pagingbar = new paging_bar($total_users, $page, $perpage, $baseurl, 'page');

// --- AJAX HANDLER ---
if (optional_param('ajax_table', 0, PARAM_BOOL)) {
    require_sesskey(); // Security check to prevent CSRF
    
    // Prepare data for table template
    $table_data = block_student_path_prepare_users_table_data($table_users, $courseid);

    echo $OUTPUT->render_from_template('block_student_path/users_table', $table_data);
    echo $OUTPUT->render($pagingbar);
    die();
}

echo $OUTPUT->header();
?>
<?php
// --- PREPARE DATA FOR TEMPLATE ---

// 1. Stats Overview Data
$stats_cards = [
    [
        'icon' => 'fa-users',
        'title' => get_string('participating_students', 'block_student_path'),
        'value' => $system_stats->active_users,
        'badge_icon' => 'fa-info-circle',
        'badge_text' => get_string('total_student_enrolled', 'block_student_path', $system_stats->total_users),
        'progress_label' => get_string('activity_rate', 'block_student_path'),
        'progress_text' => $system_stats->active_users . ' / ' . $system_stats->total_users,
        'progress_percentage' => $system_stats->participation_rate
    ],
    [
        'icon' => 'fa-check-square-o',
        'title' => get_string('completed_evaluations', 'block_student_path'),
        'value' => $system_stats->total_completed,
        'badge_icon' => 'fa-trophy',
        'badge_text' => get_string('tests_completed', 'block_student_path'),
        'progress_label' => get_string('total_progress', 'block_student_path'),
        'progress_text' => $system_stats->total_completed . ' / ' . ($system_stats->total_users * 5),
        'progress_percentage' => $system_stats->completion_percentage
    ],
    [
        'icon' => 'fa-clock-o',
        'title' => get_string('in_progress_tests', 'block_student_path'),
        'value' => $system_stats->total_in_progress,
        'badge_icon' => 'fa-spinner',
        'badge_text' => get_string('not_finished_tests', 'block_student_path'),
        'footer_text' => ($system_stats->total_in_progress == 0) 
            ? get_string('no_pending_evaluations', 'block_student_path')
            : (($system_stats->total_in_progress == 1)
                ? get_string('corresponds_to_single_student', 'block_student_path')
                : (($system_stats->users_with_in_progress == 1)
                    ? get_string('distributed_among_student', 'block_student_path', 1)
                    : get_string('distributed_among_students', 'block_student_path', $system_stats->users_with_in_progress)))
    ],
    [
        'icon' => 'fa-id-card',
        'title' => get_string('complete_profiles', 'block_student_path'),
        'value' => $system_stats->complete_profiles,
        'badge_icon' => 'fa-check-circle',
        'badge_text' => get_string('cycle_completed', 'block_student_path'),
        'progress_label' => get_string('goal_achieved', 'block_student_path'),
        'progress_text' => $system_stats->complete_profiles . ' / ' . $system_stats->total_users,
        'progress_percentage' => $system_stats->complete_profiles_percentage
    ]
];

// 2. Test Cards Data
$test_cards = [];
$tests_config = [
    'learning_style' => ['title' => get_string('learning_style_test', 'block_student_path'), 'icon' => 'learning_style_icon.svg'],
    'personality' => ['title' => get_string('personality_test', 'block_student_path'), 'icon' => 'personality_test_icon.svg'],
    'chaside' => ['title' => get_string('chaside_test', 'block_student_path'), 'icon' => 'chaside_icon.svg'],
    'tmms24' => ['title' => get_string('tmms_24_test', 'block_student_path'), 'icon' => 'tmms_24_icon.svg'],
    'student_path' => ['title' => get_string('student_profile', 'block_student_path'), 'icon' => 'icon.svg'],
];

foreach ($tests_config as $key => $info) {
    $back_data = prepare_card_back_data($users, $key);
    
    // Helper to add first/last flags for Mustache
    $add_flags = function($list) {
        if (empty($list)) return [];
        $values = array_values($list);
        if (!empty($values)) {
            $values[0]['first'] = true;
            $values[count($values)-1]['last'] = true;
        }
        return $values;
    };

    $card = [
        'type_class' => str_replace('_', '-', $key),
        'type_key' => $key,
        'title' => $info['title'],
        'icon_url' => $CFG->wwwroot . '/blocks/student_path/pix/' . $info['icon'],
        'completed_count' => $system_stats->{$key . '_completed'},
        'in_progress_count' => $system_stats->{$key . '_in_progress'},
        'not_started_count' => $system_stats->{$key . '_not_started'},
        'percentage' => $system_stats->{$key . '_percentage'},
        'percentage_rounded' => round($system_stats->{$key . '_percentage'}, 1),
        'flex1' => ($key === 'student_path'),
        
        'str_completed' => get_string('completed', 'block_student_path'),
        'str_in_progress' => get_string('in_progress', 'block_student_path'),
        'str_not_started' => get_string('not_started', 'block_student_path'),
        'str_last_access' => get_string('last_access', 'block_student_path'),

        'str_no_test_completed' => get_string('no_test_completed', 'block_student_path'),
        'str_no_test_in_progress' => get_string('no_test_in_progress', 'block_student_path'),
        'str_all_tests_started' => get_string('all_tests_started', 'block_student_path'),
        
        'completed_users' => $add_flags($back_data['completed']),
        'in_progress_users' => $add_flags($back_data['in_progress']),
        'not_started_users' => $add_flags($back_data['not_started']),
    ];
    $test_cards[] = $card;
}

// 3. Users Table Data
$table_data_initial = block_student_path_prepare_users_table_data($table_users, $courseid);

// 4. Per Page Options
$per_page_options = [];
$opts = [10, 25, 50, 100, 200];

foreach ($opts as $opt) {
    $per_page_options[] = [
        'value' => $opt,
        'selected' => ($perpage == $opt)
    ];
}

// 5. Main Dashboard Data
$dashboard_data = [
    'title' => get_string('admin_dashboard_title', 'block_student_path'),
    'iconurl' => $CFG->wwwroot . '/blocks/student_path/pix/icon.svg',
    'subtitle' => format_text(get_string('admin_dashboard_subtitle', 'block_student_path'), FORMAT_HTML),
    'str_progress_by_test' => get_string('progress_by_test', 'block_student_path'),
    'str_users_list' => get_string('users_list', 'block_student_path'),
    'str_back_to_course' => get_string('back_to_course', 'block_student_path'),
    'course_url' => $CFG->wwwroot . '/course/view.php?id=' . $courseid,
    
    // Pass current filter status to template
    'filter_status_all' => ($status_filter === 'all'),
    'filter_status_completed' => ($status_filter === 'completed'),
    'filter_status_in_progress' => ($status_filter === 'in-progress'),
    'filter_status_not_started' => ($status_filter === 'not-started'),
    'course_url' => $CFG->wwwroot . '/course/view.php?id=' . $courseid,
    
    // Include Stats
    'stats_cards' => $stats_cards,

    'test_cards' => $test_cards,
    
    // Include Users Section Data
    'str_search_users' => get_string('search_users', 'block_student_path'),
    'str_pag' => get_string('pag', 'block_student_path'),
    'str_all_students' => get_string('all_students', 'block_student_path'),
    'str_complete_profiles_only' => get_string('complete_profiles_only', 'block_student_path'),
    'str_partial_profiles_only' => get_string('partial_profiles_only', 'block_student_path'),
    'str_no_profiles_only' => get_string('no_profiles_only', 'block_student_path'),
    'str_export_data' => get_string('export_data', 'block_student_path'),
    'export_excel_url' => (new moodle_url('/blocks/student_path/export.php', array('cid' => $courseid, 'format' => 'excel', 'sesskey' => sesskey())))->out(false),
    'export_csv_url' => (new moodle_url('/blocks/student_path/export.php', array('cid' => $courseid, 'format' => 'csv', 'sesskey' => sesskey())))->out(false),
    'export_json_url' => (new moodle_url('/blocks/student_path/export.php', array('cid' => $courseid, 'format' => 'json', 'sesskey' => sesskey())))->out(false),
    'per_page_options' => $per_page_options,
    'table_html' => $OUTPUT->render_from_template('block_student_path/users_table', $table_data_initial),
    'paging_bar_html' => $OUTPUT->render($pagingbar),
];

echo $OUTPUT->render_from_template('block_student_path/admin_dashboard', $dashboard_data);
?>

<script>
// --- Helper functions for scroll locking ---
function lockBodyScroll() {
    // Calculate scrollbar width
    const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
    if (scrollbarWidth > 0) {
        document.body.style.paddingRight = scrollbarWidth + 'px';
        // Handle fixed navbars if present (common in Moodle/Bootstrap)
        const fixedNav = document.querySelector('.fixed-top, .navbar-fixed-top');
        if (fixedNav) {
            // Check if it already has padding from other scripts to avoid issues, 
            // but for now simpler is better as per instructions.
            fixedNav.style.paddingRight = scrollbarWidth + 'px';
        }
    }
    document.body.style.overflow = 'hidden';
}

function unlockBodyScroll() {
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
    const fixedNav = document.querySelector('.fixed-top, .navbar-fixed-top');
    if (fixedNav) {
        fixedNav.style.paddingRight = '';
    }
}

// Search functionality
let searchTimeout = null;
document.getElementById('searchUsers').addEventListener('input', function(e) {
    const searchTerm = e.target.value;
    
    // Clear existing timeout
    if (searchTimeout) clearTimeout(searchTimeout);
    
    // Debounce 300ms
    searchTimeout = setTimeout(() => {
        const fetchUrl = new URL(window.location.origin + window.location.pathname);
        fetchUrl.searchParams.set('cid', '<?php echo $courseid; ?>');
        fetchUrl.searchParams.set('search', searchTerm);
        fetchUrl.searchParams.set('page', 0); // Reset page
        fetchUrl.searchParams.set('status', document.getElementById('filterStatus').value);
        loadTable(fetchUrl.toString());
    }, 300);
});


// Card Interaction Logic with FLIP animation
function toggleCard(card) {
    if (card.classList.contains('expanded')) return;
    
    // Cleanup any existing placeholders just in case
    const existingPlaceholder = card.parentNode.querySelector('.card-placeholder');
    if (existingPlaceholder && existingPlaceholder.parentNode) {
        existingPlaceholder.parentNode.removeChild(existingPlaceholder);
    }

    // 1. First: Calculate initial position (First)
    const rect = card.getBoundingClientRect();
    const initialTop = rect.top;
    const initialLeft = rect.left;
    const initialWidth = rect.width;
    const initialHeight = rect.height;
    
    // Create a placeholder to prevent layout shift
    const placeholder = document.createElement('div');
    placeholder.className = 'card-placeholder';
    placeholder.style.width = initialWidth + 'px';
    placeholder.style.height = initialHeight + 'px';
    card.parentNode.insertBefore(placeholder, card);
    
    // Store reference to placeholder on the card element
    card._placeholder = placeholder;
    
    // 2. Set fixed position at start coordinates
    card.style.setProperty('position', 'fixed', 'important');
    card.style.setProperty('top', initialTop + 'px', 'important');
    card.style.setProperty('left', initialLeft + 'px', 'important');
    card.style.setProperty('width', initialWidth + 'px', 'important');
    card.style.setProperty('height', initialHeight + 'px', 'important');
    card.style.setProperty('z-index', '2000', 'important');
    card.style.setProperty('margin', '0', 'important');
    
    // Force reflow
    void card.offsetWidth;
    
    // 3. Add expanded class to trigger transition to center (Last)
    requestAnimationFrame(() => {
        card.classList.add('expanded');
        document.getElementById('card-backdrop').classList.add('active');
        lockBodyScroll();
        
        // Reset inline styles to let CSS class take over for centering
        card.style.top = '';
        card.style.left = '';
        card.style.width = '';
        card.style.height = '';
    });
}

// Close buttons
document.querySelectorAll('.close-card-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        const card = this.closest('.test-card');
        if (card) {
            closeCard(card);
        }
    });
});

// Backdrop click
document.getElementById('card-backdrop').addEventListener('click', function() {
    const expandedCard = document.querySelector('.test-card.expanded');
    if (expandedCard) {
        closeCard(expandedCard);
    }
});

function closeCard(card) {
    // 1. Get current (expanded) rect
    const rect = card.getBoundingClientRect();
    
    // 2. Find placeholder to know where to go back
    // Use the stored reference if available, otherwise fallback to querySelector
    const placeholder = card._placeholder || card.parentNode.querySelector('.card-placeholder');
    
    if (!placeholder) {
        // Fallback if something went wrong
        card.classList.remove('expanded');
        document.getElementById('card-backdrop').classList.remove('active');
        unlockBodyScroll();
        return;
    }
    
    const targetRect = placeholder.getBoundingClientRect();
    
    // 3. Set explicit styles to current state to prepare for transition
    card.classList.remove('expanded'); // Remove class first to lose the CSS-based centering
    
    // Set to fixed at current position
    card.style.setProperty('position', 'fixed', 'important');
    card.style.setProperty('top', rect.top + 'px', 'important');
    card.style.setProperty('left', rect.left + 'px', 'important');
    card.style.setProperty('width', rect.width + 'px', 'important');
    card.style.setProperty('height', rect.height + 'px', 'important');
    card.style.setProperty('z-index', '2000', 'important');
    
    // Force reflow
    void card.offsetWidth;
    
    // Animate to target
    requestAnimationFrame(() => {
        card.style.setProperty('top', targetRect.top + 'px', 'important');
        card.style.setProperty('left', targetRect.left + 'px', 'important');
        card.style.setProperty('width', targetRect.width + 'px', 'important');
        card.style.setProperty('height', targetRect.height + 'px', 'important');
        
        document.getElementById('card-backdrop').classList.remove('active');
        
        // Cleanup after transition
        setTimeout(() => {
            card.style.position = '';
            card.style.top = '';
            card.style.left = '';
            card.style.width = '';
            card.style.height = '';
            card.style.zIndex = '';
            card.style.margin = '';
            
            if (placeholder && placeholder.parentNode) {
                placeholder.parentNode.removeChild(placeholder);
            }
            // Clear reference
            card._placeholder = null;
            
            unlockBodyScroll();
        }, 500); // Match CSS transition time
    });
}

// Tab switching
window.switchTab = function(btn, tabId) {
    const cardBack = btn.closest('.card-back');
    
    // Update buttons
    cardBack.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    // Update content
    cardBack.querySelectorAll('.user-list-panel').forEach(p => p.classList.remove('active'));
    const targetPanel = cardBack.querySelector(`.user-list-panel[data-tab-content="${tabId}"]`);
    if (targetPanel) {
        targetPanel.classList.add('active');
    }
};

// --- Extraordinary Modal Logic ---

// Create Modal HTML dynamically if not exists
if (!document.getElementById('sp-modal-overlay')) {
    const modalHTML = `
    <div id="sp-modal-overlay" class="sp-modal-overlay">
        <div class="sp-modal-content">
            <div class="sp-modal-header">
                <h3 id="sp-modal-title">Detalles del Test</h3>
                <button class="sp-modal-close" onclick="closeSpModal()">&times;</button>
            </div>
            <div id="sp-modal-body" class="sp-modal-body">
                <div style="text-align: center; padding: 40px;">
                    <i class="fa fa-spinner fa-spin fa-3x fa-fw" style="color: #667eea;"></i>
                    <span class="sr-only">Cargando...</span>
                </div>
            </div>
        </div>
    </div>`;
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

// Handle Chip Clicks
// Removed static binding in favor of delegation below
/*
document.querySelectorAll('.test-indicator, .test-list-item').forEach(chip => {
    chip.addEventListener('click', function(e) {
        e.stopPropagation(); // Prevent row click if any
        
        const userId = this.dataset.userid;
        const testType = this.dataset.test;
        const status = this.classList.contains('completed') ? 'completed' : 
                       (this.classList.contains('in-progress') ? 'in-progress' : 'not-started');
        
        if (status === 'not-started') return; // Do nothing for not started? Or show "Not started" message?
        
        openSpModal(userId, testType, status);
    });
});
*/

function openSpModal(userId, testType, status) {
    const overlay = document.getElementById('sp-modal-overlay');
    const body = document.getElementById('sp-modal-body');
    const title = document.getElementById('sp-modal-title');
    
    // Reset content
    body.innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <i class="fa fa-spinner fa-spin fa-3x fa-fw" style="color: #667eea;"></i>
            <p style="margin-top: 15px; color: #718096;">Cargando resultados extraordinarios...</p>
        </div>`;
    
    // Set title based on test type
    const titles = {
        'chaside': '<?php echo get_string('chaside_test', 'block_student_path'); ?>',
        'learning_style': '<?php echo get_string('learning_style_test', 'block_student_path'); ?>',
        'personality': '<?php echo get_string('personality_test', 'block_student_path'); ?>',
        'tmms24': '<?php echo get_string('tmms_24_test', 'block_student_path'); ?>',
        'student_path': '<?php echo get_string('student_profile', 'block_student_path'); ?>'
    };
    
    let titleText = titles[testType] || 'Detalles del Test';
    title.textContent = titleText;
    
    // Show modal
    overlay.classList.add('active');
    lockBodyScroll();
    
    // Fetch data
    fetch(`<?php echo $CFG->wwwroot; ?>/blocks/student_path/ajax_get_test_details.php?user_id=${userId}&test_type=${testType}&course_id=<?php echo $courseid; ?>&sesskey=<?php echo sesskey(); ?>`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' || data.status === 'in-progress') {
                body.innerHTML = data.html;
                // Run translation logic if applicable
                if (testType === 'student_path') {
                    runContentTranslator();
                }
            } else {
                body.innerHTML = `<div class="alert alert-danger">Error: ${data.message || 'No se pudieron cargar los datos.'}</div>`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            body.innerHTML = `<div class="alert alert-danger">Error de conexión. Por favor intente nuevamente.</div>`;
        });
}

function runContentTranslator() {
    if ('Translator' in self) {
        
        var getLang = function(lang) {
            return (lang || 'en').split('-')[0].split('_')[0].toLowerCase();
        };

        var targetLang = getLang(document.documentElement.lang);

        // Improved heuristic with scoring
        var guessLang = function(text, targetLang) {
            if (!text) return null;
            
            // 1. Strong signal: Spanish accents
            if (/[áéíóúñ¿¡ÁÉÍÓÚÑ]/.test(text)) return 'es';

            // 2. Tokenize and count stopwords
            var esWords = [
                'el','la','los','las','una','unos','unas','que','en','de','por','para','con','sin',
                'es','son','fue','era','este','esta','ese','esa','esto','eso','estos','estas',
                'nosotros','vosotros','ellos','ellas','usted','ustedes',
                'mi','mis','tu','tus','su','sus','nuestro','vuestro',
                'yo','pero','mas','porque','cuando','donde','quien','como','muy','mas',
                'bien','mal','y','o',
                'hola','gracias','adios','bueno','buena','dia','tarde','noche'
            ];
            
            var enWords = [
                'the','an','and','that','of','by','for','with','without',
                'is','are','was','were','be','been',
                'this','these','those','it','he','she','we','they','you',
                'my','your','his','her','its','our','their',
                'but','if','not','or','as','at','from','to','in','on',
                'when','where','who','how','why','what',
                'very','more','good','bad','hello','thanks','bye'
            ];

            var tokens = text.toLowerCase()
                .replace(/[.,/#!$%^&*;:{}=\-_`~()]/g,"")
                .split(/\s+/);
            
            var esScore = 0;
            var enScore = 0;

            for (var i = 0; i < tokens.length; i++) {
                var word = tokens[i];
                if (esWords.indexOf(word) !== -1) esScore++;
                if (enWords.indexOf(word) !== -1) enScore++;
            }

            // 3. Decision Logic
            if (esScore > 0 && esScore > enScore) return 'es';
            if (enScore > 0 && enScore > esScore) return 'en';
            
            // Tie or zero scores
            if (esScore === enScore && esScore > 0) {
                 if (targetLang === 'en') return 'es';
                 if (targetLang === 'es') return 'en';
            }

            return null;
        };

        // Select items in DOM
        var elements = document.querySelectorAll('.sp-scrollable-text');
        elements.forEach(async function(container) {
            // Avoid re-translating if this runs multiple times
            if (container.getAttribute('data-translated')) return;

            var originalText = container.textContent.trim();
            if (!originalText) return;

            var sourceLang = null;

            // 1. Try AI Detector
            if (self.ai && self.ai.languageDetector) {
                try {
                    var capabilities = await self.ai.languageDetector.capabilities();
                    if (capabilities.available !== 'no') {
                        var detector = await self.ai.languageDetector.create();
                        var results = await detector.detect(originalText);
                        if (results && results.length > 0) {
                            if (results[0].confidence > 0.4) {
                                sourceLang = results[0].detectedLanguage;
                            }
                        }
                    }
                } catch (e) {
                    // Fail silently
                }
            }

            // 2. Fallback Heuristic
            if (!sourceLang) {
                sourceLang = guessLang(originalText, targetLang);
            }

            // 3. Logic: If unknown or same as target, do nothing
            // Normalize
            if (sourceLang) sourceLang = getLang(sourceLang);
            
            if (!sourceLang || sourceLang === targetLang) {
                return;
            }

            // 4. Translate
            try {
                // Check availability
                var availability = await self.Translator.availability({
                    sourceLanguage: sourceLang,
                    targetLanguage: targetLang
                });
                
                if (availability === 'no') {
                    return;
                }

                // Show spinner - find closest title in parent
                var title = null;
                if (container.parentElement) {
                    title = container.parentElement.querySelector('h5, h6');
                }

                if (availability !== 'readily' && title) {
                    if (!title.querySelector('.translation-spinner')) {
                         var spinner = document.createElement('i');
                         spinner.className = 'fa fa-spinner fa-spin ml-2 text-muted translation-spinner';
                         title.appendChild(spinner);
                    }
                }

                var translator = await self.Translator.create({
                    sourceLanguage: sourceLang,
                    targetLanguage: targetLang
                });

                var translatedText = await translator.translate(originalText);

                if (title && title.querySelector('.translation-spinner')) {
                    title.querySelector('.translation-spinner').remove();
                }

                // Update text
                container.textContent = translatedText;
                
                var note = document.createElement('div');
                note.className = 'small text-muted mt-2 border-top pt-1 font-italic';
                note.innerHTML = '<i class="fa fa-language"></i> Traducido automáticamente (' + sourceLang + ' a ' + targetLang + ')';
                container.appendChild(note);
                
                container.setAttribute('data-translated', 'true');

            } catch (err) {
                console.error('Translation failed:', err);
                if (title && title.querySelector('.translation-spinner')) {
                     title.querySelector('.translation-spinner').remove();
                }
            }
        });
    }
}


window.closeSpModal = function() {
    const overlay = document.getElementById('sp-modal-overlay');
    overlay.classList.remove('active');
    unlockBodyScroll();
};

// Close on overlay click
document.getElementById('sp-modal-overlay').addEventListener('click', function(e) {
    if (e.target === this) {
        closeSpModal();
    }
});

// Close on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        // Priority 1: Close Modal
        const modalOverlay = document.getElementById('sp-modal-overlay');
        if (modalOverlay && modalOverlay.classList.contains('active')) {
            closeSpModal();
            return; // Stop propagation/execution
        }
        
        // Priority 2: Close Expanded Card
        const expandedCard = document.querySelector('.test-card.expanded');
        if (expandedCard) {
            closeCard(expandedCard);
        }
    }
});

// --- AJAX Pagination & Filtering Logic ---

function updatePerPage(val) {
    const fetchUrl = new URL(window.location.origin + window.location.pathname);
    fetchUrl.searchParams.set('cid', '<?php echo $courseid; ?>');
    fetchUrl.searchParams.set('page', 0); // Reset page on perpage change
    
    // Preserve filters
    const status = document.getElementById('filterStatus').value;
    if (status !== 'all') fetchUrl.searchParams.set('status', status);
    
    const search = document.getElementById('searchUsers').value;
    if (search) fetchUrl.searchParams.set('search', search);
    
    // Add perpage
    if (val != 50) fetchUrl.searchParams.set('perpage', val);
    
    loadTable(fetchUrl.toString());
}

function loadTable(url) {
    // Add loading state
    const wrapper = document.getElementById('users-table-wrapper');
    wrapper.style.opacity = '0.5';
    wrapper.style.pointerEvents = 'none';
    
    // Append ajax param
    const fetchUrl = new URL(url, window.location.origin);
    fetchUrl.searchParams.set('ajax_table', '1');
    fetchUrl.searchParams.set('sesskey', '<?php echo sesskey(); ?>');
    
    // Ensure current filter status is preserved if not already in URL
    const currentStatus = document.getElementById('filterStatus').value;
    if (!fetchUrl.searchParams.has('status')) {
        fetchUrl.searchParams.set('status', currentStatus);
    }
    
    // Ensure current search term is preserved
    const currentSearch = document.getElementById('searchUsers').value;
    if (!fetchUrl.searchParams.has('search') && currentSearch) {
        fetchUrl.searchParams.set('search', currentSearch);
    }
    
    // Ensure perpage is preserved from selector if exists
    const perPageSelector = document.getElementById('perPageSelector');
    if (perPageSelector && perPageSelector.value != 50) {
        fetchUrl.searchParams.set('perpage', perPageSelector.value);
    }
    
    fetch(fetchUrl)
        .then(response => response.text())
        .then(html => {
            wrapper.innerHTML = html;
            wrapper.style.opacity = '1';
            wrapper.style.pointerEvents = 'auto';
            
            // Re-bind pagination links
            bindPaginationLinks();
            updateExportLinks();
        })
        .catch(err => {
            console.error('Error loading table:', err);
            wrapper.style.opacity = '1';
            wrapper.style.pointerEvents = 'auto';
            alert('Error al cargar los datos. Por favor recargue la página.');
        });
}

function bindPaginationLinks() {
    // Moodle paging bar links usually have class 'page-link' or are inside '.pagination'
    // We target all links inside the wrapper
    const links = document.querySelectorAll('#users-table-wrapper .pagination a, #users-table-wrapper .paging a');
    
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            loadTable(this.href);
        });
    });
}

// Initial bind
document.addEventListener('DOMContentLoaded', function() {
    bindPaginationLinks();
});

// Search functionality (Server-side text filter on current page)
document.getElementById('searchUsers').addEventListener('input', function(e) {
    const searchTerm = e.target.value;
    
    // Clear existing timeout
    if (searchTimeout) clearTimeout(searchTimeout);
    
    // Debounce 300ms
    searchTimeout = setTimeout(() => {
        const fetchUrl = new URL(window.location.origin + window.location.pathname);
        fetchUrl.searchParams.set('cid', '<?php echo $courseid; ?>');
        fetchUrl.searchParams.set('search', searchTerm);
        fetchUrl.searchParams.set('page', 0); // Reset page
        fetchUrl.searchParams.set('status', document.getElementById('filterStatus').value);
        
        loadTable(fetchUrl.toString());
    }, 300);
});

// Filter functionality (Server-side reload)
document.getElementById('filterStatus').addEventListener('change', function(e) {
    const status = e.target.value;
    const fetchUrl = new URL(window.location.origin + window.location.pathname);
    fetchUrl.searchParams.set('cid', '<?php echo $courseid; ?>');
    fetchUrl.searchParams.set('status', status);
    
    // Preserve search term
    const searchTerm = document.getElementById('searchUsers').value;
    if (searchTerm) {
        fetchUrl.searchParams.set('search', searchTerm);
    }
    
    fetchUrl.searchParams.set('page', 0); // Reset to first page on filter change
    
    loadTable(fetchUrl.toString());
    updateExportLinks();
});

function updateExportLinks() {
    const statusFilter = document.getElementById('filterStatus').value;
    const searchTerm = document.getElementById('searchUsers').value;
    const exportLinks = document.querySelectorAll('.dropdown-menu .dropdown-item');
    
    exportLinks.forEach(link => {
        const url = new URL(link.href);
        url.searchParams.set('status', statusFilter);
        if (searchTerm) {
            url.searchParams.set('search', searchTerm);
        } else {
            url.searchParams.delete('search');
        }
        link.href = url.toString();
    });
}

// Handle Chip Clicks (Delegated for AJAX support)
document.addEventListener('click', function(e) {
    const chip = e.target.closest('.test-indicator, .test-list-item');
    if (chip) {
        // If inside the table wrapper (dynamic) or card back (static)
        e.stopPropagation(); 
        
        const userId = chip.dataset.userid;
        const testType = chip.dataset.test;
        
        // Determine status based on class
        let status = 'not-started';
        if (chip.classList.contains('completed')) status = 'completed';
        else if (chip.classList.contains('in-progress')) status = 'in-progress';
        
        if (status === 'not-started') return;
        
        openSpModal(userId, testType, status);
    }
});

</script>

<?php
echo $OUTPUT->footer();
?>
