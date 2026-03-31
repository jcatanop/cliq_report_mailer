<?php
include 'config.php';
include 'mailer.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

function getRecords($startDate, $endDate) {
    $spreadSheetContent = [];

    // Get checkin and checkout records from csv
    $lines = [];
    $files = glob('input/*.csv');
    foreach ($files as $file) {
        $lines = array_merge($lines, file($file));
    }
    foreach ($lines as $line) {
        $records[] = str_getcsv($line);
    }

    $users = [];
    $files = glob('users/*.json');
    foreach ($files as $file) {
        $users[] = array_merge([], json_decode(file_get_contents($file), true));
    }

    $contractCodes = [];
    foreach ($users as $user) {
        if(!isset($user['agreements'])) {
            continue;
        }
        foreach ($user['agreements'] as $user_contract) {
            $contractCodes[] = $user_contract['contract'];
        }
    }

    $contractCodes = array_unique($contractCodes);

    foreach ($contractCodes as $agreement) {

        foreach ($users as $user) {
            if(!isset($user['agreements'])) {
                continue;
            }

            foreach ($user['agreements'] as $user_contract) {
                // User belongs to this agreement
                if($user_contract['contract'] === $agreement) {

                    $userRecords = array_filter($records, function($row) use ($user) {
                        return $row[1] === $user['name'];
                    });

                    $hours = 0;
                    $days = 0;
                    $previousRecordDate = null;

                    foreach ($userRecords as $row) {

                        if(
                            $row[2] >= $user_contract['start_date'] 
                            && $row[2] <= $user_contract['end_date'] 
                            && $row[2] >= $startDate 
                            && $row[2] <= $endDate
                        ) {
                            if($previousRecordDate === $row[2]) continue;

                            if( date('l', strtotime($row[2])) != "Saturday" && date('l', strtotime($row[2])) != "Sunday") {
                                $hours += $user_contract['hours_per_day'];
                                $days++;
                            }
                        }
                        $previousRecordDate = $row[2];
                    }
                    
                    // Calculate working days
                    $workingDays = 0;
                    $start_Working_Date = $user_contract['start_date'] >= $startDate ? $user_contract['start_date'] : $startDate;
                    $end_Working_Date = $user_contract['end_date'] <= $endDate ? $user_contract['end_date'] : $endDate;
                    for($date = $start_Working_Date; $date <= $end_Working_Date; $date = date('Y-m-d', strtotime($date . ' +1 day'))) {
                        if(date('l', strtotime($date)) != "Saturday" && date('l', strtotime($date)) != "Sunday") {
                            $workingDays++;
                        }
                    }

                    // check if there are missed days
                    if($days !=  $workingDays) {
                        $notes = 'Days: ' . $days . ' (expected: ' . $workingDays . '). ';
                        $notes .= 'Missed days: ';
                        for($i = $start_Working_Date; $i <= $end_Working_Date; $i = date('Y-m-d', strtotime($i . ' +1 day'))) {
                            if(date('l', strtotime($i)) == "Saturday" || date('l', strtotime($i)) == "Sunday") continue;

                            $checked = false;
                            foreach ($userRecords as $row) {
                                if($row[2] === $i
                                && $row[2] >= $user_contract['start_date'] 
                                && $row[2] <= $user_contract['end_date'] 
                                && $row[2] >= $startDate 
                                && $row[2] <= $endDate) {
                                    $checked = true;
                                    break;
                                }
                            }
                            if(!$checked) {
                                $notes .= date('d', strtotime($i)).', ';
                            }
                        }
                    } else {
                        $notes = '';
                    }

                    $spreadSheetContent[] = [
                        'User' => $user['name'],
                        'Contract' => $user_contract['contract'],
                        'hours_per_day' => $user_contract['hours_per_day'],
                        'days' => (string)$days,
                        'Hours' => (string)$hours,
                        'Notes' => $notes
                    ];

                }
            }

        }
    }
    return $spreadSheetContent;
}

function createSpreadsheet($spreadSheetContent, $startDate, $endDate) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $month = date('m', strtotime($startDate));
    $year = date('Y', strtotime($startDate));

    $period = getMonthName($month) . ' ' . $year;

    // Add content to sheet
    $sheet->fromArray(
        ['Nom','Client-code-contrat', $period],
        NULL,
        'A1'
    );
    
    $sheet->fromArray(
        ['Heures per jour', 'Jours', 'Heures totales'],
        NULL,
        'C2'
    );
    $sheet->fromArray($spreadSheetContent, NULL, 'A3');
    
    // Styling sheet
    $sheet->mergeCells('C1:E1');
    $sheet->mergeCells('A1:A2');
    $sheet->mergeCells('B1:B2');

    $sheet->getStyle('A1:E2')
        ->getBorders()
        ->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN)
        ->getColor()->setARGB('FFFFFFFF');

    $sheet->getStyle('A3:E' . (count($spreadSheetContent) + 2))
        ->getBorders()
        ->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN)
        ->getColor()->setARGB('3c3c3c');

    $sheet->getStyle('A1:E2')
        ->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('3c3c3c');

    $sheet->getStyle('A1:E2')->applyFromArray([
        'font' => [
            'color' => ['argb' => 'FFFFFFFF'],
            'bold' => false,
            'size' => 12,
        ],
    ]);

    $sheet->getStyle('A1:E2')
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->getColumnDimension('A')->setWidth(30);
    $sheet->getColumnDimension('B')->setWidth(25);
    $sheet->getColumnDimension('C')->setWidth(15);
    $sheet->getColumnDimension('D')->setWidth(15);
    $sheet->getColumnDimension('E')->setWidth(15);

    $writer = new Xlsx($spreadsheet);

    $outputDir = 'monthly_output';
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }

    $filename = $year . '_' . $month . '_reporte_mensual.xlsx';
    $writer->save($outputDir . '/' . $filename);

    return $outputDir . '/' . $filename;
}

function getMonthName($month) {
    $months = [
        '01' => 'Janvier',
        '02' => 'Février',
        '03' => 'Mars',
        '04' => 'Avril',
        '05' => 'Mai',
        '06' => 'Juin',
        '07' => 'Juillet',
        '08' => 'Août',
        '09' => 'Septembre',
        '10' => 'Octobre',
        '11' => 'Novembre',
        '12' => 'Décembre'
    ];
    return $months[$month];
}

function removeTempFile(){
    $current_date = date('Y-m-d');
    $file_name = $current_date . '_cliq_snapshot.csv';
    $dayOfWeek = date('w', strtotime($current_date));

    // Check if file exists and is not monday (1), then remove it
    if ($dayOfWeek !== '1' && file_exists('input/' . $file_name)) {
        unlink('input/' . $file_name);
    }
}

function main($conf) {
    $firstDayOfMonth = date('Y-m-d', strtotime('first day of previous month'));
    $lastDayOfMonth = date('Y-m-d', strtotime('last day of previous month'));
    
    // compute records
    $spreadsheetContent = getRecords($firstDayOfMonth, $lastDayOfMonth);

    // create spreadsheet
    $filename = createSpreadsheet($spreadsheetContent, $firstDayOfMonth, $lastDayOfMonth);

    // send mail
    $subject = "Rapport mensuel des temps pour facturation";
    $bodyMail = "
    <div style='color: #34495E; padding:10px;'>
        <h2> Allo,</h2>
        <p>Veuillez trouver ci-joint votre rapport mensuel des temps, pr&eacute;par&eacute; pour la facturation.</p>
        <p>&Agrave; bient&ocirc;t,</p>
        <p>App<b>OX</b> <i>People</i></p>
    </div>";
    mailer("jcatano@appox.ai", $subject, $bodyMail, $conf['mailUsername'], $conf['mailPassword'], $conf['mailHost'], $filename);

    removeTempFile();
}

main($conf);
?>