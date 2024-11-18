<?php

namespace local_obu_banner_marks_transfer\task;

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
 * Adhoc task to prepare our marks transfer tables with CoSector table updates
 *
 * @package    local_obu_banner_marks_transfer
 * @author     Emir Kamel
 * @copyright  2024, Oxford Brookes University {@link http://www.brookes.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_obu_banner_marks_transfer\handlers\prepare_marks_transfer_handler;
use text_progress_trace;

global $CFG;
require_once($CFG->dirroot . '/local/obu_banner_marks_transfer/locallib.php');

class prepare_marks_transfer extends \core\task\scheduled_task {

    public function get_name() : string {

        return "Prepare marks transfer task";
    }

    public function execute() {

        $trace = new text_progress_trace();
        $handler = new prepare_marks_transfer_handler($trace);

        $handler->handle_prepare_marks_transfer();
    }
}