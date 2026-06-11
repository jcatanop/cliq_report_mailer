<?php
function isHoliday($date) {
    // Load holidays from JSON file and check if $date is in the array

    $holidays = json_decode(file_get_contents('holidays.json'), true);
    if ($date === null) {
        return false;
    }

    if (in_array($date, $holidays)) {
        return true;
    }
    return false;
}

//var_dump(isHoliday('2026-05-03'));
