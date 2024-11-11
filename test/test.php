<?php
//**
//  URL REMINDER: http://poodledev/moodle/local/obu_banner_marks_transfer/test/test.php
//  */

namespace local_obu_banner_marks_transfer\test;

require_once(__DIR__ . '/../../../config.php'); // Adjust the path as necessary

defined('MOODLE_INTERNAL') || die();

if (!is_siteadmin()) {
    // Redirect to the site homepage
    redirect(new \moodle_url('/')); // Redirects to the homepage
    die(); // Ensure the script stops execution after redirect
}