<?php
//**
//  URL REMINDER: /local/obu_banner_marks_transfer/test/ethos_test.php
//  */

namespace local_obu_banner_marks_transfer\test;

use enrol_ethos\ethosclient\entities\ethos_student_gradable_components_subcomponents_info;
use enrol_ethos\ethosclient\entities\ethos_student_gradable_components_subcomponents_info_grade;
use enrol_ethos\ethosclient\providers\ethos_student_gradable_components_subcomponents_provider;

require_once(__DIR__ . '/../../../config.php');

defined('MOODLE_INTERNAL') || die();

if (!is_siteadmin()) {
    redirect(new \moodle_url('/'));
    die();
}

$trace = new \html_progress_trace();

$info = new ethos_student_gradable_components_subcomponents_info();
$info->assessmentType = "";
$info->crn = "";
$info->term = "";
$info->componentId = "";

$grade1 = new ethos_student_gradable_components_subcomponents_info_grade();
$grade1->bannerId = "";
$grade1->completedDate = "";
$grade1->currentReason = "";
$grade1->extensionDate = "";
$grade1->score = "88";
$grade1->grade = "";
$grade1->comment = "";

$info->setGrade($grade1);

$grade2 = new ethos_student_gradable_components_subcomponents_info_grade();
$grade2->bannerId = "";
$grade2->completedDate = "";
$grade2->currentReason = "";
$grade2->extensionDate = "";
$grade2->score = "99";
$grade2->grade = "";
$grade2->comment = "";

$info->setGrade($grade2);

$provider = ethos_student_gradable_components_subcomponents_provider::getInstance();
$resp = $provider->put($info);

var_dump($resp);