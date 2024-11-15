<?php

namespace local_obu_banner_marks_transfer\handlers;

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

use local_obu_banner_marks_transfer\services\marks_transfer_service;
use progress_trace;

class marks_transfer_handler {
    private marks_transfer_service $marks_transfer_service;

    private progress_trace $trace;

    public function __construct($trace) {
        $this->marks_transfer_service = marks_transfer_service::getInstance();
        $this->trace = $trace;
    }

    public function handle_marks_transfer_service() {
        $untransferred_grade_records = $this->marks_transfer_service->get_pending_records();
        if (count($untransferred_grade_records) == 0) {
            $this->trace->output("No pending/failed records in log tables found.");
        } else {
            $this->marks_transfer_service->marks_transfer($this->trace, $untransferred_grade_records);
            //TODO:: make function above return a result stuff that has transferred and stuff that has failed and report outcomes with trace
            //TODO:: log result in history table (locallib function)
            $this->trace->output("Transferred" . count($untransferred_grade_records) . " grade transfer records.");
        }
    }
}