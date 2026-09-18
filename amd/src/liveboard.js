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
 * Live Board front end: poll ajax.php every second and patch the board in place.
 *
 * @module quiz_liveboard/liveboard
 * @copyright 2026 aziznrrn
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {

    var cmid = null;
    var strings = {};
    var firstDraw = true;
    var rowsById = {};

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
     * Build a row skeleton for one user; values are filled in by updateRow.
     *
     * @param {Object} r row from ajax.php.
     * @return {jQuery} row element.
     */
    function buildRow(r) {
        return $('<div class="qlb-row" data-userid="' + r.userid + '">'
            + '<div class="qlb-rank"></div>'
            + '<div class="qlb-main">'
            + '<div class="qlb-name">' + esc(r.name) + '</div>'
            + '<div class="qlb-bar"><div class="qlb-progressbar" style="width:0"></div></div>'
            + '</div>'
            + '<div class="qlb-meta">'
            + '<span class="qlb-answered" title="' + esc(strings.answered) + '"></span>'
            + '<span class="qlb-score"></span>'
            + '</div>'
            + '</div>');
    }

    /**
     * Update an existing row in place so CSS transitions animate the change.
     *
     * @param {jQuery} $row the row element.
     * @param {Number} rank 1-based position.
     * @param {Object} r row from ajax.php.
     * @param {Number} quizgrade max quiz grade.
     */
    function updateRow($row, rank, r, quizgrade) {
        var finished = r.state === 'finished' && r.grade !== null;
        var score = finished ? (Math.round(r.grade * 10) / 10) : '-';
        var barPct = finished && quizgrade > 0
            ? Math.min(100, Math.round(r.grade / quizgrade * 100))
            : (r.slots > 0 ? Math.round(r.answered / r.slots * 100) : 0);

        $row.attr('class', 'qlb-row' + (rank <= 3 ? ' qlb-top' + rank : ''));
        $row.find('.qlb-rank').text(rank);
        setText($row.find('.qlb-answered'), r.answered + '/' + r.slots);
        setText($row.find('.qlb-score'), score);
        $row.find('.qlb-bar > div')
            .attr('class', finished ? 'qlb-scorebar' : 'qlb-progressbar')
            .css('width', barPct + '%');
    }

    /**
     * Set text and bump-animate when the value actually changed.
     *
     * @param {jQuery} $el target element.
     * @param {String} text new text.
     */
    function setText($el, text) {
        if ($el.text() === String(text)) {
            return;
        }
        $el.text(text).removeClass('qlb-bump');
        // Force restart when the same element changes twice in quick succession.
        void $el[0].offsetWidth;
        $el.addClass('qlb-bump');
    }

    /**
     * Redraw the whole board from poll data.
     *
     * @param {Object} data {quizgrade, rows}.
     */
    function render(data) {
        var root = $('.quiz-liveboard');
        if (data.rows.length > 0) {
            $('.qlb-hint').hide();
        }
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

        var finishedCount = 0;
        var i, r, key;
        var $container = root.find('.qlb-rows');

        if (data.rows.length === 0) {
            rowsById = {};
            root.find('.qlb-count').text('0 / 0');
            $container.html('<div class="qlb-waiting">' + esc(strings.waiting) + '</div>');
            return;
        }

        // Only reorder the DOM (and FLIP-animate) when the ranking actually changed;
        // re-inserting rows every poll would restart their bar transitions.
        var $kids = $container.children('.qlb-row');
        var needsMove = $kids.length !== data.rows.length;
        if (!needsMove) {
            $kids.each(function(i) {
                if (String($(this).data('userid')) !== String(data.rows[i].userid)) {
                    needsMove = true;
                }
            });
        }
        var tops = {};
        if (needsMove) {
            $kids.each(function() {
                tops[String($(this).data('userid'))] = this.getBoundingClientRect().top;
            });
        }

        var current = {};
        for (i = 0; i < data.rows.length; i++) {
            r = data.rows[i];
            if (r.state === 'finished') {
                finishedCount++;
            }
            key = String(r.userid);
            if (!rowsById[key]) {
                var $new = buildRow(r).addClass('qlb-enter');
                // Strip after it plays: re-appending rows on a later rank swap would replay it.
                setTimeout(function() {
                    $new.removeClass('qlb-enter');
                }, 600);
                rowsById[key] = $new;
            }
            updateRow(rowsById[key], i + 1, r, data.quizgrade);
            if (needsMove) {
                $container.append(rowsById[key]);
            }
            current[key] = true;
        }

        // Drop rows for users who left the board.
        for (key in rowsById) {
            if (!current[key]) {
                rowsById[key].remove();
                delete rowsById[key];
            }
        }
        $container.children('.qlb-waiting').remove();

        if (needsMove) {
            // FLIP: place moved rows at their old position, then transition to 0.
            $container.children('.qlb-row').each(function() {
                key = String($(this).data('userid'));
                if (!(key in tops)) {
                    return; // New row: the qlb-enter animation covers it.
                }
                var dy = tops[key] - this.getBoundingClientRect().top;
                if (dy) {
                    $(this).css({transition: 'none', transform: 'translateY(' + dy + 'px)'});
                    void this.offsetWidth; // Commit the start position before animating.
                    $(this).css({transition: '', transform: ''});
                }
            });
        }

        root.find('.qlb-count').text(data.rows.length + ' / ' + finishedCount);
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
        setInterval(poll, 1000);
    }

    return {
        init: init
    };
});
