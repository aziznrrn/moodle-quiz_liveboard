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
 * Board data: one aggregate query per poll for the whole cohort.
 *
 * @package    quiz_liveboard
 * @copyright  2026 aziznrrn
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quiz_liveboard;

/**
 * Reads core quiz tables; stores nothing.
 *
 * ponytail: correlated latest-step subquery is O(attempts x questions x steps)
 * scoped to one quiz - single-digit ms for a classroom, ~3.7s measured at the
 * absurd 637-user/9399-attempt extreme. If a school ever runs 600-user quizzes
 * on this board, swap for a latest-step derived-table join plus an index on
 * question_attempt_steps(questionattemptid, sequencenumber).
 *
 * @package    quiz_liveboard
 * @copyright  2026 aziznrrn
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class board {
    /**
     * Per-student board rows: latest attempt's answered count + current grade.
     *
     * Students are included once they have an inprogress or finished attempt;
     * in-progress attempts contribute their answered count, finished ones their
     * quiz_grades grade (null while ungraded). Sorted: grade desc, answered desc.
     *
     * @param int $quizid the quiz id.
     * @return array list of objects: userid, name, state, timefinish, answered, slots, grade.
     */
    public static function get_rows(int $quizid): array {
        global $DB;

        list($answeredsql, $eparams) = $DB->get_in_or_equal(
            ['complete', 'gradedright', 'gradedwrong', 'gradedpartial',
             'mangrright', 'mangrwrong', 'mangrpartial'],
            SQL_PARAMS_NAMED, 'ast');

        $sql = "SELECT u.id AS userid,
                       CONCAT(u.firstname, ' ', u.lastname) AS name,
                       la.state, la.timefinish,
                       (SELECT COUNT(DISTINCT qta.slot)
                          FROM {question_attempts} qta
                          JOIN {question_attempt_steps} qas
                            ON qas.questionattemptid = qta.id
                           AND qas.sequencenumber = (SELECT MAX(x.sequencenumber)
                                                        FROM {question_attempt_steps} x
                                                       WHERE x.questionattemptid = qta.id)
                         WHERE qta.questionusageid = la.uniqueid
                           AND qas.state $answeredsql) AS answered,
                       COUNT(qta0.slot) AS slots,
                       qg.grade
                  FROM {quiz_attempts} la
                  JOIN (SELECT userid, MAX(uniqueid) mu
                          FROM {quiz_attempts}
                         WHERE quiz = :quizid
                           AND state IN ('inprogress','finished')
                      GROUP BY userid) t
                    ON t.userid = la.userid AND t.mu = la.uniqueid
                  JOIN {user} u ON u.id = la.userid
             LEFT JOIN {quiz_grades} qg ON qg.quiz = la.quiz AND qg.userid = la.userid
             LEFT JOIN {question_attempts} qta0 ON qta0.questionusageid = la.uniqueid
              GROUP BY u.id, u.firstname, u.lastname, la.state, la.timefinish, la.uniqueid, qg.grade
              ORDER BY qg.grade IS NULL ASC, qg.grade DESC, answered DESC, u.lastname, u.firstname";

        return $DB->get_records_sql($sql, array_merge(['quizid' => $quizid], $eparams));
    }
}
