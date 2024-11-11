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
     * @return array An array of new grade_transfer_queue records.
     */
    public function get_new_transfer_records() {
        global $DB;

        $latest_record = $this->get_highest_id_from_log_table();
        $latest_record_id = $latest_record ? $latest_record->max_grade_xfer_queue_id : 0;

        $sql = "SELECT * FROM {grade_transfer_queue}
                WHERE id > :latest_record_id
                ORDER BY  assessment";

        return $DB->get_records_sql($sql, ['latest_record_id' => $latest_record_id]);
    }

    public function prepare_marks_transfer(\progress_trace $trace, $new_transfer_records) {
        global $DB;

        $previous_assessment_code = null;

        $assessment_log_objects_array = $this->get_all_constructed_assessment_log_objects();

        foreach ($new_transfer_records as $new_transfer_record) {
            //TODO:: if new assessment code != previous assessment code then get from dictionary above or create new one (locallib function from the spec) [assessment log object]
            //TODO:: build grade log object with assessment log object id and store in array for bulk insert
        }

        //TODO:: bulk insert grades using array
    }

    private function get_highest_id_from_log_table() {
        global $DB;

        $sql = "SELECT MAX(grade_xfer_queue_id) AS max_grade_xfer_queue_id
                FROM {marks_transfer_grade_log}";

        return $DB->get_record_sql($sql);
    }

    private function get_all_constructed_assessment_log_objects() {
        global $DB;

        $sql = "SELECT assessment_log_object
                FROM {marks_transfer_assess_log}";

        $assessment_log_object_records =  $DB->get_records_sql($sql);

        $assessment_log_objects = [];

        foreach ($assessment_log_object_records as $assessment_log_object_record) {
            $assessmentLogObjects[$assessment_log_object_record->id] = $assessment_log_object_record->assessment_log_object;
        }

        return $assessment_log_objects;
    }
}