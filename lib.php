<?php

require_once "{$CFG->dirroot}/course/format/topics/lib.php";

/**
 * Indicates this format uses sections.
 *
 * @return bool Returns true
 */
function callback_vv_uses_sections() {
    return callback_topics_uses_sections();
}

/**
 * Used to display the course structure for a course where format=topic
 *
 * This is called automatically by {@link load_course()} if the current course
 * format = weeks.
 *
 * @param array $path An array of keys to the course node in the navigation
 * @param stdClass $modinfo The mod info object for the current course
 * @return bool Returns true
 */
function callback_vv_load_content(&$navigation, $course, $coursenode) {
    return callback_topics_load_content($navigation, $course, $coursenode);
}

/**
 * The string that is used to describe a section of the course
 * e.g. Topic, Week...
 *
 * @return string
 */
function callback_vv_definition() {
    return callback_topics_definition();
}

function callback_vv_get_section_name($course, $section) {
    return callback_topics_get_section_name($course, $section);
}

/**
 * Declares support for course AJAX features
 *
 * @see course_format_ajax_support()
 * @return stdClass
 */
function callback_vv_ajax_support() {
    $ajaxsupport = new stdClass();
    $ajaxsupport->capable = false;
    $ajaxsupport->testedbrowsers = array();
    return $ajaxsupport;
}

/**
 * Callback function to do some action after section move
 *
 * @param stdClass $course The course entry from DB
 * @return array This will be passed in ajax respose.
 */
function callback_vv_ajax_section_move($course) {
    return callback_topics_ajax_section_move($course);
}
