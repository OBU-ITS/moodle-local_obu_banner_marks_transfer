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

    private marks_transfer_service $service;
    private progress_trace $trace;

    public function __construct($trace) {

        $this->trace = $trace;
        $this->service = marks_transfer_service::getInstance();
    }

    public function handle_marks_transfer_service() {

        $records = $this->service->get_pending_records();
        if (count($records) == 0) {
            $this->trace->output("No pending/failed records in log tables found.");
            return;
        }

        $this->service->run_marks_transfer($this->trace, $records);
    }
}