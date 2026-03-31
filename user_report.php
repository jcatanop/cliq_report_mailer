<?php
include 'config.php';
include 'mailer.php';

$current_date = date('Y-m-d');

// Get checkin and checkout records from csv file 
$file_name = $current_date . '_cliq_snapshot.csv';
// Check if file exists, if not sent mail to notify
if (!file_exists('input/' . $file_name)) {
  $subject = "Cliq reports: File not found";
  $bodyMail = "File " . $file_name . " not found.<br>Please check if the file exists in the input folder in order to send the reports to the users.";
  mailer("support@appox.ai", $subject, $bodyMail, $conf['mailUsername'], $conf['mailPassword'], $conf['mailHost'],"");
  exit;
}
$cliq_data = file('input/' . $file_name);
$data = [];
foreach ($cliq_data as $line) {
  $data[] = str_getcsv($line);
  }

// Get users
$users = [];
$files = glob('users/*.json');
foreach ($files as $file) {
    $users[] = array_merge([], json_decode(file_get_contents($file), true));
}

// Consolidate report for each user
foreach ($users as $user) {

  $userData = [];     // get checkin and checkout records from the csv file
  foreach ($data as $i) {
    if ($i[1] === $user->name) {
      $userData[] = [
        "name" => $i[1],
        "date" => $i[2],
        "checkin" => $i[3],
        "checkout" => $i[4],
        "interval" => $i[5],
      ];
    }
  }

  // total hours worked in the records
  $total = 0;
  foreach ($userData as $interval) {
    if($interval['interval'] === "NaN ") continue;
    $hours = floatval($interval['interval']);
    $total += $hours;
  }

  // dates worked
  $dates = [];
  foreach ($userData as $date) {
    $dates[] = $date["date"];
  }
  $dates = array_unique($dates);

  // dates not worked last week
  $last_friday = date('Y-m-d', strtotime('last friday'));
  $last_monday = date('Y-m-d', strtotime($last_friday . ' - 4 days'));

  $missingDates = [];
  for($i=$last_monday; $i <= $last_friday; $i = date('Y-m-d', strtotime($i . ' + 1 day'))) {
    if (!in_array($i, $dates)) {
      $missingDates[] = date('d', strtotime($i));
    }
  }

  // hours worked by day
  $dailyRecord = [];
  foreach ($dates as $day) {
    $sumDay = 0;
    foreach ($userData as $record) {
      if ($day === $record["date"] && $record['interval'] != "NaN ")
      $sumDay += $record["interval"];
    }
    $dailyRecord[] = [
      "name" => $user->name,
      "date" => $day,
      "hours" => $sumDay
    ];
  }

  $name = explode(" ", $user->name);

  // SEND MAIL
  $subject = $name[0] . ": check-in et check-out sur Cliq";
  $bodyMail = "
  <div style='color: #34495E; padding:10px;'>
      <h2> Allo " . $name[0] . ",</h2>
      <p>On tient &agrave; assurer un bon &eacute;quilibre entre vie pro et perso, et dans cette optique, voici le rapport Cliq de la semaine :</p>";

  if(!empty($missingDates)) {
    $bodyMail .= "<p><b style='color: red;'>Attention</b>: vous n'avez pas enregistr&eacute; vos heures pour les dates suivantes : "; 
    $count = count($missingDates);
    $c=0;
    foreach($missingDates as $date) {
      $c++;
      $bodyMail .= $date;
      if($c < $count-1) {
        $bodyMail .= ", ";
      }else if($c === $count-1){
        $bodyMail .= " et ";
      }
    }
    $bodyMail .= ". Nous aimerions confirmer si vous avez bien travaill&eacute; ces jours-l&agrave;</p>";
  }


  $bodyMail .= "<h4>Temps accumul&eacute;s par jour</h4>
      <table width='500px'  style='border: 1px solid black; border-collapse: collapse;'>
        <head>
          <tr>
            <th width='40%' style='border: 1px solid black; border-collapse: collapse;'>Date</th>
            <th width='20%' style='border: 1px solid black; border-collapse: collapse;'>Heures</th>
          </tr>
        </head>";

  foreach ($dailyRecord as $i) {
    if ($i["name"] === $user->name) {
      $bodyMail .= "
            <tr>
              <td align='center' style='border: 1px solid black; border-collapse: collapse;'>$i[date]</td>
              <td align='center' style='border: 1px solid black; border-collapse: collapse;'>$i[hours]</td>
            </tr>";
    }
  }
  $bodyMail .= "
         <tr>
           <td align='center' style='border: 1px solid black; border-collapse: collapse;'><b>Total</b></td>
           <td align='center' style='border: 1px solid black; border-collapse: collapse;'>$total heures</td>
         </tr>";
  $bodyMail .= "
      </table>

      <h4>Enregistrements individuels</h4>
      <table width='500px'  style='border: 1px solid black; border-collapse: collapse;'>
        <head>
          <tr>
            <th width='40%' style='border: 1px solid black; border-collapse: collapse;'>Date</th>
            <th width='20%' style='border: 1px solid black; border-collapse: collapse;'>Checkin</th>
            <th width='20%' style='border: 1px solid black; border-collapse: collapse;'>Checkout</th>
            <th width='20%' style='border: 1px solid black; border-collapse: collapse;'>temps</th>
          </tr>
        </head>";

  $nonCheckout = 0;
  foreach ($userData as $i) {
    if ($i["name"] === $user->name) {
      $bodyMail .= "
            <tr>
              <td align='center' style='border: 1px solid black; border-collapse: collapse;'>$i[date]</td>
              <td align='center' style='border: 1px solid black; border-collapse: collapse;'>$i[checkin]</td>
              <td align='center' style='border: 1px solid black; border-collapse: collapse; color: " . ($i["checkout"] === "NaN:NaN" || date("H:i", strtotime($i["checkout"])) < date("H:i", strtotime($i["checkin"])) ? "red" : "") . ";'>$i[checkout]</td>
              <td align='center' style='border: 1px solid black; border-collapse: collapse; color: " . ($i["interval"] === "NaN " || date("H:i", strtotime($i["checkout"])) < date("H:i", strtotime($i["checkin"])) ? "red" : "") . ";'>$i[interval] heures</td>
            </tr>";
      if ($i["checkout"] === "NaN:NaN" || date("H:i", strtotime($i["checkout"])) < date("H:i", strtotime($i["checkin"]))) {
        $nonCheckout++;
      }
    }
  }
  $bodyMail .= "
         <tr>
           <td align='center' style='border: 1px solid black; border-collapse: collapse;'></td>
           <td align='center' style='border: 1px solid black; border-collapse: collapse;'></td>
           <td align='center' style='border: 1px solid black; border-collapse: collapse;'><b>TOTAL</b></td>
           <td align='center' style='border: 1px solid black; border-collapse: collapse;'>$total hours</td>
         </tr>";
  $bodyMail .= "
      </table>";
      
  if ($nonCheckout > 0) {
    $bodyMail .= "<p>Vous avez $nonCheckout enregistrement(s) sans checkout &agrave; temps.. Veuillez r&eacute;pondre &agrave; ce courriel &agrave; l'adresse people@appox.ai
 afin de r&eacute;gulariser votre situation.</p>";
  }
  
  $bodyMail .= "
      <p>Merci pour ta constance avec Cliq, c'est vraiment appr&eacute;ci&eacute; !</p>
      <p>&Agrave; bient&ocirc;t,</p>
      <p>App<b>OX</b> <i>People</i></p>
  </div>";
  mailer($user->mail, $subject, $bodyMail, $conf['mailUsername'], $conf['mailPassword'], $conf['mailHost'],"");

  echo "Alert: mail sent \n";

}
?>
