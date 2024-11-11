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

//TODO:: function to build assessment log record

//TODO:: function to build grade transfer log record

//TODO:: function to make ethos calls

//TODO:: function to store records in history table

function local_obu_banner_marks_transfer_deconstruct_group_name (\progress_trace $trace, $group_name) {
    $pattern = "/^(?P<courseAcademicYear>\d{4})\.(?P<courseSubjectCodeAndNumber>.+?)_"
        . "(?P<coursePartTerm>.+?)_(?P<courseRunNumber>\d+)_"
        . "(?P<termCode>\d{6})_(?P<crn>\d+)_"
        . "(?P<courseworkName>.+?)-(?P<courseworkSequenceNumber>\d+)_"
        . "(?P<componentId>\d+)_"
        . "(?P<currentReason>.{1,2})$/";

    if (preg_match($pattern, $group_name, $matches)) {
        return (object) [
            'courseAcademicYear' => $matches['courseAcademicYear'],
            'courseSubjectCodeAndNumber' => $matches['courseSubjectCodeAndNumber'],
            'coursePartTerm' => $matches['coursePartTerm'],
            'courseRunNumber' => $matches['courseRunNumber'],
            'termCode' => $matches['termCode'],
            'crn' => $matches['crn'],
            'courseworkName' => $matches['courseworkName'],
            'courseworkSequenceNumber' => $matches['courseworkSequenceNumber'],
            'componentId' => $matches['componentId'],
            'currentReason' => $matches['currentReason'],
        ];
    } else {
        $trace->output("Group name format is invalid.");
    }
}

