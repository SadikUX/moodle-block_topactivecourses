<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Settings for block_topactivecourses
 *
 * @package    block_topactivecourses
 * @copyright  2025 Sadik Mert <sadikmert@hotmail.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading(
        'block_topactivecourses/intro',
        '',
        get_string('topactivecourses_intro', 'block_topactivecourses')
    ));

    // Top X courses.
    $settings->add(new admin_setting_configtext(
        'block_topactivecourses/topx',
        get_string('topx', 'block_topactivecourses'),
        get_string('topx_desc', 'block_topactivecourses'),
        10, // Default value 10.
        PARAM_INT
    ));

    // Time range in days.
    $settings->add(new admin_setting_configtext(
        'block_topactivecourses/since_days',
        get_string('since_days', 'block_topactivecourses'),
        get_string('since_days_desc', 'block_topactivecourses'),
        7, // Default value 7 days.
        PARAM_INT
    ));
}
