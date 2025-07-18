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
 * Block Top Active Courses main class.
 *
 * Displays the most active courses from the last 7 days in which the user is not enrolled,
 * but self-enrolment is possible.
 *
 * @package   block_topactivecourses
 * @copyright 2025 Sadik Mert <sadikmert@hotmail.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_topactivecourses extends block_base {

    /**
     * Initializes the block title.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_topactivecourses');
    }

    /**
     * Returns the block content: Shows the most active courses from the last 7 days
     * in which the user is not enrolled, but self-enrolment is possible.
     *
     * @return stdClass
     */
    public function get_content() {
        global $USER, $DB, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';

        $since = time() - (7 * 24 * 60 * 60); // Last 7 days.

        // Get top courses by user activity.
        $sql = "
            SELECT courseid, COUNT(DISTINCT userid) AS usercount
            FROM {logstore_standard_log}
            WHERE timecreated > :since
              AND courseid > 1
              AND userid > 0
            GROUP BY courseid
            ORDER BY usercount DESC
        ";

        $params = ['since' => $since];
        $records = $DB->get_records_sql($sql, $params, 0, 50);

        $shown = 0;
        $tiles = [];

        foreach ($records as $rec) {
            $course = get_course($rec->courseid);
            $context = context_course::instance($course->id);

            // Skip if user is already enrolled.
            if (is_enrolled($context, $USER)) {
                continue;
            }

            // Check if self-enrolment is enabled.
            $enrols = enrol_get_instances($course->id, true);
            $selfenrol = false;
            foreach ($enrols as $enrol) {
                if ($enrol->enrol === 'self' && $enrol->status == ENROL_INSTANCE_ENABLED) {
                    $selfenrol = true;
                    break;
                }
            }
            if (!$selfenrol) {
                continue;
            }

            // Get course image.
            $overviewfiles = core_course\external\course_summary_exporter::get_course_image($course);
            $courseimage = $overviewfiles ?: $OUTPUT->image_url('noimage', 'theme')->out();

            $url = new moodle_url('/course/view.php', ['id' => $course->id]);
            $title = format_string($course->fullname);

            $tiles[] = html_writer::start_div('topactivecourses-tile card')
                . html_writer::start_tag('a', ['href' => $url, 'class' => 'topactivecourses-link'])
                . html_writer::empty_tag('img', [
                    'src' => $courseimage,
                    'class' => 'card-img-top topactivecourses-img',
                    'alt' => $title,
                ])
                . html_writer::start_div('card-body')
                . html_writer::tag('h5', $title, ['class' => 'card-title topactivecourses-title'])
                . html_writer::end_div()
                . html_writer::end_tag('a')
                . html_writer::end_div();

            $shown++;
            if ($shown >= 10) {
                break;
            }
        }

        if ($shown === 0) {
            $this->content->text = get_string('nocourses', 'block_topactivecourses');
        } else {
            $this->content->text = html_writer::start_div('topactivecourses-tiles') . implode('', $tiles) . html_writer::end_div();
        }

        return $this->content;
    }

    /**
     * Indicates whether the block has a configuration page.
     *
     * @return bool
     */
    public function has_config() {
        return false;
    }
}
