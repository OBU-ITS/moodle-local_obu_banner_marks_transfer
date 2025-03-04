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

use gradereport_singleview\local\screen\grade;
use GuzzleHttp\Exception\RequestException;
use mod_h5pactivity\local\attempt;
use mysql_xdevapi\Exception;
use progress_trace;
use enrol_ethos\ethosclient\entities\ethos_student_gradable_components_subcomponents_info;
use enrol_ethos\ethosclient\entities\ethos_student_gradable_components_subcomponents_info_grade;
use enrol_ethos\ethosclient\providers\ethos_student_gradable_components_subcomponents_provider;
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

        return $grouped_assessments;
    }

    private function send_marks(progress_trace $trace, $assessment_with_grade_logs) {
        try {
            $marks_transfer_ethos_object = $this->prepare_marks_transfer_ethos_object($trace, $assessment_with_grade_logs);
        } catch (\Exception $e) {
            $trace->output("Failed to create marks transfer ethos object for {$assessment_with_grade_logs->access_restriction_group_idnum}: " . $e->getMessage());
            return;
        }

        $provider = ethos_student_gradable_components_subcomponents_provider::getInstance();
        try {
            $response_object = $provider->put($marks_transfer_ethos_object);
            store_logs_in_history($trace, $assessment_with_grade_logs, null, $response_object);
        } catch (RequestException $exception) {
            $status_code = $exception->getResponse()->getStatusCode();
            switch ($status_code) {
                case 400:
                case 401:
                case 403:
                case 404:
                    store_logs_in_history($trace, $assessment_with_grade_logs, $exception);
                    break;

                case 500:
                    $max_retries = 3;
                    $base_delay = 30;

                    for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
                        try {
                            $trace->output("Attempt $attempt: Retrying after 500 Internal Server Error.");
                            $response_object = $provider->put($marks_transfer_ethos_object);
                            store_logs_in_history($trace, $assessment_with_grade_logs, null, $response_object);
                        } catch (RequestException $retry_exception) {
                            $retry_status_code = $retry_exception->getResponse()->getStatusCode();
                            if ($retry_status_code === 500 && $attempt < $max_retries) {
                                $delay = $base_delay * (2 ** ($attempt - 1));
                                $trace->output("Retrying in $delay seconds...");
                                sleep($delay);
                                continue;
                            } elseif ($retry_status_code === 500 && $attempt === $max_retries) {
                                $trace->output("Max retries reached. 500 error persists: " . $retry_exception->getMessage());
                                store_logs_in_history($trace, $assessment_with_grade_logs, $retry_exception);
                            } else {
                                $trace->output("Error (HTTP $retry_status_code) encountered during retry: " . $retry_exception->getMessage());
                                store_logs_in_history($trace, $assessment_with_grade_logs, $retry_exception);
                            }
                        }
                    }
                    break;
            }
        }
    }

    /**
     * @throws \Exception
     */
    private function prepare_marks_transfer_ethos_object(progress_trace $trace, $assessment_with_grade_logs): ethos_student_gradable_components_subcomponents_info {
        $deconstructed_idnum = local_obu_banner_marks_transfer_deconstruct_group_idnum($trace ,$assessment_with_grade_logs->access_restriction_group_idnum);

        if (!$deconstructed_idnum) {
            throw new \Exception("Failed to deconstruct assessment ID: {$assessment_with_grade_logs->access_restriction_group_idnum}");
        }

        $info = new ethos_student_gradable_components_subcomponents_info();
        $info->assessmentType = $assessment_with_grade_logs->assessment_type;
        $info->crn = $deconstructed_idnum->crn;
        $info->term = $deconstructed_idnum->term_code;
        $info->componentId = $deconstructed_idnum->component_id;

        foreach ($assessment_with_grade_logs->grade_logs as $grade_log) {
            $grade = new ethos_student_gradable_components_subcomponents_info_grade();
            $grade->bannerId = $grade_log->student_number;
            $grade->currentReason = $grade_log->current_reason;
            $grade->comment = $grade_log->comment ?? "";
            $grade->score = $grade_log->score ?? 0;
            $grade->completedDate = $grade_log->completed_date
                ? convert_date_for_ethos($trace, $grade_log->completed_date)
                : date("Y-m-d");

            if (!$grade_log->completed_date && !$grade_log->score) {
                if (empty($grade_log->comment)) {
                    $grade->comment = "Not Attempted";
                }
            }

            if (!empty($grade_log->score) && $grade->comment === "Not Attempted") {
                $grade->comment = "";
            }
            
            $info->setGrade($grade);
        }

        return $info;
    }
}