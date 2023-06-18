<?php

use Crunz\Schedule;

$schedule = new Schedule();

$task = $schedule->run('php8.2 bin/console invoice:send');
$task->description('Send Invoice Task')
    ->everyThirtyMinutes()
    ->preventOverlapping();

return $schedule;
