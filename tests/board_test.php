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
 * Unit tests for the board data query.
 *
 * @package    quiz_liveboard
 * @copyright  2026 aziznrrn
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quiz_liveboard;

use advanced_testcase;

/**
 * Tests board::get_rows(): answered counting for in-progress attempts,
 * grades for finished ones, ordering.
 *
 * @package    quiz_liveboard
 * @copyright  2026 aziznrrn
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class board_test extends advanced_testcase {
    /**
     * Fixture: two-question quiz, one finished student, one halfway in-progress.
     *
     * @return array [$quiz, $u1, $u2].
     */
    protected function setup_quiz_with_attempts(): array {
        global $DB;

        $dg = $this->getDataGenerator();
        $quizgen = $dg->get_plugin_generator('mod_quiz');
        $course = $dg->create_course();
        $quiz = $quizgen->create_instance(['course' => $course->id, 'grade' => 100.0, 'sumgrades' => 2]);

        $qgen = $dg->get_plugin_generator('core_question');
        $cat = $qgen->create_question_category();
        $saq = $qgen->create_question('shortanswer', null, ['category' => $cat->id]);
        $numq = $qgen->create_question('numerical', null, ['category' => $cat->id]);
        quiz_add_quiz_question($saq->id, $quiz);
        quiz_add_quiz_question($numq->id, $quiz);

        $u1 = $dg->create_user();
        $u2 = $dg->create_user();
        $timenow = time();

        // User 1: answer both, submit, grade -> finished with full marks.
        $quizobj = \mod_quiz\quiz_settings::create($quiz->id, $u1->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $attempt = quiz_create_attempt($quizobj, 1, false, $timenow, false, $u1->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        $attemptobj = \mod_quiz\quiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, [1 => ['answer' => 'frog'], 2 => ['answer' => '3.14']]);
        $attemptobj = \mod_quiz\quiz_attempt::create($attempt->id);
        // 4.5 calls it process_finish; 5.x renamed it to process_submit.
        if (method_exists($attemptobj, 'process_submit')) {
            $attemptobj->process_submit($timenow, false);
            $attemptobj->process_grade_submission($timenow + 1);
        } else {
            // 4.5: process_finish grades inline.
            $attemptobj->process_finish($timenow, false);
        }

        // User 2: start, answer one, stay in progress.
        $quizobj = \mod_quiz\quiz_settings::create($quiz->id, $u2->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $attempt = quiz_create_attempt($quizobj, 1, false, $timenow, false, $u2->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        $attemptobj = \mod_quiz\quiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, [1 => ['answer' => 'frog']]);

        return [$quiz, $u1, $u2];
    }

    /**
     * Finished student shows grade + full answered count; in-progress shows
     * partial count with null grade; ordering puts the leader first.
     *
     * @covers \quiz_liveboard\board::get_rows
     */
    public function test_rows(): void {
        $this->resetAfterTest(true);
        [$quiz, $u1, $u2] = $this->setup_quiz_with_attempts();

        $rows = array_values(board::get_rows($quiz->id));
        $this->assertCount(2, $rows);

        // Leader first (finished, graded).
        $this->assertEquals($u1->id, $rows[0]->userid);
        $this->assertEquals('finished', $rows[0]->state);
        $this->assertEquals(2, $rows[0]->answered);
        $this->assertEquals(2, $rows[0]->slots);
        $this->assertEqualsWithDelta(100.0, $rows[0]->grade, 0.0001);

        // In-progress student: partial answered, no grade yet.
        $this->assertEquals($u2->id, $rows[1]->userid);
        $this->assertEquals('inprogress', $rows[1]->state);
        $this->assertEquals(1, $rows[1]->answered);
        $this->assertNull($rows[1]->grade);
    }
}
