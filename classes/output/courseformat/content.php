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
 * Contains the default content output class.
 *
 * @package   format_vv
 * @copyright 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_vv\output\courseformat;

use context_course;
use core_courseformat\output\local\content as content_base;
use renderer_base;

/**
 * Base class to render a course content.
 *
 * @package   format_vv
 * @copyright 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content extends content_base {

    /**
     * @var bool Topic format has add section after each topic.
     *
     * The responsible for the buttons is core_courseformat\output\local\content\section.
     */
    protected $hasaddsection = false;

    /**
     * Export this data so it can be used as the context for a mustache template (core/inplace_editable).
     *
     * @param renderer_base $output typically, the renderer that's calling this function
     * @return stdClass data context for a mustache template
     */
    public function export_for_template(renderer_base $output) {
        $data = parent::export_for_template($output);
        // Unset initial section title so no name is shown.
        // $data->initialsection->header->title = '';
        $course = $this->format->get_course();
        $data->booktype = $this->get_book_type($course);
        $context = context_course::instance($course->id);
        $data->coursefullname = format_string($course->fullname, true, array('context' => $context));
        return $data;
    }

    /**
     * Returns the output class template path.
     *
     * This method redirects the default template when the course content is rendered.
     */
    public function get_template_name(\renderer_base $renderer): string {
        return 'format_vv/local/content';
    }


    private function get_book_type($course) {
        $subjects = ["mates", "lengua", "naturales", "sociales"];
        $subject = substr($course->idnumber, strrpos($course->idnumber, '-') + 1);
        if (!in_array($subject, $subjects)) {
            return $subjects[0];
        }
        return $subject;
    }
}