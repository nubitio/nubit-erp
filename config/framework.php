<?php
return static function (FrameworkConfig $framework) {
    $framework
        ->secret('%env(APP_SECRET)%')
        ->profiler()
        ->onlyExceptions(false);
};