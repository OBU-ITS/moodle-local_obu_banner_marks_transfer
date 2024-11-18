<?php
//**
//  URL REMINDER: http://poodledev/moodle/local/obu_banner_marks_transfer/test/test.php
//  */

namespace local_obu_banner_marks_transfer\test;

use local_obu_banner_marks_transfer\handlers\prepare_marks_transfer_handler;

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/handlers/prepare_marks_transfer_handler.php');

defined('MOODLE_INTERNAL') || die();

if (!is_siteadmin()) {
    redirect(new \moodle_url('/'));
    die();
}

$trace = new \html_progress_trace();
$handler = new prepare_marks_transfer_handler($trace);

$handler->handle_prepare_marks_transfer();
$trace->finished();