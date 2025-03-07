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

use Horde\Socket\Client\Exception;
use progress_trace;
use function PHPUnit\Framework\throwException;

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
     * Retrieves all new local_grade_transfer_obu records from the database.
     *
     * @param progress_trace $trace
     * @return array An array of new local_grade_transfer_obu records.
     */
    public function get_records_for_transfer(progress_trace $trace) {
        global $DB;

        $sql = "SELECT *
                FROM {local_grade_transfer_obu}
                WHERE id > :latest_record_id
                ORDER BY assessment";

        $latest_record_id = $this->get_highest_id_from_logs($trace);

        return $DB->get_records_sql($sql, ['latest_record_id' => $latest_record_id]);
    }


    /**
     * Create records of grade and assessment logs for transfer
     *
     * @param progress_trace $trace
     * @param $transfer_records
     * @return void
     */
    public function transfer_records_to_logs(progress_trace $trace, $transfer_records) {
        $existing_assessment_logs = $this->get_existing_assessment_logs($trace, $transfer_records);
        $current_assessment_log = null;
        $grade_logs = [];

        foreach ($transfer_records as $transfer_record) {
            try {
                if($transfer_record->assessment == "") {
                    throw new \Exception("Empty assessment field in local_grade_transfer_obu");
                }

                if ($current_assessment_log == null || $transfer_record->assessment != $current_assessment_log->access_restriction_group_idnum) {
                    if (array_key_exists($transfer_record->assessment, $existing_assessment_logs)) {
                        $current_assessment_log = $existing_assessment_logs[$transfer_record->assessment];
                    } else {
                        $current_assessment_log = $this->create_and_store_assessment_log($trace, $transfer_record);
                    }
                }

                $grade_log = $this->create_grade_log($trace, $current_assessment_log, $transfer_record);
                $grade_logs[] = $grade_log;
            } catch (\Exception $e) {
                $trace->output("Error processing record: " . $e->getMessage());
                continue;
            }
        }

        $this->bulk_store_grade_logs($trace, $grade_logs);
    }


    private function get_highest_id_from_logs(progress_trace $trace) : int {
        global $DB;

        $sql = "SELECT 
                    MAX(grade_xfer_queue_id) AS max_grade_xfer_queue_id
                FROM {marks_xfer_grade_log}";

        $latest_record = $DB->get_record_sql($sql);
        $latest_record = $latest_record && $latest_record->max_grade_xfer_queue_id !== null ? $latest_record->max_grade_xfer_queue_id : 0;

        $trace->output("Highest grade ID in log table: $latest_record");

        return $latest_record;
    }


    private function get_existing_assessment_logs(progress_trace $trace, $transfer_records) : array {
        global $DB;

        $unique_assessments = array_unique(array_column($transfer_records, 'assessment'));
        $unique_assessments_values = array_values($unique_assessments);

        $sql = "SELECT * 
                FROM {marks_xfer_assess_log} 
                WHERE access_restriction_group_idnum IN (" . implode(',', array_fill(0, count($unique_assessments_values), '?')) . ")";

        $records = $DB->get_records_sql($sql, $unique_assessments_values);

        $trace->output(count($records) . " of " . count($unique_assessments) . " assessments found in existing logs.");

        $assessment_logs = [];
        foreach ($records as $record) {
            $assessment_logs[$record->access_restriction_group_idnum] = $record;
        }

        return $assessment_logs;
    }


    /**
     * @throws \Exception
     */
    private function create_and_store_assessment_log(progress_trace $trace, $transfer_record) : object {
        global $DB;

        $deconstructed_idnum = local_obu_banner_marks_transfer_deconstruct_group_idnum($trace ,$transfer_record->assessment);

        if (!$deconstructed_idnum) {
            throw new \Exception("Failed to deconstruct assessment ID: {$transfer_record->assessment}");
        }

        $assessment_log_object = new \stdClass();
        $assessment_log_object->access_restriction_group_idnum = $transfer_record->assessment;
        $assessment_log_object->reason_code = $deconstructed_idnum->current_reason;
        if ($deconstructed_idnum->current_reason == 'UR' || $deconstructed_idnum->current_reason == 'RE') {
            $assessment_log_object->assessment_type = 'reassessment';
        } else {
            $assessment_log_object->assessment_type = 'assessment';
        }

        $assessment_log_object->crn = $deconstructed_idnum->crn;
        $assessment_log_object->term = $deconstructed_idnum->term_code;
        $assessment_log_object->component_id = $deconstructed_idnum->component_id;
        $assessment_log_object->timecreated = time();
        $assessment_log_object->lastupdated = time();

        $assessment_log_object->id = $DB->insert_record('marks_xfer_assess_log', $assessment_log_object);
        $trace->output("Successfully inserted assessment log for: {$transfer_record->assessment}");

        return $assessment_log_object;
    }


    private function create_grade_log(progress_trace $trace, $assessment_log, $transfer_record) : array {

        $student_number = $transfer_record->user;
        $assessment = $transfer_record->assessment;

        $grade_log_obj = [
            'grade_xfer_queue_id' => (int)$transfer_record->id,
            'marks_xfer_assess_log_id' => $assessment_log->access_restriction_group_idnum,
            'student_number' => $student_number,
            'completed_date' => $transfer_record->submission_date,
            'current_reason' => $assessment_log->reason_code,
            'extension_date' => $transfer_record->extension_date,
            'score' => $transfer_record->grade,
            'grade' => "",
            'comment' => $transfer_record->comment,
            'timecreated' => time(),
            'lastupdated' => time(),
            'status' => 1];

        $trace->output("$student_number record for $assessment ready for transfer");

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
            $sql = "INSERT INTO {marks_xfer_grade_log} (
                        grade_xfer_queue_id, 
                        marks_xfer_assess_log_id, 
                        student_number, 
                        completed_date, 
                        current_reason, 
                        extension_date, 
                        score, 
                        grade, 
                        comment, 
                        timecreated,
                        lastupdated,
                        status)
                    VALUES " . implode(',', array_fill(0, count($batch), $placeholders));

            $flat_data = [];
            foreach ($batch as $record) {
                $flat_data = array_merge($flat_data, array_values($record));
            }
            try {
            $DB->execute($sql, $flat_data);
            } catch (\Exception $e) {
                $trace->output("Error inserting grade logs: " . $e->getMessage());
                $trace->output("SQL Query: " . $sql);
                $trace->output("SQL Data: " . json_encode($flat_data));
                error_log("Database error: " . $e->getMessage());
                error_log("SQL Query: " . $sql);
                error_log("SQL Data: " . json_encode($flat_data));
            }

            $batch_runs++;

            $trace->output(count($batch) . "grade logs inserted.");
        }
    }
}