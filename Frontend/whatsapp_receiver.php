<?php
// whatsapp_receiver.php

// Es ist eine gute Praxis, die Zeitzone explizit zu setzen.
date_default_timezone_set('Europe/Berlin');

/**
 * Schreibt eine formatierte Nachricht in eine Log-Datei.
 * @param string $message Die zu loggende Nachricht.
 * @param string $logFile Die Zieldatei.
 */
function writeToLog($message, $logFile = 'messages.log') {
    // Fügt einen Zeitstempel zur Nachricht hinzu.
    $logEntry = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $message);
    // Schreibt die Nachricht in die Datei (im Anfüge-Modus).
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// 1. Rohdaten aus der Anfrage lesen
$input = file_get_contents('php://input');
if (empty($input)) {
    http_response_code(400);
    writeToLog("Fehler: Leerer Anfragekörper empfangen.");
    echo json_encode(["status" => "error", "message" => "Leerer Anfragekörper"]);
    exit;
}

// 2. JSON-Daten dekodieren
$data = json_decode($input, true); // true für assoziatives Array
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    writeToLog("Fehler: Ungültiges JSON empfangen. Details: " . json_last_error_msg());
    echo json_encode(["status" => "error", "message" => "Ungültiges JSON"]);
    exit;
}

// 3. Wichtige Datenfelder validieren
if (!isset($data['from'], $data['type'], $data['body'])) {
    http_response_code(400);
    writeToLog("Fehler: Erforderliche Felder (from, type, body) fehlen. Empfangen: " . $input);
    echo json_encode(["status" => "error", "message" => "Fehlende Pflichtfelder"]);
    exit;
}

// 4. Nachricht je nach Typ verarbeiten
$from = $data['from'];
$type = $data['type'];
$body = $data['body']; // Bei Text: Nachricht, bei Bild: Bildunterschrift

switch ($type) {
    case 'text':
        // Bei Textnachrichten loggen wir einfach den Inhalt.
        writeToLog("Text von '{$from}' empfangen. Nachricht: '{$body}'");
        break;

    case 'image':
        // Bei Bildnachrichten dekodieren und speichern wir das Bild.
        if (!isset($data['media'], $data['mimetype'])) {
            http_response_code(400);
            writeToLog("Fehler: Bildnachricht von '{$from}' fehlt 'media' oder 'mimetype'.");
            echo json_encode(["status" => "error", "message" => "Bilddaten fehlen"]);
            exit;
        }

        $media_b64 = $data['media'];
        $mimetype = $data['mimetype']; // z.B. "image/jpeg"
        $caption = $body;

        // Base64-String dekodieren
        $imageData = base64_decode($media_b64);
        if ($imageData === false) {
            http_response_code(500);
            writeToLog("Fehler: Konnte Base64-Bild von '{$from}' nicht dekodieren.");
            echo json_encode(["status" => "error", "message" => "Base64-Dekodierung fehlgeschlagen"]);
            exit;
        }

        // Upload-Verzeichnis erstellen, falls es nicht existiert
        $uploadDir = 'uploads';
        if (!is_dir($uploadDir)) {
            // 0755 sind typische Berechtigungen für Verzeichnisse
            mkdir($uploadDir, 0755, true);
        }

        // Eindeutigen Dateinamen generieren, um Überschreibungen zu vermeiden
        $extension = pathinfo($mimetype, PATHINFO_EXTENSION) ?: 'jpg';
        $filename = sprintf('%s/%s-%s.%s', $uploadDir, time(), uniqid(), $extension);

        // Datei speichern
        if (file_put_contents($filename, $imageData) === false) {
            http_response_code(500);
            writeToLog("Fehler: Konnte Bilddatei '{$filename}' nicht auf die Festplatte schreiben.");
            echo json_encode(["status" => "error", "message" => "Speichern der Datei fehlgeschlagen"]);
            exit;
        }

        writeToLog("Bild von '{$from}' empfangen. Untertitel: '{$caption}'. Gespeichert unter: '{$filename}'");
        break;

    default:
        writeToLog("Warnung: Unbekannter Nachrichtentyp '{$type}' von '{$from}' empfangen.");
        break;
}

// 5. Erfolgreiche Verarbeitung bestätigen
echo json_encode(["status" => "ok", "message" => "Daten erfolgreich empfangen"]);

?>