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

//TODO:: function to make ethos calls

//TODO:: function to store records in history table

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
        $trace->output("Group name format is invalid.");
    }
}

function store_logs_in_history(\progress_trace $trace, $assessment_with_grade_logs, $response) {
    global $DB;

    $assessment_log_history_object = new \stdClass();
    $assessment_log_history_object->marks_xfer_assess_log_id = $assessment_with_grade_logs->marks_xfer_assess_log_id;
    $assessment_log_history_object->response_code = $response->code;
    $assessment_log_history_object->resonse_name = $response->name;
    $assessment_log_history_object->error_message = $response->message;
    $assessment_log_history_object->timecreated = time();

    $DB->insert_record('marks_xfer_asses_history', $assessment_log_history_object);

    //TODO:: loop through grade logs create as objects, store in grade history table as bulk insert
}