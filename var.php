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

function WorkingDays($start_Working_Date,$end_Working_Date){
    // Count how many days are working days between two dates
    $workingDays = 0;
    for($date = $start_Working_Date; $date <= $end_Working_Date; $date = date('Y-m-d', strtotime($date . ' +1 day'))) {
        if(isHoliday($date)) continue;

        if(date('l', strtotime($date)) != "Saturday" && date('l', strtotime($date)) != "Sunday") {
            $workingDays++;
        }
    }
    return $workingDays;
}