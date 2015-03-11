<?php
// This file is for Moodle - http://moodle.org/
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
 * The results import report for offlinequizzes
 *
 * @package       mod
 * @subpackage    offlinequiz
 * @author        Juergen Zimmer
 * @copyright     2012 The University of Vienna
 * @since         Moodle 2.1
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/offlinequiz/report/rimport/upload_form.php');
require_once($CFG->libdir . '/filelib.php');

class offlinequiz_rimport_report extends offlinequiz_default_report {

    private function print_error_report($offlinequiz) {
        global $CFG, $DB, $OUTPUT;

        offlinequiz_load_useridentification();
        $offlinequizconfig = get_config('offlinequiz');

        $nologs = optional_param('nologs', 0, PARAM_INT);
        $pagesize = optional_param('pagesize', 10, PARAM_INT);

        $letterstr = 'ABCDEFGHIJKL';

        require_once('errorpages_table.php');

        $tableparams = array('q' => $offlinequiz->id, 'mode' => 'rimport', 'action' => 'delete',
                'strreallydel'  => addslashes(get_string('deletepagecheck', 'offlinequiz')));

        $table = new offlinequiz_selectall_table('mod_offlinequiz_import_report', 'report.php', $tableparams);

        $tablecolumns = array('checkbox', 'counter', 'userkey', 'groupnumber', 'pagenumber', 'time', 'error', 'info', 'link');
        $tableheaders = array('', '#', get_string($offlinequizconfig->ID_field, 'offlinequiz_rimport'),
                get_string('group'), get_string('page'), get_string('importedon', 'offlinequiz_rimport'), get_string('error'), get_string('info'), '');

        $table->initialbars(true);
        $table->define_columns($tablecolumns);
        $table->define_headers($tableheaders);
        $table->define_baseurl($CFG->wwwroot . '/mod/offlinequiz/report.php?mode=rimport&amp;q=' .
                $offlinequiz->id . '&amp;nologs=' . $nologs .
                '&amp;pagesize=' . $pagesize);

        $table->sortable(true, 'time'); // Sorted by lastname by default.
        // $table->collapsible(true);
        $table->initialbars(true);

        $table->column_class('checkbox', 'checkbox');
        $table->column_class('counter', 'counter');
        $table->column_class('username', 'username');
        $table->column_class('group', 'group');
        $table->column_class('page', 'page');
        $table->column_class('time', 'time');
        $table->column_class('error', 'error');
        $table->column_class('link', 'link');

        $table->set_attribute('cellspacing', '0');
        $table->set_attribute('cellpadding', '4');
        $table->set_attribute('id', 'errorpages');
        $table->set_attribute('class', 'errorpages');
        // $table->set_attribute('width', '100%');
        $table->set_attribute('align', 'center');
        $table->set_attribute('border', '1');

        $table->no_sorting('checkbox');
        $table->no_sorting('counter');
        $table->no_sorting('info');
        $table->no_sorting('link');

        // Start working -- this is necessary as soon as the niceties are over.
        $table->setup();

        // Construct the SQL.

        $sql = "SELECT *
                  FROM {offlinequiz_scanned_pages}
                 WHERE offlinequizid = :offlinequizid
                   AND (status = 'error'
                        OR status = 'suspended'
                        OR error = 'missingpages')";

        $params = array('offlinequizid' => $offlinequiz->id);

        // Add extra limits due to sorting by question grade.
        if ($sort = $table->get_sql_sort()) {
            if (strpos($sort, 'checkbox') === false && strpos($sort, 'counter') === false &&
                    strpos($sort, 'info') === false && strpos($sort, 'link') === false) {
                $sql .= ' ORDER BY ' . $sort;
            }
        }

        $errorpages = $DB->get_records_sql($sql, $params);

        $strtimeformat = get_string('strftimedatetime');

        // options for the popup_action
        $options = array();
        $options['height'] = 1200; // optional
        $options['width'] = 1170; // optional
        $options['resizable'] = false;

        $counter = 1;

        foreach ($errorpages as $page) {

            if ($page->error == 'filenotfound') {
                $actionlink = '';
            } else {
                if ($page->error == 'missingpages') {
                    $url = new moodle_url($CFG->wwwroot . '/mod/offlinequiz/image.php?pageid=' . $page->id . '&resultid=' . $page->resultid);
                    $title = get_string('showpage', 'offlinequiz_rimport');
                } else {
                    $url = new moodle_url($CFG->wwwroot . '/mod/offlinequiz/correct.php?pageid=' . $page->id);
                    $title = get_string('correcterror', 'offlinequiz_rimport');
                }

                $actionlink = $OUTPUT->action_link($url, $title, new popup_action('click', $url, 'correct' . $page->id, $options));
            }
            //             if ($page->error == 'missingpages') {
            //              $actionlink = ' ';
            //             }

            $groupstr = '?';
            $groupnumber = $page->groupnumber;
            if ($groupnumber > 0 and $groupnumber <= $offlinequiz->numgroups) {
                $groupstr = $letterstr[$page->groupnumber - 1];
            }

            $errorstr = '';
            if (!empty($page->error)) {
                $errorstr = get_string('error' . $page->error, 'offlinequiz_rimport');
            }
            if ($page->status == 'suspended') {
                $errorstr = get_string('waitingforanalysis', 'offlinequiz_rimport');
            }
            $row = array(
                    '<input type="checkbox" name="p' . $page->id . '" value="'.$page->id.'" />',
                    $counter.'&nbsp;',
                    $page->userkey,
                    $groupstr,
                    empty($page->pagenumber) ? '?' : $page->pagenumber,
                    userdate($page->time, $strtimeformat),
                    $errorstr,
                    $page->info,
                    $actionlink
            );
            $table->add_data($row);
            $counter++;
        }

        if (!$table->print_nothing_to_display()) {
            // Print the table.
            $table->print_html();  // Print the whole table
        }
    }


    /**
     * (non-PHPdoc)
     * @see offlinequiz_default_report::display()
     */
    public function display($offlinequiz, $cm, $course) {
        global $CFG, $COURSE, $DB, $OUTPUT, $USER;

        $this->context = context_module::instance($cm->id);

        $pageoptions = array();
        $pageoptions['id'] = $cm->id;
        $pageoptions['mode'] = 'rimport';

        $reporturl = new moodle_url('/mod/offlinequiz/report.php', $pageoptions);

        $action = optional_param('action', '', PARAM_ACTION);
        if ($action != 'delete') {
            $this->print_header_and_tabs($cm, $course, $offlinequiz, 'rimport');

            if (!$offlinequiz->docscreated) {
                echo $OUTPUT->heading(format_string($offlinequiz->name));
                echo $OUTPUT->heading(get_string('nopdfscreated', 'offlinequiz'));
                return true;
            }

            echo $OUTPUT->heading(format_string($offlinequiz->name));
            echo $OUTPUT->heading_with_help(get_string('resultimport', 'offlinequiz'), 'import', 'offlinequiz');
        }

        $import_form = new offlinequiz_upload_form($reporturl,
                array('offlinequiz' => $offlinequiz, 'context' => $this->context));

        // has the user submitted a file?
        if ($fromform = $import_form->get_data() && confirm_sesskey()) {
            // file checks out ok
            $fileisgood = false;

            // work out if this is an uploaded file
            // or one from the filesarea.
            $realfilename = $import_form->get_new_filename('newfile');
            // create a unique temp dir.
            srand(microtime()*1000000);
            $unique = str_replace('.', '', microtime(true) . rand(0, 100000));
            $dirname = "{$CFG->tempdir}/offlinequiz/import/$unique";
            check_dir_exists($dirname, true, true);

            $importfile = $dirname . '/' . $realfilename;

            if (!$result = $import_form->save_file('newfile', $importfile, true)) {
                throw new moodle_exception('uploadproblem');
            }

            $files = array();
            $mimetype = mimeinfo('type', $importfile);
            if ($mimetype == 'application/zip') {
                if (unzip_file($importfile)) {
                    unlink($importfile);
                    $files = get_directory_list($dirname);
                } else {
                    echo $OUTPUT->notification(get_string('couldnotunzip', 'offlinequiz_rimport', $realfilename), 'notifyproblem');

                }
            } else if (preg_match('/^image/' , $mimetype)) {
                $files[] = $realfilename;
            }
            $added = count($files);

            // create a new queue job
            $job = new stdClass();
            $job->offlinequizid = $offlinequiz->id;
            $job->importuserid = $USER->id;
            $job->timecreated = time();
            $job->timestart = 0;
            $job->timefinish = 0;
            $job->status = 'new';
            if (!$job->id = $DB->insert_record('offlinequiz_queue', $job)) {
                echo $OUTPUT->notification(get_string('couldnotcreatejob', 'offlinequiz_rimport'), 'notifyproblem');
            }

            // add the files to the job
            foreach ($files as $file) {
                $jobfile = new stdClass();
                $jobfile->queueid = $job->id;
                $jobfile->filename = $dirname . '/' . $file;
                $jobfile->status = 'new';
                if (!$jobfile->id = $DB->insert_record('offlinequiz_queue_data', $jobfile)) {
                    echo $OUTPUT->notification(get_string('couldnotcreatejobfile', 'offlinequiz_rimport'), 'notifyproblem');
                    $added--;
                }
            }

            // notify the user
            echo $OUTPUT->notification(get_string('addingfilestoqueue', 'offlinequiz_rimport', $added), 'notifysuccess');
            echo $OUTPUT->continue_button($CFG->wwwroot . '/mod/offlinequiz/report.php?q=' . $offlinequiz->id . '&mode=rimport');
        } else {

            // print info about offlinequiz_queue jobs
            $sql = 'SELECT COUNT(*) as count
                      FROM {offlinequiz_queue} q
                      JOIN {offlinequiz_queue_data} qd on q.id = qd.queueid
                     WHERE (qd.status = :status1 OR qd.status = :status3)
                       AND q.offlinequizid = :offlinequizid
                       AND q.status = :status2
                    ';
            $newforms = $DB->get_record_sql($sql, array('offlinequizid' => $offlinequiz->id, 'status1' => 'new',
                    'status2' => 'new', 'status3' => ''));
            $processingforms = $DB->get_record_sql($sql, array('offlinequizid' => $offlinequiz->id, 'status1' => 'processing',
                    'status2' => 'processing', 'status3' => 'new'));

            if ($newforms->count > 0) {
                echo $OUTPUT->notification(get_string('newformsinqueue', 'offlinequiz_rimport', $newforms->count), 'notifysuccess');
            }
            if ($processingforms->count > 0) {
                echo $OUTPUT->notification(get_string('processingformsinqueue', 'offlinequiz_rimport', $processingforms->count), 'notifysuccess');
            }

            $action = optional_param('action', '', PARAM_ACTION);

            switch ($action) {
                case 'delete':
                    if (confirm_sesskey()) {

                        $selectedpageids = array();
                        $params = (array) data_submitted();

                        foreach ($params as $key => $value) {
                            if (preg_match('!^p([0-9]+)$!', $key, $matches)) {
                                $selectedpageids[] = $matches[1];
                            }
                        }

                        foreach ($selectedpageids as $pageid) {
                            if ($pageid && ($page = $DB->get_record('offlinequiz_scanned_pages', array('id' => $pageid)))) {
                                offlinequiz_delete_scanned_page($page, $this->context);
                            }
                        }

                        redirect($CFG->wwwroot . '/mod/offlinequiz/report.php?q=' . $offlinequiz->id . '&amp;mode=rimport');
                    } else {
                        print_error('invalidsesskey');
                    }
                    break;
                default:
                    if (!file_exists($CFG->filter_tex_pathconvert)) {
                        $OUTPUT->notification(get_string('imagickwarning', 'offlinequiz'), 'notifyproblem');
                    }
                    // print the table with answer forms that need correction
                    $this->print_error_report($offlinequiz);
                    // display the upload form
                    $import_form->display();
            }
        }
    }
}
