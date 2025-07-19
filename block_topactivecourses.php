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
        global $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';

        $since = $this->get_since_timestamp();
        $records = $this->get_top_course_records($since);
        $filtered = $this->filter_courses($records, $USER);
        $topx = $this->get_topx_limit();

        $tiles = $this->render_course_tiles($filtered, $topx, $USER);

        if (empty($tiles)) {
            $this->content->text = get_string('nocourses', 'block_topactivecourses');
        } else {
            $this->content->text = html_writer::start_div('topactivecourses-tiles') . implode('', $tiles) . html_writer::end_div();
        }

        return $this->content;
    }

    /**
     * Calculates the timestamp for the configured number of days in the past.
     *
     * @return int Unix timestamp representing the cutoff time.
     */
    private function get_since_timestamp(): int {
        $days = get_config('block_topactivecourses', 'since_days');
        if (!$days || !is_numeric($days)) {
            $days = 7;
        }
        return time() - ($days * 24 * 60 * 60);
    }

    /**
     * Retrieves the number of top courses to display from the plugin settings.
     *
     * @return int Number of courses to show, defaults to 10 if not set or invalid.
     */
    private function get_topx_limit(): int {
        $topx = get_config('block_topactivecourses', 'topx');
        return ($topx && is_numeric($topx)) ? (int)$topx : 10;
    }

    /**
     * Retrieves the most active courses since the given timestamp based on log activity.
     *
     * @param int $since Unix timestamp to filter log entries.
     * @return array List of course activity records.
     */
    private function get_top_course_records(int $since): array {
        global $DB;

        $sql = "
        SELECT courseid, COUNT(DISTINCT userid) AS usercount, COUNT(*) AS logcount
        FROM {logstore_standard_log}
        WHERE timecreated > :since
          AND courseid > 1
          AND userid > 0
          AND component = 'core'
        GROUP BY courseid
        ORDER BY logcount DESC
    ";

        return $DB->get_records_sql($sql, ['since' => $since], 0, 50);
    }

    /**
     * Filters out courses that the user is already enrolled in or cannot self-enrol into.
     *
     * @param array $records List of course activity records.
     * @param stdClass $user The user to check enrolment against.
     * @return array Filtered list of courses the user can self-enrol into.
     */
    private function filter_courses(array $records, stdClass $user): array {
        $filtered = [];

        foreach ($records as $rec) {
            $course = get_course($rec->courseid);
            $context = context_course::instance($course->id);

            if (is_enrolled($context, $user)) {
                continue;
            }

            $enrols = enrol_get_instances($course->id, true);
            $selfenrol = false;

            foreach ($enrols as $enrol) {
                if ($enrol->enrol === 'self' && $enrol->status == ENROL_INSTANCE_ENABLED) {
                    $selfenrol = true;
                    break;
                }
            }

            if ($selfenrol) {
                $filtered[] = $rec;
            }
        }

        return $filtered;
    }

    /**
     * Renders the course tiles for display.
     *
     * @param array $records Filtered course activity records.
     * @param int $limit Maximum number of tiles to render.
     * @param stdClass $user The current user, used to check enrolments.
     * @return array Array of HTML strings representing course tiles.
     */
    private function render_course_tiles(array $records, int $limit, stdClass $user): array {
        global $OUTPUT;

        $tiles = [];
        $shown = 0;

        foreach ($records as $rec) {
            if ($shown >= $limit) {
                break;
            }

            $course = get_course($rec->courseid);
            $context = context_course::instance($course->id);

            if (is_enrolled($context, $user)) {
                continue;
            }

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

            $image = core_course\external\course_summary_exporter::get_course_image($course);
            if (!$image) {
                $image = 'https://picsum.photos/400/200?random=' . $course->id;
            }

            $url = new moodle_url('/course/view.php', ['id' => $course->id]);
            $title = format_string($course->fullname);

            $tiles[] = html_writer::start_div('topactivecourses-tile card')
                . html_writer::start_tag('a', ['href' => $url, 'class' => 'topactivecourses-link'])
                . html_writer::empty_tag('img', [
                    'src' => $image,
                    'class' => 'card-img-top topactivecourses-img',
                    'alt' => $title,
                ])
                . html_writer::start_div('card-body')
                . html_writer::tag('h5', $title, ['class' => 'card-title topactivecourses-title'])
                . html_writer::end_div()
                . html_writer::end_tag('a')
                . html_writer::end_div();

            $shown++;
        }

        return $tiles;
    }

    /**
     * Indicates whether the block has a configuration page.
     *
     * @return bool
     */
    public function has_config() {
        return true;
    }
}
