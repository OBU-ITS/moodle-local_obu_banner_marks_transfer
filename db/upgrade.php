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
 * OBU Banner Marks Transfer - Database upgrade
 *
 * @package    obu_banner_marks_transfer
 * @category   local
 * @author     Emir Kamel
 * @copyright  2024, Oxford Brookes University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

function xmldb_local_obu_banner_marks_transfer_upgrade($oldversion = 0) {
    global $DB;
    $dbman = $DB->get_manager();

    $result = true;

    if ($oldversion < 2024111404) {
        // Define the table and the field.
        $table = new xmldb_table('marks_xfer_assess_log');
        $field = new xmldb_field('reason_code', XMLDB_TYPE_TEXT, '2', null, XMLDB_NOTNULL, false, null, 'access_restriction_group_idnum');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024111404, 'local', 'obu_banner_marks_transfer');
    }

    if ($oldversion < 2025030702) {
        $table = new xmldb_table('marks_xfer_grade_log');

        $field_completed_date = new xmldb_field('completed_date', XMLDB_TYPE_TEXT, '10', null, null, null, null, 'student_number');
        if ($dbman->field_exists($table, $field_completed_date)) {
            $dbman->change_field_notnull($table, $field_completed_date);
        }

        $field_score = new xmldb_field('score', XMLDB_TYPE_TEXT, '10', null, null, null, null, 'extension_date');
        if ($dbman->field_exists($table, $field_score)) {
            $dbman->change_field_notnull($table, $field_score);
        }

        $field_comment = new xmldb_field('comment', XMLDB_TYPE_TEXT, '10', null, null, null, null, 'grade');
        if ($dbman->field_exists($table, $field_comment)) {
            $dbman->change_field_notnull($table, $field_comment);
        }

        upgrade_plugin_savepoint(true, 2025030702, 'local', 'obu_banner_marks_transfer');
    }

    if ($oldversion < 2025042901) {
        $table = new xmldb_table('marks_xfer_status');
        $field = new xmldb_field('status', XMLDB_TYPE_TEXT, '15', null, XMLDB_NOTNULL, false);

        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }

        $existing = $DB->get_record_select(
            'marks_xfer_status',
            $DB->sql_compare_text('status') . ' = :status',
            ['status' => 'Needs review']
        );

        if (!$existing) {
            $status = new stdClass();
            $status->status = 'Needs review';
            $DB->insert_record('marks_xfer_status', $status);
        }

        upgrade_plugin_savepoint(true, 2025041501, 'local', 'obu_banner_marks_transfer');
    }

    return $result;
}