<?php
$filename = '/usr/storage/ips/ips.txt';

// Om formuläret har skickats (Spara-knappen)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Hämta alla inskickade IP-adresser, rensa bort tomma rader
    $ips = isset($_POST['ips']) ? array_filter(array_map('trim', $_POST['ips'])) : [];
    
    // Skriv till filen (en IP per rad)
    file_put_contents($filename, implode(PHP_EOL, $ips));

    // DEBUG-VERSION:
    // 2>&1 gör att även felmeddelanden (stderr) hamnar i $output
    $script_path = '/homedata/edssensors/start_eds.sh';
    exec("nohup sudo $script_path > /tmp/eds_debug.log 2>&1 &");

    $message = "Filen har uppdaterats och skriptet har startats!";
}

// Läs in befintliga IP-adresser
$ip_list = [];
if (file_exists($filename)) {
    $ip_list = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
}

// Om listan är tom, lägg till ett tomt fält så användaren kan skriva in något
if (empty($ip_list)) {
    $ip_list = [''];
}
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title>IP-hanterare</title>
    <style>
        body { font-family: sans-serif; padding: 20px; line-height: 1.6; }
        .ip-row { margin-bottom: 10px; }
        input[type="text"] { padding: 8px; width: 250px; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 8px 15px; cursor: pointer; background: #28a745; color: white; border: none; border-radius: 4px; }
        .add-btn { background: #007bff; margin-bottom: 20px; }
        .msg { color: green; font-weight: bold; }
    </style>
</head>
<body>

    <h1>Hantera IP-adresser</h1>

    <?php if (isset($message)) echo "<p class='msg'>$message</p>"; ?>

    <form method="post">
        <div id="ip-container">
            <?php foreach ($ip_list as $ip): ?>
                <div class="ip-row">
                    <input type="text" name="ips[]" value="<?php echo htmlspecialchars($ip); ?>" placeholder="T.ex. 192.168.1.1">
                </div>
            <?php endforeach; ?>
        </div>

        <button type="button" class="add-btn" onclick="addBox()">+ Lägg till rad</button>
        <br>
        <button type="submit">Spara till fil</button>
    </form>

    <script>
        // Enkel funktion för att lägga till fler textrutor dynamiskt
        function addBox() {
            const container = document.getElementById('ip-container');
            const div = document.createElement('div');
            div.className = 'ip-row';
            div.innerHTML = '<input type="text" name="ips[]" placeholder="T.ex. 192.168.1.1">';
            container.appendChild(div);
        }
    </script>

</body>
</html>
