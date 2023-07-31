<?php
include("config.php");
$datenbank = $baseUrl."/db/datenbank.db";

// Create a new database, if the file doesn't exist and open it for reading/writing.
// The extension of the file is arbitrary.
$db = new SQLite3($datenbank, SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);
$max_entries = 1;

foreach ($feeds as $feed) {
  $stmt = $db->prepare("SELECT id, feed, lastcheck, intervall, guid, alarm, name
  FROM feeds
  where name = :0");
  $stmt->bindValue(":0", $feed[0], SQLITE3_TEXT);
  $results = $stmt->execute();
  if (empty($results)) {
    $stmt = $db->prepare("Insert into feeds (feed, intervall, name, alarm, guid) values (:1,:3,:0,0,0)");
    $stmt->bindValue(":1", $feed[1], SQLITE3_INTEGER);
    $stmt->bindValue(":0", $feed[0], SQLITE3_TEXT);
    $stmt->bindValue(":3", $feed[3], SQLITE3_TEXT);
    $q = $stmt->execute();

  } else {
    while ($row = $results->fetchArray()) {
      $id = $row['id'];
      $feed = $row['feed'];
      $lastcheck = $row['lastcheck'];
      $guid = $row['guid'];
      $intervall = $row['intervall'];
      $alarm = $row['alarm'];
      $name = $row['name'];

      $checktime = ($lastcheck + ($intervall));
      $time = time();
      $rawXML = (file_get_contents($feed));
      $xml = simplexml_load_string($rawXML, 'SimpleXMLElement', LIBXML_NOCDATA);
      $entries = $xml->channel->item;
      $counter = 0;
      if (count($entries) === 0) {
        if ($alarm == 0) {
          sendTelegram("Folgender Feed hat keine items: " . $feed);
          $db->exec("UPDATE feeds SET alarm=1 WHERE id='" . $id . "'");
        }
      } else {
        foreach ($entries as $root) {
          $counter++;
          if ($counter > $max_entries) {
            break;
          }
          if ($root->guid == "") {
            $root->guid = $root->title;
          }
          if ($guid != $root->guid) {
            $guid = $root->guid;
            $db->exec("UPDATE feeds SET lastcheck='" . $time . "', guid='" . $guid . "', alarm = 0  WHERE id='" . $id . "'");
            if ($alarm == 1) {
              echo "Entwarnungsmail versenden" . PHP_EOL;
              sendTelegram("Folgender Feed wurde wieder aktualisiert: " . $feed);
            }
          } else {
            if (time() >= $checktime) {
              if ($alarm == 0) { 
                sendTelegram("Folgender Feed wurde zu lang nicht aktualisiert: " . $feed);
                $db->exec("UPDATE feeds SET alarm=1 WHERE id='" . $id . "'");
              }
            }
          }
        }
      }

    }
  }

  //Clar Database from Items, which are not in the config file
  $sdbresults = $db->query("SELECT id, feed, lastcheck, intervall, guid, alarm, name
  FROM feeds");
  while ($row = $sdbresults->fetchArray()) {
    $delete = 1;
    foreach ($feeds as $feed) {

      if ($row['name'] == $feed[0]) {
        $delete = 0;
      }

    }
    if ($delete == 1) {
      $stmt = $db->prepare("DELETE FROM feeds  WHERE name = :0");
      $stmt->bindValue(":0", $row['name'], SQLITE3_TEXT);
      $q = $stmt->execute();
      echo ($row['name'] . " nicht in Config-File gefunden. Lösche Eintrag in Datenbank");
    }
  }

}



function sendTelegram($message)
{
  global $apiToken;
  global $chatId;

  $data = [
    'chat_id' => $chatId,
    'text' => $message
  ];
  $response = file_get_contents("https://api.telegram.org/bot$apiToken/sendMessage?" . http_build_query($data));
}

?>