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
 * Plugin local library methods
 *
 * @package    local_obu_banner_marks_transfer
 * @author     Emir Kamel
 * @copyright  2024, Oxford Brookes University {@link http://www.brookes.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use GuzzleHttp\Exception\RequestException;

function local_obu_banner_marks_transfer_deconstruct_group_idnum (\progress_trace $trace, $group_name) {
    $pattern = "/^(?P<course_academic_year>\d{4})\.(?P<course_subject_code_and_number>.+?)_"
        . "(?P<course_part_term>.+?)_(?P<course_run_number>\d+)_"
        . "(?P<term_code>\d{6})_(?P<crn>\d+)_"
        . "(?P<coursework_name>.+?)-(?P<coursework_sequence_number>\d+)_"
        . "(?P<component_id>\d+)_"
        . "(?P<current_reason>.{1,2})$/";

    if (preg_match($pattern, $group_name, $matches)) {
        return (object) [
            'course_academic_year' => $matches['course_academic_year'],
            'course_subject_code_and_number' => $matches['course_subject_code_and_number'],
            'course_part_term' => $matches['course_part_term'],
            'course_run_number' => $matches['course_run_number'],
            'term_code' => $matches['term_code'],
            'crn' => $matches['crn'],
            'coursework_name' => $matches['coursework_name'],
            'coursework_sequence_number' => $matches['coursework_sequence_number'],
            'component_id' => $matches['component_id'],
            'current_reason' => $matches['current_reason'],
        ];
    } else {
        $trace->output("Group name format is invalid: {$group_name}");
        return false;
    }
}

function store_logs_in_history(\progress_trace $trace, $assessment_with_grade_logs, ?RequestException $exception, $response_object = null) {
    global $DB;

    $assessment_log_history_object = new \stdClass();
    $assessment_log_history_object->marks_xfer_assess_log_id = (int) $assessment_with_grade_logs->id;
    if ($exception) {
        $assessment_log_history_object->response_code = $exception->getCode();
        $assessment_log_history_object->response_name = $exception->getResponse()->getReasonPhrase();
        $assessment_log_history_object->error_message = $exception->getMessage();
    } else if ($response_object) {
        $assessment_log_history_object->response_code = 200;
        if (empty($response_object->failureList)) {
            $assessment_log_history_object->response_name = "Success";
        } else if (empty($response_object->successList)) {
            $assessment_log_history_object->response_name = "Failure";
        } else {
            $assessment_log_history_object->response_name = "Partial success";
        }
        $assessment_log_history_object->error_message = null;
    }
    $assessment_log_history_object->timecreated = time();

    ob_start();
    var_dump($assessment_log_history_object);
    $assessment_log_object_dump = ob_get_clean();
    $trace->output("Created assessment log history object: " . $assessment_log_object_dump);

    $assessment_log_history_id = $DB->insert_record('marks_xfer_asses_history', $assessment_log_history_object);
    $trace->output("Inserted assessment log history object with ID: " . $assessment_log_history_id);

    $grade_log_history_objects = [];
    $grade_log_status_updates = [];
    foreach ($assessment_with_grade_logs->grade_logs as $grade_log) {
        $grade_log_history_object = new \stdClass();
        $grade_log_history_object->marks_xfer_grade_log_id = $grade_log->id;
        $grade_log_history_object->marks_xfer_assess_history_id = $assessment_log_history_id;
        if ($exception) {
            $grade_log_history_object->xfer_message = $exception->getMessage();
            $grade_log_history_object->status = 3;
        } elseif ($response_object) {
            if (empty($response_object->failureList)) {
                $grade_log_history_object->xfer_message = "Successful transfer";
                $grade_log_history_object->status = 2;
            } else {
                $is_failed = check_failure_list($response_object->failureList, $grade_log);
                if ($is_failed) {
                    $grade_log_history_object->xfer_message = $is_failed->failureMessage ?? "Unknown error";
                    $grade_log_history_object->status = 3; // Failed
                } else {
                    $grade_log_history_object->xfer_message = "Successful transfer";
                    $grade_log_history_object->status = 2; // Success
                }
            }
        }
        $grade_log_history_object->timecreated = time();

        $grade_log_history_objects[] = $grade_log_history_object;
        $grade_log_status_updates[] = [
            'id' => $grade_log->id,
            'status' => $grade_log_history_object->status
        ];
        update_grade_log_statuses($grade_log_status_updates);
    }

    ob_start();
    var_dump($grade_log_history_objects);
    $grade_log_object_dump = ob_get_clean();
    $trace->output("Created grade log history objects: " . $grade_log_object_dump);

    $DB->insert_records('marks_xfer_grade_history', $grade_log_history_objects);
    $trace->output("Inserted " . count($grade_log_history_objects) . " grade log history objects successfully.");
}

function check_failure_list(array $failureList, $grade_log) {
    foreach ($failureList as $failure) {
        if (isset($failure->bannerId) && $failure->bannerId === $grade_log->student_number) {
            return $failure;
        }
    }
    return false;
}

function update_grade_log_statuses(array $status_updates) {
    global $DB;

    if (empty($status_updates)) {
        return;
    }

    $case_statement = "CASE id ";
    $ids = [];

    foreach ($status_updates as $update) {
        $case_statement .= "WHEN {$update['id']} THEN {$update['status']} ";
        $ids[] = $update['id'];
    }

    $case_statement .= "END";

    $id_list = implode(',', $ids);

    $sql = "UPDATE {marks_xfer_grade_log} SET status = $case_statement WHERE id IN ($id_list)";
    $DB->execute($sql);
}

function convert_date_for_ethos(\progress_trace $trace, $original_date) {
    $date_object = DateTime::createFromFormat("l, j F Y, g:i A", $original_date);

    if ($date_object) {
        return $date_object->format("Y-m-d");
    } else {
        $trace->output("Invalid date format.");
        return null;
    }
}