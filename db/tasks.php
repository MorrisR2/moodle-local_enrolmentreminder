<?php

$tasks = array(
    array(
        'classname' => 'local_enrolmentreminder\task\send_reminders',
        'blocking' => 0,
        'minute' => '*/10',
        'hour' => '8-19',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*'
    )
);
