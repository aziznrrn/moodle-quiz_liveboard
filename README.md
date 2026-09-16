# Moodle Quiz Live Board

A Kahoot-style **live scoreboard** for Moodle quizzes, as a quiz report
subplugin. While students take a quiz, the teacher opens
**Results → Live Board**, projects it, and the board shows — per student — how
many questions they have answered and their current grade, sorted by score,
auto-refreshing via AJAX polling.

- No settings, no stored data (reads core quiz tables only; null privacy provider).
- Teacher-only (default `mod/quiz:viewreports` capability gate).
- Moodle 4.5 (LTS) → 5.2. UI in English and Indonesian.

Status: work in progress — see the wayfinder tracker in the sibling repo.
