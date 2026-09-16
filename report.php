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

declare(strict_types=1);

/**
 * Live Board quiz report: a Kahoot-style projected scoreboard while students
 * take the quiz.
 *
 * @package    quiz_liveboard
 * @copyright  2026 aziznrrn
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Live Board report class.
 *
 * Renders the board shell; the AMD module polls ajax.php and redraws.
 *
 * @package    quiz_liveboard
 * @copyright  2026 aziznrrn
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_liveboard_report {
    /**
     * Render the report.
     *
     * @param object $quiz the quiz object.
     * @param object $cm the course-module object.
     * @param object $course the course object.
     * @return void (echoes output).
     */
    public function display($quiz, $cm, $course): void {
        global $OUTPUT, $PAGE;

        // The dispatcher does not print any header; each report prints its own
        // (see \mod_quiz\local\reports\report_base::print_header_and_tabs()).
        $PAGE->set_title(format_string($quiz->name));
        $PAGE->set_heading(format_string($course->fullname));
        echo $OUTPUT->header();

        echo $OUTPUT->heading(get_string('liveboard', 'quiz_liveboard'));
        echo $OUTPUT->notification(get_string('placeholder', 'quiz_liveboard'), 'notifymessage');
        echo \html_writer::div('', 'quiz-liveboard', ['data-cmid' => $cm->id]);

        $PAGE->requires->js_call_amd('quiz_liveboard/liveboard', 'init', [
            (int) $cm->id,
            (float) $quiz->grade,
            [
                'answered' => get_string('answered', 'quiz_liveboard'),
                'fullscreen' => get_string('fullscreen', 'quiz_liveboard'),
                'live' => get_string('live', 'quiz_liveboard'),
                'score' => get_string('score', 'quiz_liveboard'),
                'waiting' => get_string('waiting', 'quiz_liveboard'),
            ],
        ]);
    }
}
