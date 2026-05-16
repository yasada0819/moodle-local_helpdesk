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

namespace local_helpdesk\util;

/**
 * Class filter
 *
 * @package   local_helpdesk
 * @copyright 2025 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter {
    /**
     * Function create_filter_course
     *
     * @param string $coursefullname
     * @param int $courseid
     *
     * @return mixed
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function create_filter_course($coursefullname, $courseid) {
        return \html_writer::label(get_string("course"), "courseid") . " " .
            \html_writer::empty_tag("input", [
                "type" => "number",
                "name" => "courseid",
                "id" => "courseid",
                "value" => $courseid,
                "min" => 0,
                "style" => "width: 7rem;",
            ]) . " " . \html_writer::span(s($coursefullname), "form-control-static");
    }

    /**
     * Function create_filter_user
     *
     * @param string $userfullname
     * @param int $userid
     *
     * @return mixed
     */
    public static function create_filter_user($userfullname, $userid) {
        return \html_writer::label(get_string("user"), "find_user") . " " .
            \html_writer::empty_tag("input", [
                "type" => "number",
                "name" => "find_user",
                "id" => "find_user",
                "value" => $userid,
                "min" => 0,
                "style" => "width: 7rem;",
            ]) . " " . \html_writer::span(s($userfullname), "form-control-static");
    }
}
