<?php

use Crunz\Schedule;

$schedule = new Schedule();

$task = $schedule->run('php bin/console app:send-email');
$task->description('Send Email Task')
    ->dailyAt('09:00')
    ->preventOverlapping();

return $schedule;