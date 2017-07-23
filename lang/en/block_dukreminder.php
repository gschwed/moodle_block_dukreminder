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
 * Strings for component 'block_dukreminder', language 'de'
 *
 * @package    block_dukreminder
 * @copyright  gtn gmbh <office@gtn-solutions.com>
 * @author	   Florian Jungwirth <fjungwirth@gtn-solutions.com>
 * @ideaandconcept Gerhard Schwed <gerhard.schwed@donau-uni.ac.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['blockstring'] = 'Kursabschlusserinnerung';
$string['newblock:addinstance'] = 'Add a newblock block';
$string['newblock:myaddinstance'] = 'Add a newblock block to my moodle';
$string['pluginname'] = 'Kursabschlusserinnerung';

$string['tab_course_reminders'] = 'Kurs-Erinnerungen';
$string['tab_new_reminder'] = 'Neue Erinnerung';

$string['form_title'] = 'interne Bezeichnung';
$string['form_subject'] = 'Email Betreff';
$string['form_subject_help'] = 'Betreff der Benachrichtungs-Email';
$string['form_text'] = 'Emailtext Teilnehmer';
$string['form_text_help'] = 'Hier wird der Text für den Bericht an die KursteilnehmerInnen erfasst. Folgende Platzhalter stehen zur Auswahl: ###username### wird in der E-Mail mit dem Namen des Teilnehmers ersetzt, ###usermail### mit dessen Mail und ###coursename### mit dem aktuellen Kurs.';
$string['form_text_teacher'] = 'Emailtext Trainer';
$string['form_text_teacher_help'] = 'Hier wird der Text für den Bericht an die KurstrainerInnen erfasst. Folgende Platzhalter stehen zur Auswahl: ###coursename### wird in der E-Mail mit dem Kursnamen ersetzt. ###users### wird durch eine Liste der benachrichtigten Kursteilnehmer ersetzt, und ###usercount### durch die Anzahl der benachrichtigten Teilnehmer';
$string['form_time'] = 'Sending Time';
$string['form_dateabsolute'] = 'absolutes Versanddatum';
$string['form_dateabsolute_help'] = 'Das absolute Versanddatum legt eine Deadline fest, an der die Erinnerungen versendet werden';
$string['form_daterelative'] = 'relative Versandzeit';
$string['form_daterelative_help'] = 'Die relative Versandzeit gibt an in welchem relativem Abstand zum gewählten Kriterium die Erinnerung versendet werden soll';
$string['form_daterelative_completion'] = 'Versand [Zeit] nach letztem Kursabschluss';
$string['form_to_status'] = 'Kursabschlusstatus';

$string['form_to_reporttrainer'] = 'Bericht an die Kurstrainer';
$string['form_to_reporttrainer_help'] = 'Diese Option bestimmt, ob der Bericht an die TrainerInnen im Kurs gesendet werden soll';
$string['form_to_reportsuperior'] = 'Report to superior (manager)';
$string['form_to_reportsuperior_help'] = 'Diese Option bestimmt, ob der Bericht an die/den Vorgesetzte(n) des Users gesendet werden soll.
    <br>Dazu muss die Emailadresse beim User im Feld ... eingetragen sein.';
$string['form_to_reportdirector'] = 'Report to directors';
$string['form_to_reportdirector_help'] = 'Diese Option bestimmt, ob der Bericht an die/den für den User zuständige Direktor/in gesendet werden soll.
    <br>Dazu muss die Emailadresse beim User im Feld ... eingetragen sein.';

$string['form_to_groups'] = 'Empfängergruppen';
$string['form_to_mail'] = 'Bericht an sonstige Email Empfänger';
$string['form_to_mail_help'] = 'Hier können mit ; getrennte E-Mail-Adressen als sonstige Empfänger für den Trainer-Bericht definiert werden';
$string['form_mailssent'] = 'Bisher versandte Erinnerungen';
$string['form_delete'] = 'Wirklich löschen?';
$string['form_criteria'] = 'Benachrichtigungskriterium';
$string['form_criteria_help'] = 'Es stehen verschiedene Kriterien zur Verfügung. Bei Unklarheiten sind in der Block-Dokumentation ausführliche Informationen vorhanden und Anwendungsfälle beschrieben.';

$string['form_header_general'] = 'Allgemeines';
$string['form_header_time'] = 'Zeit';
$string['form_header_criteria'] = 'Kriterium';
$string['form_header_groups'] = 'Gruppenfilter';
$string['form_header_report'] = 'Berichtsoptionen';

$string['form_to_status_all'] = 'Alle';
$string['form_to_status_completed'] = 'Mit Abschluss';
$string['form_to_status_notcompleted'] = 'Ohne Abschluss';

$string['daterelative_error'] = 'Es ist kein negativer Wert hier erlaubt';
$string['criteria_error'] = 'Eine Kombination aus absolutem Datum und dem Kriterium Kurseinschreibung kann nicht verwendet werden';
$string['criteria_error2'] = 'Eine Kombination aus relativer Zeitspanne und dem Kriterium Alle kann nicht verwendet werden';
$string['to_mail_error'] = 'Es ist eine ungültige E-Mail-Adresse angegeben worden! Die Adressen müssen mit ; getrennt werden';

$string['email_teacher_notification'] = 'Soeben wurden folgende {$a->amount} Personen im Kurs {$a->course} erinnert:';

$string['criteria_all'] = 'Alle';
$string['criteria_completion'] = 'Kursabschluss';
$string['criteria_enrolment'] = 'Kurseinschreibung';
$string['criteria_activity_grade'] = 'Passsing grade for';