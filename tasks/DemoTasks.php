<?php

use Crunz\Schedule;

$schedule = new Schedule();

$task = $schedule->run('php bin/console app:run-demo');
$task->description('Demo Task')
    ->everyFiveMinutes()
    ->preventOverlapping();

return $schedule;