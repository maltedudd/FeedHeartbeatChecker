<?php
include("config.php");
$datenbank = $baseUrl . "/db/datenbank.db";

// Create a new database, if the file doesn't exist and open it for reading/writing.
// The extension of the file is arbitrary.
$db = new SQLite3($datenbank, SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);


// Roll over items from config file
foreach ($feeds as $feed) {
  $stmt = $db->prepare("SELECT id, feed, lastcheck, intervall, guid, alarm, name
  FROM feeds
  where feed = :1");
  $stmt->bindValue(":1", $feed[1], SQLITE3_TEXT);
  $results = $stmt->execute();
  $insertcounter = 0;

  // Check with Items from Database
  while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
    $insertcounter++;
    $id = $row['id'];
    $feedurl = $row['feed'];
    $lastcheck = $row['lastcheck'];
    $guid = $row['guid'];
    $intervall = $row['intervall'];
    $alarm = $row['alarm'];
    $name = $row['name'];
    $checktime = ($lastcheck + ($intervall));
    $time = time();
    $rawXML = (file_get_contents($feedurl));
    $xml = simplexml_load_string($rawXML, 'SimpleXMLElement', LIBXML_NOCDATA);
    $entries = $xml->channel->item;
    $counter = 0;

    // Check if feed is empty
    if (count($entries) === 0) {
      if ($alarm == 0) {
        sendTelegram("Folgender Feed hat keine items: " . $feedurl);
        $db->exec("UPDATE feeds SET alarm=1 WHERE id='" . $id . "'");
      }
    } else {
      foreach ($entries as $root) {
        $max_entries = 1;
        $counter++;
        if ($counter > $max_entries) {
          break;
        }
        if ($guid != $root->guid) {
          $guid = $root->guid;
          $db->exec("UPDATE feeds SET lastcheck='" . $time . "', guid='" . $guid . "', alarm = 0  WHERE id='" . $id . "'");
          if ($alarm == 1) {
            sendTelegram("Folgender Feed wurde wieder aktualisiert: " . $feedurl);
          }
        } else {
          if (time() >= $checktime) {
            if ($alarm == 0) {
              sendTelegram("Folgender Feed wurde zu lang nicht aktualisiert: " . $feedurl);
              $db->exec("UPDATE feeds SET alarm=1 WHERE id='" . $id . "'");
            }
          }
        }
      }
    }
  }

  // Add Items from Config-File to Database
  if ($insertcounter == 0) {
    $stmt = $db->prepare("Insert into feeds (feed, intervall, name, alarm, guid,lastcheck) values (:1,:2,:0,0,0,0)");
    $stmt->bindValue(":1", $feed[1], SQLITE3_TEXT);
    $stmt->bindValue(":2", $feed[2], SQLITE3_INTEGER);
    $stmt->bindValue(":0", $feed[0], SQLITE3_TEXT);
    $q = $stmt->execute();
  }
}

// Clear Database from Items, which are not in the config file
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