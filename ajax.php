<?php
// This file is part of the Live Board plugin for Moodle.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Polling endpoint: JSON board rows for one quiz.
 *
 * @package    quiz_liveboard
 * @copyright  2026 aziznrrn
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');

$id = required_param('id', PARAM_INT); // Course module id.

[$course, $cm] = get_course_and_cm_from_cmid($id, 'quiz');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/quiz:viewreports', $context);

$quizobj = \mod_quiz\quiz_settings::create((int) $cm->instance);
$quiz = $quizobj->get_quiz();

echo json_encode([
    'quizgrade' => (float) $quiz->grade,
    'rows' => array_values(array_map(fn($r) => [
        'userid' => $r->userid,
        'name' => format_string($r->name),
        'state' => $r->state,
        'answered' => (int) $r->answered,
        'slots' => (int) $r->slots,
        'grade' => $r->grade === null ? null : (float) $r->grade,
    ], \quiz_liveboard\board::get_rows((int) $cm->instance))),
]);
