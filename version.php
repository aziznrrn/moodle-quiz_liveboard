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
 * Version definition.
 *
 * @package    quiz_liveboard
 * @copyright  2026 aziznrrn
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'quiz_liveboard';
$plugin->version = 2026091700;
$plugin->requires = 2024100100; // Moodle 4.5+.
$plugin->release = 'v0.1.0';
$plugin->maturity = MATURITY_ALPHA;
