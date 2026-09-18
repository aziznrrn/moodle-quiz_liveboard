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

declare(strict_types=1);

namespace quiz_liveboard;

use advanced_testcase;

/**
 * Tests board::get_rows(): answered counting for in-progress attempts,
 * grades for finished ones, per-attempt grade source, preview exclusion,
 * ordering.
 *
 * @package    quiz_liveboard
 * @copyright  2026 aziznrrn
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class board_test extends advanced_testcase {
    /**
     * Fixture: two-question quiz (shortanswer 'frog', numerical '3.14'),
     * full marks 2.0, quiz grade 100. No attempts yet.
     *
     * @return array [$quiz, $u1, $u2].
     */
    protected function setup_quiz_with_attempts(): array {
        $dg = $this->getDataGenerator();
        $quizgen = $dg->get_plugin_generator('mod_quiz');
        $course = $dg->create_course();
        $quiz = $quizgen->create_instance(['course' => $course->id, 'grade' => 100.0, 'sumgrades' => 2]);

        $qgen = $dg->get_plugin_generator('core_question');
        $cat = $qgen->create_question_category();
        $saq = $qgen->create_question('shortanswer', null, ['category' => $cat->id]);
        $numq = $qgen->create_question('numerical', null, ['category' => $cat->id]);
        quiz_add_quiz_question((int) $saq->id, $quiz);
        quiz_add_quiz_question((int) $numq->id, $quiz);

        return [$quiz, $dg->create_user(), $dg->create_user()];
    }

    /**
     * Start attempt $attemptno for $user, submit $answers (slot => response),
     * optionally finish and grade it. Returns the attempt record.
     *
     * @param object $quiz the quiz.
     * @param object $user the attempting user.
     * @param int $attemptno attempt number (1-based).
     * @param array $answers slot => response array for process_submitted_actions.
     * @param object|false $lastattempt previous attempt record, or false for the first.
     * @param bool $finish finish and grade the attempt.
     * @return object the quiz_attempts record.
     */
    protected function attempt_quiz(
        object $quiz,
        object $user,
        int $attemptno,
        array $answers,
        $lastattempt = false,
        bool $finish = true
    ): object {
        $quizobj = \mod_quiz\quiz_settings::create((int) $quiz->id, (int) $user->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $timenow = time();
        $attempt = quiz_create_attempt($quizobj, $attemptno, $lastattempt, $timenow, false, (int) $user->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, $attemptno, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        $attemptobj = \mod_quiz\quiz_attempt::create((int) $attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, $answers);
        if ($finish) {
            $attemptobj = \mod_quiz\quiz_attempt::create((int) $attempt->id);
            // 4.5 calls it process_finish; 5.x splits submit and grade.
            if (method_exists($attemptobj, 'process_submit')) {
                $attemptobj->process_submit($timenow, false);
                $attemptobj->process_grade_submission($timenow + 1);
            } else {
                $attemptobj->process_finish($timenow, false);
            }
        }
        return $attempt;
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
        $this->attempt_quiz($quiz, $u1, 1, [1 => ['answer' => 'frog'], 2 => ['answer' => '3.14']]);
        $this->attempt_quiz($quiz, $u2, 1, [1 => ['answer' => 'frog']], false, false);

        $rows = array_values(board::get_rows((int) $quiz->id));
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

    /**
     * The grade shown is the latest attempt's own grade (like the overview
     * report), not quiz_grades - which keeps the best attempt under the
     * default highest-grade method and would show a stale, higher score.
     *
     * @covers \quiz_liveboard\board::get_rows
     */
    public function test_grade_is_latest_attempt_not_best(): void {
        global $DB;

        $this->resetAfterTest(true);
        [$quiz, $u1] = $this->setup_quiz_with_attempts();
        $first = $this->attempt_quiz($quiz, $u1, 1, [1 => ['answer' => 'frog'], 2 => ['answer' => '3.14']]);
        $this->attempt_quiz($quiz, $u1, 2, [1 => ['answer' => 'toad'], 2 => ['answer' => '9.99']], $first);

        // The quiz_grades table still holds the best attempt (100) ...
        $this->assertEqualsWithDelta(
            100.0,
            (float) $DB->get_field('quiz_grades', 'grade', ['quiz' => $quiz->id, 'userid' => $u1->id]),
            0.0001
        );

        // ... but the board shows the latest attempt's own grade, like the overview report.
        $row = board::get_rows((int) $quiz->id)[$u1->id];
        $this->assertEquals('finished', $row->state);
        $sumgrades2 = (float) $DB->get_field(
            'quiz_attempts',
            'sumgrades',
            ['quiz' => $quiz->id, 'userid' => $u1->id, 'attempt' => 2]
        );
        $this->assertEqualsWithDelta(
            $sumgrades2 * 100.0 / 2.0,
            (float) $row->grade,
            0.0001
        );
        $this->assertLessThan(100.0, (float) $row->grade);
    }

    /**
     * Preview attempts are excluded like the overview report: a user whose
     * only attempt is a preview does not appear, and a newer preview must
     * not shadow an earlier real attempt (the teacher log-in-as case).
     *
     * @covers \quiz_liveboard\board::get_rows
     */
    public function test_preview_attempts_excluded(): void {
        global $DB;

        $this->resetAfterTest(true);
        [$quiz, $u1, $u2] = $this->setup_quiz_with_attempts();
        $first = $this->attempt_quiz($quiz, $u1, 1, [1 => ['answer' => 'frog'], 2 => ['answer' => '3.14']]);
        $preview = $this->attempt_quiz($quiz, $u1, 2, [1 => ['answer' => 'toad']], $first, false);
        $u2attempt = $this->attempt_quiz($quiz, $u2, 1, [1 => ['answer' => 'frog']], false, false);
        $DB->set_field('quiz_attempts', 'preview', 1, ['id' => $preview->id]);
        $DB->set_field('quiz_attempts', 'preview', 1, ['id' => $u2attempt->id]);

        // Only u1's real attempt counts: he shows finished with its grade,
        // and u2 (preview-only) is absent.
        $rows = board::get_rows((int) $quiz->id);
        $this->assertCount(1, $rows);
        $this->assertEquals('finished', $rows[$u1->id]->state);
        $this->assertEqualsWithDelta(100.0, (float) $rows[$u1->id]->grade, 0.0001);
    }
}
