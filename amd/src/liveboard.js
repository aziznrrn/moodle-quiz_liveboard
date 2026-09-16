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
 * Live Board front end: poll ajax.php every few seconds and redraw.
 *
 * @module quiz_liveboard/liveboard
 * @copyright 2026 aziznrrn
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {

    var cmid = null;
    var strings = {};
    var firstDraw = true;

    /**
     * HTML-escape a value (names are user data).
     *
     * @param {String} s raw text.
     * @return {String} escaped text.
     */
    function esc(s) {
        return $('<div>').text(String(s)).html();
    }

    /**
     * Build one ranked row.
     *
     * @param {Number} rank 1-based position.
     * @param {Object} r row from ajax.php.
     * @param {Number} quizgrade max quiz grade.
     * @return {String} HTML.
     */
    function rowHtml(rank, r, quizgrade) {
        var finished = r.state === 'finished' && r.grade !== null;
        var score = finished ? (Math.round(r.grade * 10) / 10) : '-';
        var barPct = finished && quizgrade > 0
            ? Math.min(100, Math.round(r.grade / quizgrade * 100))
            : (r.slots > 0 ? Math.round(r.answered / r.slots * 100) : 0);
        var barClass = finished ? 'qlb-scorebar' : 'qlb-progressbar';
        var posClass = rank <= 3 ? ' qlb-top' + rank : '';

        return '<div class="qlb-row' + posClass + '">'
            + '<div class="qlb-rank">' + rank + '</div>'
            + '<div class="qlb-main">'
            + '<div class="qlb-name">' + esc(r.name) + '</div>'
            + '<div class="qlb-bar"><div class="' + barClass + '" style="width:' + barPct + '%"></div></div>'
            + '</div>'
            + '<div class="qlb-meta">'
            + '<span class="qlb-answered" title="' + esc(strings.answered) + '">' + r.answered + '/' + r.slots + '</span>'
            + '<span class="qlb-score">' + score + '</span>'
            + '</div>'
            + '</div>';
    }

    /**
     * Redraw the whole board from poll data.
     *
     * @param {Object} data {quizgrade, rows}.
     */
    function render(data) {
        var root = $('.quiz-liveboard');
        if (firstDraw) {
            root.html('<div class="qlb-header">'
                + '<span class="qlb-live"><span class="qlb-dot"></span>' + esc(strings.live) + '</span>'
                + '<span class="qlb-count"></span>'
                + '<button type="button" class="qlb-btn btn btn-outline-light btn-sm">' + esc(strings.fullscreen) + '</button>'
                + '</div><div class="qlb-rows"></div>');
            root.find('.qlb-btn').on('click', function() {
                if (document.fullscreenElement) {
                    document.exitFullscreen();
                } else {
                    root[0].requestFullscreen();
                }
            });
            firstDraw = false;
        }

        var finishedCount = data.rows.filter(function(r) {
            return r.state === 'finished';
        }).length;

        root.find('.qlb-count').text(data.rows.length + ' / ' + finishedCount);
        var out = data.rows.length === 0
            ? '<div class="qlb-waiting">' + esc(strings.waiting) + '</div>'
            : data.rows.map(function(r, i) {
                return rowHtml(i + 1, r, data.quizgrade);
            }).join('');
        root.find('.qlb-rows').html(out);
    }

    /**
     * One poll round.
     */
    function poll() {
        $.getJSON(M.cfg.wwwroot + '/mod/quiz/report/liveboard/ajax.php', {id: cmid})
            .done(render)
            .fail(function() { /* transient: keep the last board on screen */ });
    }

    /**
     * Initialise polling.
     *
     * @param {Number} courseModuleId the quiz course module id.
     * @param {Number} quizgrade max quiz grade (informational; poll returns it too).
     * @param {Object} lang UI strings.
     */
    function init(courseModuleId, quizgrade, lang) {
        cmid = courseModuleId;
        strings = lang;
        poll();
        setInterval(poll, 5000);
    }

    return {
        init: init
    };
});
