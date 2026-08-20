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
 * Displays the most visited courses from the last 7 days in which the user is not enrolled,
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
     * Returns the block content: Shows the most visited courses from the last 7 days
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

        $cache = cache::make('block_topactivecourses', 'topcourses');
        $since = $this->get_since_timestamp();
        $cachekey = $this->get_cache_key();
        $records = $cache->get($cachekey);

        if ($records === false) {
            $records = $this->get_top_visited_course_records($since);
            $cache->set($cachekey, $records);
        }

        $filtered = $this->filter_courses($records, $USER);
        $topx = $this->get_topx_limit();

        $tiles = $this->render_course_tiles($filtered, $topx, $USER);

        if (empty($tiles)) {
            $this->content->text = get_string('nocourses', 'block_topactivecourses');
        } else {
            $this->content->text = implode('', $tiles);
        }

        return $this->content;
    }

    /**
     * Calculates the timestamp for the configured number of days in the past.
     *
     * @return int Unix timestamp representing the cutoff time.
     */
    private function get_since_timestamp(): int {
        $days = $this->get_since_days();
        return time() - ($days * 24 * 60 * 60);
    }

    /**
     * Retrieves the configured number of days to look back.
     *
     * @return int Number of days, defaults to 7 if not set or invalid.
     */
    private function get_since_days(): int {
        $days = get_config('block_topactivecourses', 'since_days');
        if (!$days || !is_numeric($days)) {
            $days = 7;
        }
        return max(1, (int)$days);
    }

    /**
     * Returns the cache key for the current aggregation settings.
     *
     * The key must not include the exact timestamp, otherwise it changes every request.
     *
     * @return string Cache key.
     */
    private function get_cache_key(): string {
        return 'topcourses_d' . $this->get_since_days()
            . '_t' . $this->get_topx_limit()
            . '_i' . (int)$this->should_ignore_enrolment_methods();
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
     * Retrieves the number of aggregated records to keep before per-user filtering.
     *
     * @return int Number of records.
     */
    private function get_record_pool_limit(): int {
        return max(50, $this->get_topx_limit() * 5);
    }

    /**
     * Retrieves the most visited courses since the given timestamp.
     *
     * @param int $since Unix timestamp to filter course visits.
     * @return array List of course visit records.
     */
    private function get_top_visited_course_records(int $since): array {
        global $DB;

        $params = [
            'since' => $since,
        ];

        $coursefilter = '';
        if (!$this->should_ignore_enrolment_methods()) {
            $coursefilter = "
           AND EXISTS (
                   SELECT 1
                     FROM {enrol} e
                    WHERE e.courseid = ula.courseid
                      AND e.enrol = :enrol
                      AND e.status = :enrolstatus
               )";
            $params['enrol'] = 'self';
            $params['enrolstatus'] = ENROL_INSTANCE_ENABLED;
        }

        $sql = "
        SELECT ula.courseid,
               COUNT(DISTINCT ula.userid) AS usercount,
               MAX(ula.timeaccess) AS lastaccess
          FROM {user_lastaccess} ula
          JOIN {course} c ON c.id = ula.courseid
         WHERE ula.timeaccess > :since
           AND ula.courseid > 1
           AND ula.userid > 0
           AND c.visible = 1
               $coursefilter
      GROUP BY ula.courseid
        ORDER BY usercount DESC, lastaccess DESC
    ";

        try {
            return $DB->get_records_sql($sql, $params, 0, $this->get_record_pool_limit());
        } catch (dml_exception $e) {
            debugging('Top active courses visit aggregation failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }

    /**
     * Whether enrolment method checks should be ignored.
     *
     * @return bool
     */
    private function should_ignore_enrolment_methods(): bool {
        return (bool)get_config('block_topactivecourses', 'ignore_enrolment_methods');
    }

    /**
     * Filters out courses that the user is already enrolled in or cannot self-enrol into.
     *
     * @param array $records List of course visit records.
     * @param stdClass $user The user to check enrolment against.
     * @return array Filtered list of courses the user can self-enrol into.
     */
    private function filter_courses(array $records, stdClass $user): array {
        $filtered = [];
        $ignoreenrol = get_config('block_topactivecourses', 'ignore_enrolment_methods');

        foreach ($records as $rec) {
            if ($this->should_include_course($rec, $user, $ignoreenrol)) {
                $filtered[] = $rec;
            }
        }

        return $filtered;
    }

    /**
     * Determines if a course should be included in the filtered list.
     *
     * @param stdClass $rec Course record.
     * @param stdClass $user Current user.
     * @param bool $ignoreenrol Whether to ignore enrolment method checks.
     * @return bool True if course should be included.
     */
    private function should_include_course(stdClass $rec, stdClass $user, bool $ignoreenrol): bool {
        $course = get_course($rec->courseid);
        $context = context_course::instance($course->id);

        if (is_enrolled($context, $user)) {
            return false;
        }

        if ($ignoreenrol) {
            return true;
        }

        return $this->can_self_enrol_in_course($course->id);
    }

    /**
     * Checks if self-enrolment is possible for a course.
     *
     * @param int $courseid Course ID.
     * @return bool True if self-enrolment is possible.
     */
    private function can_self_enrol_in_course(int $courseid): bool {
        $enrols = enrol_get_instances($courseid, true);

        foreach ($enrols as $enrol) {
            if ($enrol->status != ENROL_INSTANCE_ENABLED) {
                continue;
            }
            $plugin = enrol_get_plugin($enrol->enrol);
            if ($plugin && method_exists($plugin, 'can_self_enrol')) {
                $result = $plugin->can_self_enrol($enrol);
                if ($result === true) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Renders the course tiles for display.
     *
     * @param array $records Filtered course visit records.
     * @param int $limit Maximum number of tiles to render.
     * @param stdClass $user The current user, used to check enrolments.
     * @return array Array of HTML strings representing course tiles.
     */
    private function render_course_tiles(array $records, int $limit, stdClass $user): array {
        global $OUTPUT;

        $tilesdata = [];
        $shown = 0;

        foreach ($records as $rec) {
            if ($shown >= $limit) {
                break;
            }

            $coursedata = $this->prepare_course_data($rec, $user);
            if ($coursedata === null) {
                continue;
            }

            $tilesdata[] = $coursedata;
            $shown++;
        }

        if (empty($tilesdata)) {
            return [];
        }

        $html = $OUTPUT->render_from_template('block_topactivecourses/course_tiles', ['courses' => $tilesdata]);
        return [$html];
    }

    /**
     * Prepares course data for tile rendering.
     *
     * @param stdClass $rec Course record.
     * @param stdClass $user Current user.
     * @return array|null Course data array or null if course should be skipped.
     */
    private function prepare_course_data(stdClass $rec, stdClass $user): ?array {
        $course = get_course($rec->courseid);
        $context = context_course::instance($course->id);

        if (is_enrolled($context, $user)) {
            return null;
        }

        if (!$this->can_user_access_course($course)) {
            return null;
        }

        return [
            'url' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'image' => $this->get_course_image($course),
            'title' => format_string($course->fullname),
            'summary' => $this->format_course_summary($course),
            'visitorlabel' => get_string('participants', 'block_topactivecourses', (int)$rec->usercount),
            'categoryname' => $this->get_course_category_name($course),
            'tags' => $this->get_course_tags($course->id, $this->get_max_tags_limit()),
        ];
    }

    /**
     * Formats and shortens a course summary for compact tiles.
     *
     * @param stdClass $course Course object.
     * @return string Plain summary text.
     */
    private function format_course_summary(stdClass $course): string {
        if (empty($course->summary)) {
            return '';
        }

        $context = context_course::instance($course->id);
        $summary = format_text($course->summary, $course->summaryformat, [
            'context' => $context,
            'overflowdiv' => false,
        ]);

        return shorten_text(trim(strip_tags($summary)), 120);
    }

    /**
     * Returns the display name of the course category.
     *
     * @param stdClass $course Course object.
     * @return string Category name.
     */
    private function get_course_category_name(stdClass $course): string {
        $category = core_course_category::get($course->category, IGNORE_MISSING, true);
        return $category ? format_string($category->name) : '';
    }

    /**
     * Checks if user can access the course based on enrolment settings.
     *
     * @param stdClass $course Course object.
     * @return bool True if user can access.
     */
    private function can_user_access_course(stdClass $course): bool {
        $ignoreenrol = get_config('block_topactivecourses', 'ignore_enrolment_methods');

        if ($ignoreenrol) {
            return true;
        }

        $enrols = enrol_get_instances($course->id, true);
        foreach ($enrols as $enrol) {
            if ($enrol->enrol === 'self' && $enrol->status == ENROL_INSTANCE_ENABLED) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets the course image or a fallback.
     *
     * @param stdClass $course Course object.
     * @return string Image URL.
     */
    private function get_course_image(stdClass $course): string {
        $image = core_course\external\course_summary_exporter::get_course_image($course);
        if (!$image) {
            $image = 'https://picsum.photos/400/200?random=' . $course->id;
        }
        return $image;
    }

    /**
     * Gets the maximum number of tags to display from settings.
     *
     * @return int Maximum number of tags.
     */
    private function get_max_tags_limit(): int {
        $maxtags = get_config('block_topactivecourses', 'max_tags');
        if (!$maxtags || !is_numeric($maxtags) || $maxtags < 1) {
            $maxtags = 5;
        }
        return (int)$maxtags;
    }

    /**
     * Returns up to $limit tag names for a course.
     *
     * @param int $courseid
     * @param int $limit
     * @return array
     */
    private function get_course_tags(int $courseid, int $limit = 5): array {
        if (!class_exists('core_tag_tag')) {
            return [];
        }
        $tags = core_tag_tag::get_item_tags('core', 'course', $courseid);
        $tagnames = [];
        if (!empty($tags)) {
            foreach ($tags as $tag) {
                $tagnames[] = $tag->rawname;
                if (count($tagnames) >= $limit) {
                    break;
                }
            }
        }
        return $tagnames;
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
