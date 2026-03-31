<?php
include 'config.php';
include 'mailer.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

function getRecords($startDate, $last_saturday) {
    $spreadSheetContent = [];

    // checkin and checkout records from csv
    $lines = [];
    $files = glob('input/*.csv');
    foreach ($files as $file) {
        $lines = array_merge($lines, file($file));
    }
    foreach ($lines as $line) {
    $records[] = str_getcsv($line);
    }
    
    // find records for each user between last two weeks
    $users = [];
    $files = glob('users/*.json');
    foreach ($files as $file) {
        $users[] = array_merge([], json_decode(file_get_contents($file), true));
    }

    foreach ($users as $user) {
        if(!isset($user['id'])) {
            continue;
        }

        $userRecords = array_filter($records, function($row) use ($user) {
            return $row[1] === $user['name'];
        });

        $hours = 0;
        $previousRecordDate = null;

        foreach ($userRecords as $row) {

            if($row[2] >= $startDate && $row[2] <= $last_saturday && $row[1] === $user['name']) {
                if($previousRecordDate === $row[2]) continue;

                if( date('l', strtotime($row[2])) != "Saturday" && date('l', strtotime($row[2])) != "Sunday") {
                    $hours += $user['hours_per_day'];
                }
            }
            $previousRecordDate = $row[2];
        }

        // check if there are missed days
        if($hours !=  $user['hours_per_day'] * 10) {
            $notes = 'Hours: ' . $hours . ' (expected: ' . ($user['hours_per_day'] * 10) . '). ';
            $notes .= 'Missed days: ';
            for($i = $startDate; $i <= $last_saturday; $i = date('Y-m-d', strtotime($i . ' +1 day'))) {
                if(date('l', strtotime($i)) == "Saturday" || date('l', strtotime($i)) == "Sunday") continue;

                $checked = false;
                foreach ($userRecords as $row) {
                    if($row[2] === $i) {
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
            'ID' => (string)$user['id'],
            'User' => $user['name'],
            'Hours' => (string)$hours,
            'Vacation' => '0',
            'Cumulative' => '0',
            'Total' => (string)$hours,
            'Notes' => $notes
        ];
    }

    return $spreadSheetContent;
}

function createSpreadsheet($spreadSheetContent, $startDate, $last_saturday) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $period = 'Période du '.$startDate . ' au ' . $last_saturday;

    // Add content to sheet
    $sheet->fromArray(
        ['ID Employé', 'Nom', $period],
        NULL,
        'A1'
    );
    
    $sheet->fromArray(
        ['Heures régulières', 'Heures vacances anticipées', 'Heures vacances cumulées','Heures totales','Notes'],
        NULL,
        'C2'
    );
    $sheet->fromArray($spreadSheetContent, NULL, 'A3');
    
    // Styling sheet
    $sheet->mergeCells('C1:G1');
    $sheet->mergeCells('A1:A2');
    $sheet->mergeCells('B1:B2');

    $sheet->getStyle('A1:G2')
        ->getBorders()
        ->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN)
        ->getColor()->setARGB('FFFFFFFF');

    $sheet->getStyle('A3:G' . (count($spreadSheetContent) + 2))
        ->getBorders()
        ->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN)
        ->getColor()->setARGB('3c3c3c');

    $sheet->getStyle('A1:G2')
        ->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('3c3c3c');

    $sheet->getStyle('A1:G2')->applyFromArray([
        'font' => [
            'color' => ['argb' => 'FFFFFFFF'],
            'bold' => false,
            'size' => 12,
        ],
    ]);

    $sheet->getStyle('A1:G2')
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->getColumnDimension('A')->setWidth(12);
    $sheet->getColumnDimension('B')->setWidth(30);
    $sheet->getColumnDimension('C')->setWidth(20);
    $sheet->getColumnDimension('D')->setWidth(25);
    $sheet->getColumnDimension('E')->setWidth(25);
    $sheet->getColumnDimension('F')->setWidth(20);
    $sheet->getColumnDimension('G')->setWidth(30);

    $writer = new Xlsx($spreadsheet);

    $outputDir = 'biweekly_output';
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }

    $filename = $startDate . '_' . $last_saturday . '_reporte_quincenal.xlsx';
    $writer->save($outputDir . '/' . $filename);

    return $outputDir . '/' . $filename;
}

function main($conf) {
    // Check if script is executed every two weeks
    $file_flip = 'int.txt';

    $fp = fopen($file_flip, 'c+');
    if (flock($fp, LOCK_EX)) {
        $current = trim(fread($fp, filesize($file_flip)));
        $current = ($current === '') ? 0 : (int)$current;

        $new = ($current === 0) ? 1 : 0;

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, $new);

        fflush($fp);
        flock($fp, LOCK_UN);
    } else {
        fclose($fp);
        exit; 
    }

    fclose($fp);

    if ($new === 0) {
        exit; 
    }

    // dates for the last two weeks
    $last_saturday = date('Y-m-d', strtotime('last Saturday'));
    $startDate = date('Y-m-d', strtotime($last_saturday . ' -13 days'));

    // compute records
    $records = getRecords($startDate, $last_saturday);
    
    // create spreadsheet
    $filename = createSpreadsheet($records, $startDate, $last_saturday);

    // send mail
    $subject = "Rapport de temps bihebdomadaire";
    $bodyMail = "
    <div style='color: #34495E; padding:10px;'>
        <h2> Allo,</h2>
        <p>Veuillez trouver ci-joint votre rapport de temps bihebdomadaire.</p>
        <p>&Agrave; bient&ocirc;t,</p>
        <p>App<b>OX</b> <i>People</i></p>
    </div>";
    mailer("finance@appox.ai", $subject, $bodyMail, $conf['mailUsername'], $conf['mailPassword'], $conf['mailHost'], $filename);
}

main($conf);
?>