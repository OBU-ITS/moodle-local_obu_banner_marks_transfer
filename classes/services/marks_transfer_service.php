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

        // TODO : Retrieve everything from marks_transfer_grade_log table where the status is "pending" or "failed"
        $sql = "";

        return $DB->get_records_sql($sql);
    }

    public function run_marks_transfer(progress_trace $trace, $untransferred_grade_records) : void {
        global $DB;
        foreach ($untransferred_grade_records as $untransferred_grade_record) {
            // TODO : attempt to send ethos message here return the results as part of array of successes and failures
        }
    }

}