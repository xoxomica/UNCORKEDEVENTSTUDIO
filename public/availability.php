<?php
require __DIR__.'/../app/bootstrap.php';header('Content-Type: application/json');
$venue=filter_input(INPUT_GET,'venue',FILTER_VALIDATE_INT);$date=$_GET['date']??'';$start=$_GET['start']??'';$end=$_GET['end']??'';
if(!$venue||!is_string($date)||!is_string($start)||!is_string($end)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$start)||!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$end)||$end<=$start){http_response_code(422);echo json_encode(['message'=>'Choose a venue, date, start time, and later end time first.']);exit;}
if(!run('SELECT id FROM venues WHERE id=? AND active=1',[$venue])->fetch()){http_response_code(422);echo json_encode(['message'=>'Venue is unavailable.']);exit;}
$busy=run("SELECT COUNT(*) FROM reservations WHERE venue_id=? AND date=? AND status IN ('Confirmed','Completed') AND start<? AND end>?",[$venue,$date,$end,$start])->fetchColumn();
echo json_encode(['available'=>!$busy,'message'=>$busy?'This schedule is already booked. Please choose another time.':'No confirmed booking overlaps this schedule. Availability is subject to staff confirmation.']);
