<?php
//**
//  URL REMINDER: http://poodledev/moodle/local/obu_banner_marks_transfer/test/reset_queue.php
//  */

require_once(__DIR__ . '/../../../config.php');

defined('MOODLE_INTERNAL') || die();

if (!is_siteadmin()) {
    redirect(new \moodle_url('/'));
    die();
}

global $DB;

$trace = new \html_progress_trace();

// Truncate tables used in marks transfer specific to the class
$DB->delete_records('marks_xfer_asses_history');
$DB->delete_records('marks_xfer_asses_log');
$DB->delete_records('marks_xfer_grade_history');
$DB->delete_records('marks_xfer_grade_log');
$DB->delete_records('local_grade_transfer_obu');

$trace->output('Truncated previous marks transfer tables');

$trace->finished();