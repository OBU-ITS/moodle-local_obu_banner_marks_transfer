<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Code to execute on plugin installation
 */
function xmldb_obu_banner_marks_transfer_install() {
    global $DB;

    $statuses = [
        ['status' => 'Pending'],
        ['status' => 'Success'],
        ['status' => 'Failed']
    ];

    foreach ($statuses as $status) {
        $DB->insert_record('marks_transfer_status', $status);
    }

    return true;
}