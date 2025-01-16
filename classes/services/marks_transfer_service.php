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

class marks_transfer_service {

    private static ?marks_transfer_service $instance = null;
    public static function getInstance() : marks_transfer_service {
        if (self::$instance == null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Retrieves all pending and failed grade transfer log records from the database.
     *
     * @return array An array of unprocessed extension records.
     */
    public function get_unsent_records() : array {
        global $DB;

        $sql = "SELECT gl.*
                FROM {marks_xfer_grade_log} gl
                JOIN {marks_xfer_status} s ON gl.status = s.id
                WHERE s.status <> 'Success'
                ORDER BY gl.marks_xfer_assess_log_id
                ";

        return $DB->get_records_sql($sql);
    }

    public function run_marks_transfer(progress_trace $trace, $grade_logs) : void {
        $assessment_logs = $this->get_associated_assessment_logs($trace, $grade_logs);
        $assessments_with_grades_logs = $this->group_grades_into_assessments($trace, $assessment_logs, $grade_logs);

        foreach ($assessments_with_grades_logs as $assessment_with_grade_logs) {
            $trace->output("Sending grade logs for assessment: " . $assessment_with_grade_logs->access_restriction_group_idnum);
            $this->send_marks($trace, $assessment_with_grade_logs);
        }
    }

    private function get_associated_assessment_logs(progress_trace $trace, $grade_logs) : array {
        global $DB;

        $assessment_logs = [];
        $previous_grade_log_assessment_id = '';

        foreach ($grade_logs as $grade_log) {
            $trace->output("Getting associated assessment log for grade log id: " . $grade_log->grade_xfer_queue_id);
            if ($grade_log->marks_xfer_assess_log_id != $previous_grade_log_assessment_id) {
                $trace->output("New grade log assessment id retrieved : " . $grade_log->marks_xfer_assess_log_id);
                $assessment_logs[] = $grade_log->marks_xfer_assess_log_id;
                $previous_grade_log_assessment_id = $grade_log->marks_xfer_assess_log_id;
            }
        }

        $assessment_logs = array_unique($assessment_logs);
        list($in_sql, $params) = $DB->get_in_or_equal($assessment_logs);

        $sql = "SELECT * 
            FROM {marks_xfer_assess_log} 
            WHERE access_restriction_group_idnum $in_sql";

        return $DB->get_records_sql($sql, $params);
    }

    private function group_grades_into_assessments(progress_trace $trace, $assessment_logs, $grade_logs) : array {

        $grouped_assessments = [];
        foreach ($assessment_logs as $assessment_log) {
            $assessment_log->grade_logs = [];
            $grouped_assessments[$assessment_log->access_restriction_group_idnum] = $assessment_log;
        }

        foreach ($grade_logs as $grade_log) {
            $assessment_id = $grade_log->marks_xfer_assess_log_id;

            if (isset($grouped_assessments[$assessment_id])) {
                $trace->output("Adding grade log with assessment ID {$grade_log->marks_xfer_assess_log_id} to assessment log with ID {$grouped_assessments[$assessment_id]->access_restriction_group_idnum}.");
                $trace->output("Before appending: " . print_r($grouped_assessments[$assessment_id]->grade_logs, true));
                $grouped_assessments[$assessment_id]->grade_logs[$grade_log->id] = $grade_log;
                $trace->output("After appending: " . print_r($grouped_assessments[$assessment_id]->grade_logs, true));
            } else {
                $trace->output("Warning: Grade log with ID {$grade_log->grade_xfer_queue_id} has no matching assessment log.");
            }
        }

        echo ("Assessment logs: " . var_dump($assessment_logs));
        echo ("Grade logs: " . var_dump($grade_logs));
        var_dump($grouped_assessments);
        die();
        return $grouped_assessments;
    }

    private function send_marks(progress_trace $trace, $assessment_with_grade_logs) {
        $response = $this->submit_marks($trace, $assessment_with_grade_logs);

        if ($response->code == 400 || $response->code == 403 || $response->code == 404) {
            foreach ($assessment_with_grade_logs->grade_logs as $grade_log) {
                $grade_log->status = 3;
                $grade_log->last_updated = time();
            }
            store_logs_in_history($trace, $assessment_with_grade_logs, $response);
        } elseif ($response->code == 401 || $response->code == 500) {
            //TODO:: retry with exponential backoff
        } elseif ($response->code == 200) {
            //TODO:: Success
        } else {
            //TODO:: whats going on here then?
        }
    }

    /**
     * This is a temp function to represent the ETHOS API call:
     * **/
    public function submit_marks(progress_trace $trace, $assessment_log) {
        $response = new \stdClass();

        $codes = [
            [200, "Success", null],
            [400, "Bad Request", "This means the data was not in the correct format due to xyz"],
            [401, "Unauthorized", null],
            [403, "Permission Denied", "Permission denied for API call due to xyz"],
            [404, "Resource not found", "Could not find the thingie that needs inserting"],
            [500, "Server error, unexpected configuration or data", null]];

        $ethos_response_idx = array_rand($codes);

        // NOTE: response type and properties are all temporary - feel free to change and alter
        $response->code = $codes[$ethos_response_idx][0];
        $response->name = $codes[$ethos_response_idx][1];
        $response->message = $codes[$ethos_response_idx[2]];
        $response->successList = array();
        $response->failureList = array();
        $trace->output("Response: {$response->code} - {$response->message}");

        if($response->code == 200) {
            foreach($assessment_log->grade_logs as $grade_log) {
                $random_percentage = mt_rand(1, 100);
                if($random_percentage > 70) {
                    $response->successList[] = $grade_log;
                }
                else {
                    $response->failureList[] = $grade_log;
                }
            }
        }

        return $response;
    }
}