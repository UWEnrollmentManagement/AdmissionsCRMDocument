<?php

require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../local-settings.php');
require_once(__DIR__ . '/Attacher.php');

use UWDOEM\Person\Person;
use UWDOEM\Person\Student;
use UWDOEM\AdmissionsCRMDocument\Attacher;

$attacher = new Attacher('https://' . CRM_HOST . '/' . CRM_BASE, CRM_USERNAME, CRM_PASSWORD);

//$person = Student::fromUWNetID('alexmac3');

//$syskey = $person->getAttr('StudentSystemKey');

$syskey = '001836743';

$attacher->attach($syskey, 'Waitlist Confirmation Form', '', '');

