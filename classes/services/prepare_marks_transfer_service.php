<?php
namespace local_obu_banner_marks_transfer\services;

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
 * @package    local_obu_banner_marks_transfer
 * @author     Emir Kamel
 * @copyright  2024, Oxford Brookes University {@link http://www.brookes.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use progress_trace;
global $CFG;
require_once($CFG->dirroot . '/local/obu_banner_marks_transfer/locallib.php');

class prepare_marks_transfer_service {

    private static ?prepare_marks_transfer_service $instance = null;
    public static function getInstance() : prepare_marks_transfer_service {
        if (self::$instance == null) {
            self::$instance = new self();
        }

        return self::$instance;
    }


    /**
     * Retrieves all new grade transfer queue records from the database.
     *
     * @param progress_trace $trace
     * @return array An array of new grade_transfer_queue records.
     */
    public function get_records_for_transfer(progress_trace $trace) {
        global $DB;

        $sql = "SELECT *
                FROM {grade_xfer_queue}
                WHERE id > :latest_record_id
                ORDER BY assessment";

        $latest_record_id = $this->get_highest_id_from_logs($trace);

        return $DB->get_records_sql($sql, ['latest_record_id' => $latest_record_id]);
    }


    /**
     * @param progress_trace $trace
     * @param $transfer_records
     * @return void
     */
    public function transfer_records_to_logs(progress_trace $trace, $transfer_records) {

        $existing_assessment_logs = $this->get_existing_assessment_logs($trace, $transfer_records);
        $current_assessment_log = null;
        $grade_logs = [];

        foreach ($transfer_records as $transfer_record) {
            if($transfer_record->assessment == "") {
                // TODO : Should never happen - what should we do if it does?!
                continue;
            }

            if($current_assessment_log == null || $transfer_record->assessment != $current_assessment_log->access_restriction_group_idnum) {
                $current_assessment_log = array_key_exists($transfer_record->assessment, $existing_assessment_logs)
                    ? $existing_assessment_logs[$transfer_record->assessment]
                    : $this->create_and_store_assessment_log($trace, $transfer_record);
            }

            $grade_log = $this->create_grade_log($trace, $current_assessment_log, $transfer_record);
            $grade_logs[] = $grade_log;
        }

        $this->bulk_store_grade_logs($trace, $grade_logs);
    }


    private function get_highest_id_from_logs(progress_trace $trace) : int {
        global $DB;

        $sql = "SELECT 
                    MAX(grade_xfer_queue_id) AS max_grade_xfer_queue_id
                FROM {marks_xfer_grade_log}";

        $latest_record = $DB->get_record_sql($sql);
        $latest_record = $latest_record ? $latest_record->max_grade_xfer_queue_id : 0;

        $trace->output("Highest grade ID in log table: $latest_record");

        return $latest_record;
    }


    private function get_existing_assessment_logs(progress_trace $trace, $transfer_records) : array {
        global $DB;

        $unique_assessments = array_unique(array_column($transfer_records, 'assessment'));
        $unique_assessments_values = array_values($unique_assessments);

        $sql = "SELECT * 
                FROM {marks_xfer_assess_log} 
                WHERE assessment IN (" . implode(',', array_fill(0, count($unique_assessments_values), '?')) . ")";

        $records = $DB->get_records_sql($sql, $unique_assessments_values);

        $trace->output(count($records) . " of " . count($unique_assessments) . " assessments found in existing logs.");

        $assessment_logs = [];
        foreach ($records as $record) {
            $assessment_logs[$record->assessment] = $record;
        }

        return $assessment_logs;
    }


    private function create_and_store_assessment_log(progress_trace $trace, $transfer_record) : object {

        // TODO : use transfer record to build assessment object and store

        return new \stdClass();
    }


    private function create_grade_log(progress_trace $trace, $current_assessment_log, $transfer_record) : array {

        $student_number = $transfer_record['user'];
        $student_pidm = $transfer_record['user_udf1'];
        $assessment = $transfer_record['assessment'];

        // TODO : Confirm values set
        $grade_log_obj = [
            'grade_xfer_queue_id' => $transfer_record['id'],
            'marks_xfer_assess_log_id' => $current_assessment_log['id'],
            'student_number' => $student_pidm,
            'completed_date' => $transfer_record['submission_date'],
            'current_reason' => $current_assessment_log['current_reason'],
            'extension_date' => $transfer_record['extension_date'],
            'score' => $transfer_record['grade'],
            'grade' => "",
            'comment' => $transfer_record['comment'],
            'timecreated' => time(),
            'lastupdated' => time(),
            'status' => 0];

        $trace->output("$student_number ($student_pidm) record for $assessment ready for transfer");

        return $grade_log_obj;
    }


    private function bulk_store_grade_logs(progress_trace $trace, $grade_logs) : void {
        global $DB;

        $batch_size = 1000;
        $batch_runs = 0;

        for ($i = 0; $i < count($grade_logs); $i += $batch_size) {
            $batch = array_slice($grade_logs, $i, $batch_size);
            $fields = array_keys($batch[0]);

            $placeholders = '(' . implode(',', array_fill(0, count($fields), '?')) . ')';
            $sql = "INSERT INTO {marks_transfer_grade_log} (
                                          'grade_xfer_queue_id', 
                                          'marks_xfer_assess_log_id', 
                                          'student_number', 
                                          'completed_date', 
                                          'current_reason', 
                                          'extension_date', 
                                          'score', 
                                          'grade', 
                                          'comment', 
                                          'timecreated',
                                          'lastupdated',
                                          'status')
                    VALUES " . implode(',', array_fill(0, count($batch), $placeholders));

            $flat_data = [];
            foreach ($batch as $record) {
                $flat_data = array_merge($flat_data, array_values($record));
            }

            $DB->execute($sql, $flat_data);
            $batch_runs++;

            $trace->output(count($batch) . "grade logs inserted.");
        }
    }
}