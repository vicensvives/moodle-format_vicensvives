<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/format/topics/renderer.php');

class format_vv_renderer extends format_topics_renderer {

    /**
     * Generate the starting container html for a list of sections
     * @return string HTML to output.
     */
    protected function start_section_list() {
        return html_writer::start_tag('ul', array('class' => 'vv-topics'));
    }

    /**
     * Generate the closing container html for a list of sections
     * @return string HTML to output.
     */
    protected function end_section_list() {
        return html_writer::end_tag('ul');
    }

    /**
     * Generate the title for this section page
     * @return string the page title
     */
    protected function page_title() {
        return get_string('topicoutline');
    }

    /**
     * Output the html for a multiple section page
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections The course_sections entries from the DB
     * @param array $mods used for print_section()
     * @param array $modnames used for print_section()
     * @param array $modnamesused used for print_section()
     */
    public function print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused) {
        global $PAGE;

        $context = context_course::instance($course->id);
        // Title with completion help icon.

        echo html_writer::start_tag('div', array('class' => 'vv-' . $this->get_book_type($course)));
        $heading = format_text($course->fullname);
        echo html_writer::tag('div', $heading, array('class' => 'vv-title'));

        // Copy activity clipboard..
        echo $this->course_activity_clipboard($course, 0);

        // Now the list of sections..
        echo $this->start_section_list();

        // General section if non-empty.
        $thissection = $sections[0];
        unset($sections[0]);
        if ($thissection->sequence or $PAGE->user_is_editing()) {
            echo $this->section_header($thissection, $course, false, 0);
            $this->print_section($course, $thissection, $mods, $modnamesused);
            if ($PAGE->user_is_editing()) {
                print_section_add_menus($course, 0, $modnames, false, false, 0);
            }
            echo $this->section_footer();
        }

        $canviewhidden = has_capability('moodle/course:viewhiddensections', $context);
        for ($section = 1; $section <= $course->numsections; $section++) {
            if (!empty($sections[$section])) {
                $thissection = $sections[$section];
            } else {
                // This will create a course section if it doesn't exist..
                $thissection = get_course_section($section, $course->id);

                // The returned section is only a bare database object rather than
                // a section_info object - we will need at least the uservisible
                // field in it.
                $thissection->uservisible = true;
                $thissection->availableinfo = null;
                $thissection->showavailability = 0;
            }
            // Show the section if the user is permitted to access it, OR if it's not available
            // but showavailability is turned on (and there is some available info text).
            $showsection = $thissection->uservisible ||
                    ($thissection->visible && !$thissection->available && $thissection->showavailability
                    && !empty($thissection->availableinfo));
            if (!$showsection) {
                // Hidden section message is overridden by 'unavailable' control
                // (showavailability option).
                if (!$course->hiddensections && $thissection->available) {
                    echo $this->section_hidden($section);
                }

                unset($sections[$section]);
                continue;
            }

            echo $this->section_header($thissection, $course, false, 0);
            if ($thissection->uservisible) {
                $this->print_section($course, $thissection, $mods, $modnamesused);
                if ($PAGE->user_is_editing()) {
                    print_section_add_menus($course, $section, $modnames, false, false, 0);
                }
            }
            echo $this->section_footer();

            unset($sections[$section]);
        }

        if ($PAGE->user_is_editing() and has_capability('moodle/course:update', $context)) {
            // Print stealth sections if present.
            $modinfo = get_fast_modinfo($course);
            foreach ($sections as $section => $thissection) {
                if (empty($modinfo->sections[$section])) {
                    continue;
                }
                echo $this->stealth_section_header($section);
                $this->print_section($course, $thissection, $mods, $modnamesused);
                echo $this->stealth_section_footer();
            }

            echo $this->end_section_list();

            echo html_writer::start_tag('div', array('id' => 'changenumsections', 'class' => 'mdl-right'));

            // Increase number of sections.
            $straddsection = get_string('increasesections', 'moodle');
            $url = new moodle_url('/course/changenumsections.php',
                array('courseid' => $course->id,
                      'increase' => true,
                      'sesskey' => sesskey()));
            $icon = $this->output->pix_icon('t/switch_plus', $straddsection);
            echo html_writer::link($url, $icon.get_accesshide($straddsection), array('class' => 'increase-sections'));

            if ($course->numsections > 0) {
                // Reduce number of sections sections.
                $strremovesection = get_string('reducesections', 'moodle');
                $url = new moodle_url('/course/changenumsections.php',
                    array('courseid' => $course->id,
                          'increase' => false,
                          'sesskey' => sesskey()));
                $icon = $this->output->pix_icon('t/switch_minus', $strremovesection);
                echo html_writer::link($url, $icon.get_accesshide($strremovesection), array('class' => 'reduce-sections'));
            }

            echo html_writer::end_tag('div');
        } else {
            echo $this->end_section_list();
        }

        echo html_writer::end_tag('div');
    }

    /**
     * Generate the display of the header part of a section before
     * course modules are included
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @param bool $onsectionpage true if being printed on a single-section page
     * @param int $sectionreturn The section to return to after an action
     * @return string HTML to output.
     */
    protected function section_header($section, $course, $onsectionpage, $sectionreturn=null) {
        global $PAGE;

        $o = '';
        $currenttext = '';
        $sectionstyle = '';

        if ($section->section != 0) {
            // Only in the non-general sections.
            if (!$section->visible) {
                $sectionstyle = ' hidden';
            }
        }

        $o.= html_writer::start_tag('li', array('id' => 'section-'.$section->section,
            'class' => 'vv-section clearfix'.$sectionstyle));

        // When not on a section page, we display the section titles except the general section if null
        $hasnamenotsecpg = (!$onsectionpage && ($section->section != 0 || !is_null($section->name)));

        // When on a section page, we only display the general section title, if title is not the default one
        $hasnamesecpg = ($onsectionpage && ($section->section == 0 && !is_null($section->name)));

        $context = context_course::instance($course->id);

        if ($hasnamenotsecpg || $hasnamesecpg) {
            $icons = html_writer::tag('span', '', array('class' => 'vv-icon-section'));
            $icons .= html_writer::tag('span', '', array('class' => 'vv-icon-arrow'));
            $edit = '';

            if ($PAGE->user_is_editing() && has_capability('moodle/course:update', $context)) {
                $url = new moodle_url('/course/editsection.php', array('id'=>$section->id, 'sr'=>$sectionreturn));
                $edit .= html_writer::start_tag('span', array('class' => 'commands'));
                if ($section->section != 0) {
                    $controls = $this->section_edit_controls($course, $section, $onsectionpage);
                    if (!empty($controls)) {
                        $edit .= implode('', $controls);
                    }
                }
                $edit .= html_writer::end_tag('span');
            }

            $title = get_section_name($course, $section);
            $o.= html_writer::tag('h3', $icons . $title . ' ' .  $edit,  array('class' => 'vv-sectionname'));
        }

        $o .= $this->section_availability_message($section,
                has_capability('moodle/course:viewhiddensections', $context));

        return $o;
    }

    /**
     * Generate the display of the footer part of a section
     *
     * @return string HTML to output.
     */
    protected function section_footer() {
        $o= html_writer::end_tag('li');

        return $o;
    }

    /**
     * Generate the header html of a stealth section
     *
     * @param int $sectionno The section number in the coruse which is being dsiplayed
     * @return string HTML to output.
     */
    protected function stealth_section_header($sectionno) {
        $o = '';
        $o.= html_writer::start_tag('li', array('id' => 'section-'.$sectionno, 'class' => 'vv-section orphaned'));
        $o.= html_writer::tag('h3', get_string('orphanedactivities'), array('class' => 'vv-sectionname'));
        return $o;
    }

    /**
     * Generate footer html of a stealth section
     *
     * @return string HTML to output.
     */
    protected function stealth_section_footer() {
        return html_writer::end_tag('li');
    }

    /**
     * Generate the edit controls of a section
     *
     * @param stdClass $course The course entry from DB
     * @param stdClass $section The course_section entry from DB
     * @param bool $onsectionpage true if being printed on a section page
     * @return array of links with edit controls
     */
    protected function section_edit_controls($course, $section, $onsectionpage = false) {
        global $PAGE;

        if (!$PAGE->user_is_editing()) {
            return array();
        }

        $coursecontext = context_course::instance($course->id);

        if ($onsectionpage) {
            $baseurl = course_get_url($course, $section->section);
        } else {
            $baseurl = course_get_url($course);
        }
        $baseurl->param('sesskey', sesskey());

        $controls = array();

        $url = clone($baseurl);
        if (has_capability('moodle/course:sectionvisibility', $coursecontext)) {
            if ($section->visible) { // Show the hide/show eye.
                $strhidefromothers = get_string('hidefromothers', 'format_'.$course->format);
                $url->param('hide', $section->section);
                $controls[] = html_writer::link($url,
                    html_writer::empty_tag('img', array('src' => $this->output->pix_url('i/hide'),
                    'class' => 'icon hide', 'alt' => $strhidefromothers)),
                    array('title' => $strhidefromothers, 'class' => 'editing_showhide'));
            } else {
                $strshowfromothers = get_string('showfromothers', 'format_'.$course->format);
                $url->param('show',  $section->section);
                $controls[] = html_writer::link($url,
                    html_writer::empty_tag('img', array('src' => $this->output->pix_url('i/show'),
                    'class' => 'icon hide', 'alt' => $strshowfromothers)),
                    array('title' => $strshowfromothers, 'class' => 'editing_showhide'));
            }
        }

        return $controls;
    }

    private function print_section($course, $section, $mods, $modnamesused) {
        global $CFG, $USER, $DB, $PAGE, $OUTPUT;

        static $initialised;

        static $groupbuttons;
        static $groupbuttonslink;
        static $isediting;
        static $ismoving;
        static $strmovehere;
        static $strmovefull;
        static $strunreadpostsone;
        static $modulenames;

        if (!isset($initialised)) {
            $groupbuttons     = ($course->groupmode or (!$course->groupmodeforce));
            $groupbuttonslink = (!$course->groupmodeforce);
            $isediting        = $PAGE->user_is_editing();
            $ismoving         = $isediting && ismoving($course->id);
            if ($ismoving) {
                $strmovehere  = get_string("movehere");
                $strmovefull  = strip_tags(get_string("movefull", "", "'$USER->activitycopyname'"));
            }
            $modulenames      = array();
            $initialised = true;
        }

        $modinfo = get_fast_modinfo($course);
        $completioninfo = new completion_info($course);

        $issubsection = false;


        //Accessibility: replace table with list <ul>, but don't output empty list.
        if (!empty($section->sequence)) {

            $class = 'vv-section';
            if ($section->section > 0 && !$this->is_section_current($section, $course)) {
                $class .= ' vv-hidden';
            }
            echo html_writer::start_tag('ul', array('class' => $class));

            $sectionmods = explode(",", $section->sequence);

            foreach ($sectionmods as $modnumber) {
                if (empty($mods[$modnumber])) {
                    continue;
                }

                /**
                 * @var cm_info
                 */
                $mod = $mods[$modnumber];

                if ($ismoving and $mod->id == $USER->activitycopy) {
                    // do not display moving mod
                    continue;
                }

                if (isset($modinfo->cms[$modnumber])) {
                    // We can continue (because it will not be displayed at all)
                    // if:
                    // 1) The activity is not visible to users
                    // and
                    // 2a) The 'showavailability' option is not set (if that is set,
                    //     we need to display the activity so we can show
                    //     availability info)
                    // or
                    // 2b) The 'availableinfo' is empty, i.e. the activity was
                    //     hidden in a way that leaves no info, such as using the
                    //     eye icon.
                    if (!$modinfo->cms[$modnumber]->uservisible &&
                        (empty($modinfo->cms[$modnumber]->showavailability) ||
                         empty($modinfo->cms[$modnumber]->availableinfo))) {
                        // visibility shortcut
                        continue;
                    }
                } else {
                    if (!file_exists("$CFG->dirroot/mod/$mod->modname/lib.php")) {
                        // module not installed
                        continue;
                    }
                    if (!coursemodule_visible_for_user($mod) &&
                        empty($mod->showavailability)) {
                        // full visibility check
                        continue;
                    }
                }

                if (!isset($modulenames[$mod->modname])) {
                    $modulenames[$mod->modname] = get_string('modulename', $mod->modname);
                }
                $modulename = $modulenames[$mod->modname];

                // In some cases the activity is visible to user, but it is
                // dimmed. This is done if viewhiddenactivities is true and if:
                // 1. the activity is not visible, or
                // 2. the activity has dates set which do not include current, or
                // 3. the activity has any other conditions set (regardless of whether
                //    current user meets them)
                $modcontext = context_module::instance($mod->id);
                $canviewhidden = has_capability('moodle/course:viewhiddenactivities', $modcontext);
                $accessiblebutdim = false;
                $conditionalhidden = false;
                if ($canviewhidden) {
                    $accessiblebutdim = !$mod->visible;
                    if (!empty($CFG->enableavailability)) {
                        $conditionalhidden = $mod->availablefrom > time() ||
                            ($mod->availableuntil && $mod->availableuntil < time()) ||
                            count($mod->conditionsgrade) > 0 ||
                            count($mod->conditionscompletion) > 0;
                    }
                    $accessiblebutdim = $conditionalhidden || $accessiblebutdim;
                }

                $liclasses = array();
                $liclasses[] = 'vv-activity';
                // $liclasses[] = $mod->modname;
                // $liclasses[] = 'modtype_'.$mod->modname;
                $extraclasses = $mod->get_extra_classes();
                if ($extraclasses) {
                    $liclasses = array_merge($liclasses, explode(' ', $extraclasses));
                }

                if ($mod->modname == 'label') {
                    if ($ismoving) {
                        echo '<li class="vv-activity"><a title="'.$strmovefull.'"'.
                            ' href="'.$CFG->wwwroot.'/course/mod.php?moveto='.$mod->id.'&amp;sesskey='.sesskey().'">'.
                            '<img class="movetarget" src="'.$OUTPUT->pix_url('movehere') . '" '.
                            ' alt="'.$strmovehere.'" /></a></li>';
                    }

                    if ($issubsection) {
                        // Close previous subsection
                        echo html_writer::end_tag('ul');
                        echo html_writer::end_tag('li');
                    }

                    // Open new subsection
                    echo html_writer::start_tag('li', array('class' =>  'vv-subsection'));

                    $labelname = $modinfo->cms[$modnumber]->name;
                    $labelnum = '&nbsp';
                    if (preg_match('/^\[(\w+)\]( .*)$/', $labelname, $match)) {
                        $labelnum = $match[1];
                        $labelname = $match[2];
                    }

                    $icons = html_writer::tag('span', '', array('class' => 'vv-icon-arrow'));
                    $number = html_writer::tag('span', $labelnum, array('class' => 'vv-subsectionnum'));
                    $name = format_string($labelname);

                    if ($isediting) {
                        if ($groupbuttons and plugin_supports('mod', $mod->modname, FEATURE_GROUPS, 0)) {
                            if (! $mod->groupmodelink = $groupbuttonslink) {
                                $mod->groupmode = $course->groupmode;
                            }

                        } else {
                            $mod->groupmode = false;
                        }
                        $icons .= $this->make_editing_buttons($mod, true, true, $mod->indent, null);
                        $icons .= $mod->get_after_edit_icons();
                    }

                    echo html_writer::tag('h4', $icons . $number . $name, array('class' => 'vv-subsectionname'));

                    echo html_writer::start_tag('ul', array('class' => 'vv-subsection vv-hidden'));
                    $issubsection = true;
                    continue;
                }

                if ($ismoving) {
                    echo '<li class="vv-activity"><a title="'.$strmovefull.'"'.
                        ' href="'.$CFG->wwwroot.'/course/mod.php?moveto='.$mod->id.'&amp;sesskey='.sesskey().'">'.
                        '<img class="movetarget" src="'.$OUTPUT->pix_url('movehere') . '" '.
                        ' alt="'.$strmovehere.'" /></a></li>';
                }

                echo html_writer::start_tag('li', array('class'=>join(' ', $liclasses), 'id'=>'module-'.$modnumber));

                echo html_writer::start_tag('div');

                // Get data about this course-module
                list($content, $instancename) =
                    get_print_section_cm_text($modinfo->cms[$modnumber], $course);

                //Accessibility: for files get description via icon, this is very ugly hack!
                $altname = '';
                $altname = $mod->modfullname;
                // Avoid unnecessary duplication: if e.g. a forum name already
                // includes the word forum (or Forum, etc) then it is unhelpful
                // to include that in the accessible description that is added.
                if (false !== strpos(textlib::strtolower($instancename),
                                     textlib::strtolower($altname))) {
                    $altname = '';
                }
                // File type after name, for alphabetic lists (screen reader).
                if ($altname) {
                    $altname = get_accesshide(' '.$altname);
                }

                // We may be displaying this just in order to show information
                // about visibility, without the actual link
                $contentpart = '';
                if ($mod->uservisible) {
                    // Nope - in this case the link is fully working for user
                    $linkclasses = '';
                    $textclasses = '';
                    if ($accessiblebutdim) {
                        $linkclasses .= ' dimmed';
                        $textclasses .= ' dimmed_text';
                        if ($conditionalhidden) {
                            $linkclasses .= ' conditionalhidden';
                            $textclasses .= ' conditionalhidden';
                        }
                        $accesstext = '<span class="accesshide">'.
                            get_string('hiddenfromstudents').': </span>';
                    } else {
                        $accesstext = '';
                    }
                    if ($linkclasses) {
                        $linkcss = 'class="' . trim($linkclasses) . '" ';
                    } else {
                        $linkcss = '';
                    }
                    if ($textclasses) {
                        $textcss = 'class="' . trim($textclasses) . '" ';
                    } else {
                        $textcss = '';
                    }

                // Get on-click attribute value if specified
                    $onclick = $mod->get_on_click();
                    if ($onclick) {
                        $onclick = ' onclick="' . $onclick . '"';
                    }

                    if (preg_match('/unit|section/', $mod->idnumber)) {
                        $icon = html_writer::tag('div', '', array('class' => 'vv-icon-book'));
                    } elseif (preg_match('/document/', $mod->idnumber)) {
                        $icon = html_writer::tag('div', '', array('class' => 'vv-icon-document'));
                    } elseif (preg_match('/question/', $mod->idnumber)) {
                        $icon = html_writer::tag('div', '', array('class' => 'vv-icon-activity'));
                    } elseif (preg_match('/link/', $mod->idnumber)) {
                        $icon = html_writer::tag('div', '', array('class' => 'vv-icon-link'));
                    } else {
                        $attributes = array(
                            'src' => $mod->get_icon_url(),
                            'class' => 'activityicon',
                            'alt' => $modulename,
                        );
                        $icon = html_writer::empty_tag('img', $attributes);
                    }

                    if ($url = $mod->get_url()) {
                        // Display link itself
                        echo '<a ' . $linkcss . $mod->extra . $onclick .
                            ' href="' . $url . '" class="vv-activitylink">' . $icon . ' ' .
                            $accesstext . '<span class="instancename">' .
                            $instancename . $altname . '</span></a>';

                        // If specified, display extra content after link
                        if ($content) {
                            $contentpart = '<div class="' . trim('contentafterlink' . $textclasses) .
                                '">' . $content . '</div>';
                        }
                    } else {
                        // No link, so display only content
                        $contentpart = '<div ' . $textcss . $mod->extra . '>' .
                            $accesstext . $content . '</div>';
                    }

                    if (!empty($mod->groupingid) && has_capability('moodle/course:managegroups', get_context_instance(CONTEXT_COURSE, $course->id))) {
                        $groupings = groups_get_all_groupings($course->id);
                        echo " <span class=\"groupinglabel\">(".format_string($groupings[$mod->groupingid]->name).')</span>';
                    }
                } else {
                    $textclasses = $extraclasses;
                    $textclasses .= ' dimmed_text';
                    if ($textclasses) {
                        $textcss = 'class="' . trim($textclasses) . '" ';
                    } else {
                        $textcss = '';
                    }
                    $accesstext = '<span class="accesshide">' .
                        get_string('notavailableyet', 'condition') .
                        ': </span>';

                    if ($url = $mod->get_url()) {
                        // Display greyed-out text of link
                        echo '<div ' . $textcss . $mod->extra .
                            ' >' . '<img src="' . $mod->get_icon_url() .
                            '" class="activityicon" alt="" /> <span>'. $instancename . $altname .
                            '</span></div>';

                        // Do not display content after link when it is greyed out like this.
                    } else {
                        // No link, so display only content (also greyed)
                        $contentpart = '<div ' . $textcss . $mod->extra . '>' .
                            $accesstext . $content . '</div>';
                    }
                }

                // Module can put text after the link (e.g. forum unread)
                echo $mod->get_after_link();

                // If there is content but NO link (eg label), then display the
                // content here (BEFORE any icons). In this case cons must be
                // displayed after the content so that it makes more sense visually
                // and for accessibility reasons, e.g. if you have a one-line label
                // it should work similarly (at least in terms of ordering) to an
                // activity.
                if (empty($url)) {
                    echo $contentpart;
                }

                if ($isediting) {
                    if ($groupbuttons and plugin_supports('mod', $mod->modname, FEATURE_GROUPS, 0)) {
                        if (! $mod->groupmodelink = $groupbuttonslink) {
                            $mod->groupmode = $course->groupmode;
                        }

                    } else {
                        $mod->groupmode = false;
                    }
                    echo $this->make_editing_buttons($mod, true, true, $mod->indent, null);
                    echo $mod->get_after_edit_icons();
                }

                // Completion
                $completion = $completioninfo->is_enabled($mod);
                if ($completion!=COMPLETION_TRACKING_NONE && isloggedin() &&
                    !isguestuser() && $mod->uservisible) {
                    $completiondata = $completioninfo->get_data($mod,true);
                    $completionicon = '';
                    if ($isediting) {
                        switch ($completion) {
                        case COMPLETION_TRACKING_MANUAL :
                            $completionicon = 'manual-enabled'; break;
                        case COMPLETION_TRACKING_AUTOMATIC :
                            $completionicon = 'auto-enabled'; break;
                        default: // wtf
                        }
                    } else if ($completion==COMPLETION_TRACKING_MANUAL) {
                        switch($completiondata->completionstate) {
                        case COMPLETION_INCOMPLETE:
                            $completionicon = 'manual-n'; break;
                        case COMPLETION_COMPLETE:
                            $completionicon = 'manual-y'; break;
                        }
                    } else { // Automatic
                        switch($completiondata->completionstate) {
                        case COMPLETION_INCOMPLETE:
                            $completionicon = 'auto-n'; break;
                        case COMPLETION_COMPLETE:
                            $completionicon = 'auto-y'; break;
                        case COMPLETION_COMPLETE_PASS:
                            $completionicon = 'auto-pass'; break;
                        case COMPLETION_COMPLETE_FAIL:
                            $completionicon = 'auto-fail'; break;
                        }
                    }
                    if ($completionicon) {
                        $imgsrc = $OUTPUT->pix_url('i/completion-'.$completionicon);
                        $formattedname = format_string($mod->name, true, array('context' => $modcontext));
                        $imgalt = get_string('completion-alt-' . $completionicon, 'completion', $formattedname);
                        if ($completion == COMPLETION_TRACKING_MANUAL && !$isediting) {
                            $imgtitle = get_string('completion-title-' . $completionicon, 'completion', $formattedname);
                            $newstate =
                                $completiondata->completionstate==COMPLETION_COMPLETE
                                ? COMPLETION_INCOMPLETE
                                : COMPLETION_COMPLETE;
                            // In manual mode the icon is a toggle form...

                            // If this completion state is used by the
                            // conditional activities system, we need to turn
                            // off the JS.
                            if (!empty($CFG->enableavailability) &&
                                condition_info::completion_value_used_as_condition($course, $mod)) {
                                $extraclass = ' preventjs';
                            } else {
                                $extraclass = '';
                            }
                            echo html_writer::start_tag('form', array(
                                'class' => 'togglecompletion' . $extraclass,
                                'method' => 'post',
                                'action' => $CFG->wwwroot . '/course/togglecompletion.php'));
                            echo html_writer::start_tag('div');
                            echo html_writer::empty_tag('input', array(
                                'type' => 'hidden', 'name' => 'id', 'value' => $mod->id));
                            echo html_writer::empty_tag('input', array(
                                'type' => 'hidden', 'name' => 'modulename',
                                'value' => $mod->name));
                            echo html_writer::empty_tag('input', array(
                                'type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
                            echo html_writer::empty_tag('input', array(
                                'type' => 'hidden', 'name' => 'completionstate',
                                'value' => $newstate));
                            echo html_writer::empty_tag('input', array(
                                'type' => 'image', 'src' => $imgsrc, 'alt' => $imgalt, 'title' => $imgtitle,
                                'aria-live' => 'polite'));
                            echo html_writer::end_tag('div');
                            echo html_writer::end_tag('form');
                        } else {
                            // In auto mode, or when editing, the icon is just an image.
                            echo html_writer::tag('span', html_writer::empty_tag('img', array(
                                'src' => $imgsrc, 'alt' => $imgalt, 'title' => $imgalt)),
                                                  array('class' => 'autocompletion'));
                        }
                    }
                }

                // If there is content AND a link, then display the content here
                // (AFTER any icons). Otherwise it was displayed before
                if (!empty($url)) {
                    echo $contentpart;
                }

                // Show availability information (for someone who isn't allowed to
                // see the activity itself, or for staff)
                if (!$mod->uservisible) {
                    echo '<div class="availabilityinfo">'.$mod->availableinfo.'</div>';
                } else if ($canviewhidden && !empty($CFG->enableavailability)) {
                    // Don't add availability information if user is not editing and activity is hidden.
                    if ($mod->visible || $PAGE->user_is_editing()) {
                        $hidinfoclass = '';
                        if (!$mod->visible) {
                            $hidinfoclass = 'hide';
                        }
                        $ci = new condition_info($mod);
                        $fullinfo = $ci->get_full_information();
                        if($fullinfo) {
                            echo '<div class="availabilityinfo '.$hidinfoclass.'">'.get_string($mod->showavailability
                                                                                               ? 'userrestriction_visible'
                                                                                               : 'userrestriction_hidden','condition',
                                                                                               $fullinfo).'</div>';
                        }
                    }
                }

                echo html_writer::end_tag('div');
                echo html_writer::end_tag('li')."\n";
            }

        } elseif ($ismoving) {
            echo "<ul class=\"section\">\n";
        }


        if ($ismoving) {
            echo '<li class="vv-activity"><a title="'.$strmovefull.'"'.
                ' href="'.$CFG->wwwroot.'/course/mod.php?movetosection='.$section->id.'&amp;sesskey='.sesskey().'">'.
                '<img class="movetarget" src="'.$OUTPUT->pix_url('movehere') . '" '.
                ' alt="'.$strmovehere.'" /></a></li>';
        }

        if (!empty($section->sequence) || $ismoving) {
            echo "</ul><!--class='section'-->\n\n";
        }

        // Close last subsection
        if ($issubsection) {
            echo html_writer::end_tag('ul');
            echo html_writer::end_tag('li');
        }
    }

    /**
     * Produces the editing buttons for a module
     *
     * @global core_renderer $OUTPUT
     * @staticvar type $str
     * @param stdClass $mod The module to produce editing buttons for
     * @param bool $absolute_ignored ignored - all links are absolute
     * @param bool $moveselect If true a move seleciton process is used (default true)
     * @param int $indent The current indenting
     * @param int $section The section to link back to
     * @return string XHTML for the editing buttons
     */
    private function make_editing_buttons(stdClass $mod, $absolute_ignored = true, $moveselect = true, $indent=-1, $section=null) {
        global $CFG, $OUTPUT, $COURSE;

        static $str;

        $coursecontext = get_context_instance(CONTEXT_COURSE, $mod->course);
        $modcontext = get_context_instance(CONTEXT_MODULE, $mod->id);

        $editcaps = array('moodle/course:manageactivities', 'moodle/course:activityvisibility');

        // no permission to edit anything
        if (!has_any_capability($editcaps, $modcontext)) {
            return false;
        }

        $hasmanageactivities = has_capability('moodle/course:manageactivities', $modcontext);

        if (!isset($str)) {
            $str = new stdClass;
            $str->delete         = get_string("delete");
            $str->move           = get_string("move");
            $str->update         = get_string("update");
            $str->hide           = get_string("hide");
            $str->show           = get_string("show");
        }

        $baseurl = new moodle_url('/course/mod.php', array('sesskey' => sesskey()));

        if ($section !== null) {
            $baseurl->param('sr', $section);
        }
        $actions = array();


        // Move
        if ($hasmanageactivities) {
            if (!preg_match('/^\d+_(label|section|document|question|link)_\d+/', $mod->idnumber)) {
                $actions[] = new action_link(
                    new moodle_url($baseurl, array('copy' => $mod->id)),
                    new pix_icon('t/move', $str->move, 'moodle', array('class' => 'iconsmall', 'title' => '')),
                    null,
                    array('class' => 'editing_move', 'title' => $str->move)
                );
            }
        }

        // Update
        if ($hasmanageactivities) {
            $actions[] = new action_link(
                new moodle_url($baseurl, array('update' => $mod->id)),
                new pix_icon('t/edit', $str->update, 'moodle', array('class' => 'iconsmall', 'title' => '')),
                null,
                array('class' => 'editing_update', 'title' => $str->update)
            );
        }

        // Delete
        if ($hasmanageactivities) {
            $actions[] = new action_link(
                new moodle_url($baseurl, array('delete' => $mod->id)),
                new pix_icon('t/delete', $str->delete, 'moodle', array('class' => 'iconsmall', 'title' => '')),
                null,
                array('class' => 'editing_delete', 'title' => $str->delete)
            );
        }

        // hideshow
        if (has_capability('moodle/course:activityvisibility', $modcontext)) {
            if ($mod->visible) {
                $actions[] = new action_link(
                    new moodle_url($baseurl, array('hide' => $mod->id)),
                    new pix_icon('t/hide', $str->hide, 'moodle', array('class' => 'iconsmall', 'title' => '')),
                    null,
                    array('class' => 'editing_hide', 'title' => $str->hide)
                );
            } else {
                $actions[] = new action_link(
                    new moodle_url($baseurl, array('show' => $mod->id)),
                    new pix_icon('t/show', $str->show, 'moodle', array('class' => 'iconsmall', 'title' => '')),
                    null,
                    array('class' => 'editing_show', 'title' => $str->show)
                );
            }
        }

        $output = html_writer::start_tag('span', array('class' => 'commands'));
        foreach ($actions as $action) {
            if ($action instanceof renderable) {
                $output .= $OUTPUT->render($action);
            } else {
                $output .= $action;
            }
        }
        $output .= html_writer::end_tag('span');
        return $output;
    }

    /**
     * Return the book subject
     *
     * @global core_renderer $OUTPUT
     * @staticvar type $str
     * @param stdClass $mod The module to produce editing buttons for
     * @param bool $absolute_ignored ignored - all links are absolute
     * @param bool $moveselect If true a move seleciton process is used (default true)
     * @param int $indent The current indenting
     * @param int $section The section to link back to
     * @return string XHTML for the editing buttons
     */
    private function get_book_type($course) {
        $subjects = array("mates", "lengua", "naturales", "sociales");
        $subject = substr($course->idnumber, strrpos($course->idnumber, '-') +1 );
        if (!in_array($subject, $subjects)) {
            return $subjects[0];
        }
            return $subject;
    }
}
