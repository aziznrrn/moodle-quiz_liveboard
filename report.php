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
 * Live Board quiz report - scaffold stub; the scoreboard lands in the
 * implementation tickets (see wayfinder T05/T02).
 *
 * @package    quiz_liveboard
 * @copyright  2026 aziznrrn
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Live Board report class.
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
        global $OUTPUT;
        echo $OUTPUT->heading(get_string('pluginname', 'quiz_liveboard'));
        echo $OUTPUT->notification(get_string('placeholder', 'quiz_liveboard'), 'notifymessage');
    }
}
