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
                FROM {grade_transfer_queue}
                WHERE id > :latest_record_id
                ORDER BY assessment";

        $latest_record_id = $this->get_highest_id_from_logs($trace);

        return $DB->get_records_sql($sql, ['latest_record_id' => $latest_record_id]);
    }


    /**
     * @param progress_trace $trace
     * @param $new_transfer_records
     * @return void
     */
    public function transfer_records_to_logs(progress_trace $trace, $new_transfer_records) {
        global $DB;

        $previous_assessment_code = null;
        $previous_assessment_id = null;

        foreach ($new_transfer_records as $new_transfer_record) {
            if ($new_transfer_record->assessment != $previous_assessment_code) {
                $assessment_log_object = local_obu_banner_marks_transfer_deconstruct_group_name($trace ,$new_transfer_record->assessment);
                //TODO:: insert object into table for assessment log
                //TODO:: build grade log object with assessment log object id and store in array for bulk insert
                $previous_assessment_code = $new_transfer_record->assessment;
                $previous_assessment_id = $assessment_log_object->id;
            } else {
                //TODO:: build grade log object with previous assessment log object id and store in array for bulk insert
            }
        }
        //TODO:: bulk insert grades using array
    }


    private function get_highest_id_from_logs(progress_trace $trace) {
        global $DB;

        $sql = "SELECT 
                    MAX(grade_xfer_queue_id) AS max_grade_xfer_queue_id
                FROM {marks_transfer_grade_log}";

        $latest_record = $DB->get_record_sql($sql);
        $latest_record = $latest_record ? $latest_record->max_grade_xfer_queue_id : 0;

        $trace->output("Highest grade ID in log table: $latest_record");

        return $latest_record;
    }


    private function get_existing_assessment_logs(progress_trace $trace, $transfer_records) {
        global $DB;

        $unique_assessments = array_unique(array_column($transfer_records, 'assessment'));

        $sql = "SELECT * 
                FROM {marks_transfer_assess_log} 
                WHERE assessment IN (" . implode(',', array_fill(0, count($unique_assessments), '?')) . ")";

        $assessment_logs = $DB->get_records_sql($sql, $unique_assessments);

        $trace->output(count($assessment_logs) . " of " . count($unique_assessments) . " assessments found in existing logs.");

        // TODO : Put the assessment logs into a dictionary for easy lookup

        return $assessment_logs;
    }
}