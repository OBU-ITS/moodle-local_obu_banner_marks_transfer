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
    public function get_pending_records() : array {
        global $DB;

        $sql = "SELECT gl.*
                FROM {marks_xfer_grade_log} gl
                JOIN {marks_xfer_assess_log} al ON gl.marks_xfer_assess_log_id = al.id
                JOIN {marks_xfer_status} s ON gl.status = s.id
                WHERE s.status <> 'Success'
                ";

        return $DB->get_records_sql($sql);
    }

    public function run_marks_transfer(progress_trace $trace, $grade_logs) : void {
        $assessment_logs = $this->get_associated_assessment_logs($trace, $grade_logs);
        $assessment_with_grade_logs = $this->group_grades_into_assessments($trace, $assessment_logs, $grade_logs);

        foreach ($assessment_with_grade_logs as $assessment_with_grade_log) {
            // TODO : Send marks
            // $this->send_marks($trace, $assessment_with_grade_log);
        }
    }

    private function get_associated_assessment_logs(progress_trace $trace, $grade_logs) : array {
        $assessment_logs = array();

        // TODO : Get all relevant assessmentl logs

        return $assessment_logs;
    }

    private function group_grades_into_assessments(progress_trace $trace, $assessment_logs, $grade_logs) : array {

        // TODO : Group grades into Assessment logs

        return $assessment_logs;
    }

    private function send_marks(progress_trace $trace, $assessment_log) {
        $response = $this->submit_marks($trace, $assessment_log);

        // TODO : Handle response codes /record transaction etc
    }

    /**
     * This is a temp function to represent the ETHOS API call:
     * **/
    public function submit_marks(progress_trace $trace, $assessment_log) {
        $response = new \stdClass();

        $codes = [
            [200, "Success"],
            [400, "Bad Request"],
            [401, "Unauthorized"],
            [403, "Permission Denied"],
            [404, "Resource not found"],
            [500, "Server error, unexpected configuration or data"]];

        $ethos_response_idx = array_rand($codes);

        // NOTE: response type and properties are all temporary - feel free to change and alter
        $response->code = $codes[$ethos_response_idx][0];
        $response->message = $codes[$ethos_response_idx][1];
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