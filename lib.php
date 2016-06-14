<?php

require_once "{$CFG->dirroot}/course/format/topics/lib.php";

class format_vv extends format_topics {

    /**
     * Returns the information about the ajax support in the given source format
     *
     * The returned object's property (boolean)capable indicates that
     * the course format supports Moodle course ajax features.
     * The property (array)testedbrowsers can be used as a parameter for {@link ajaxenabled()}.
     *
     * @return stdClass
     */
    public function supports_ajax() {
        $ajaxsupport = new stdClass();
        $ajaxsupport->capable = false;
        $ajaxsupport->testedbrowsers = array();
        return $ajaxsupport;
    }

    /**
     * Definitions of the additional options that this course format uses for course
     *
     * Topics format uses the following options:
     * - coursedisplay
     * - numsections
     * - hiddensections
     *
     * @param bool $foreditform
     * @return array of options
     */
    public function course_format_options($foreditform = false) {
        $options = parent::course_format_options($foreditform);
        unset($options->coursedisplay); // This format is only single-page
        return $options;
    }

    /**
     * Allows course format to execute code on moodle_page::set_course()
     *
     * @param moodle_page $page instance of page calling set_course
     */
    public function page_set_course(moodle_page $page) {
        global $PAGE;

        if (!$PAGE->requires->is_head_done()) {
            $PAGE->requires->jquery();
        }
    }

    /**
     * Returns the format options stored for this course or course section
     *
     * @param null|int|stdClass|section_info $section if null the course format options will be returned
     *     otherwise options for specified section will be returned. This can be either
     *     section object or relative section number (field course_sections.section)
     * @return array
     */
    public function get_format_options($section = null) {
        $options = parent::get_format_options($section);
        $options['coursedisplay'] = COURSE_DISPLAY_SINGLEPAGE; // This format is only single-page
        return $options;
    }
}
