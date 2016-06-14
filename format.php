<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/completionlib.php');

$context = context_course::instance($course->id);

if (($marker >=0) && has_capability('moodle/course:setcurrentsection', $context) && confirm_sesskey()) {
    $course->marker = $marker;
    course_set_marker($course->id, $marker);
}

$renderer = $PAGE->get_renderer('format_vv');
$renderer->print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused);

// Include course format js module
// $PAGE->requires->js('/course/format/topics/format.js');
$PAGE->requires->js('/course/format/vv/jquery.min.js');
$PAGE->requires->js('/course/format/vv/format.js');