<?php
$filename = '/usr/storage/ips/ips.txt';

// Om formuläret har skickats (Spara-knappen)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ips = isset($_POST['ips']) ? array_filter(array_map('trim', $_POST['ips'])) : [];

    if (!is_dir(dirname($filename))) {
        mkdir(dirname($filename), 0755, true);
    }

    $result = file_put_contents($filename, implode(PHP_EOL, $ips));

    if ($result === false) {
        $message = "FEL: Kunde inte skriva till filen. Kontrollera rättigheter för " . dirname($filename);
    } else {
        $script_path = '/homedata/edssensors/start_eds.sh';
        exec("nohup sudo $script_path > /tmp/eds_debug.log 2>&1 &");
        $message = "Filen har uppdaterats och skriptet har startats!";
    }
}

// Läs in befintliga IP-adresser
$ip_list = [];
if (file_exists($filename)) {
    $ip_list = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
}

if (empty($ip_list)) {
    $ip_list = [''];
}

// Hämta tjänste-IPs via Kubernetes REST API
$discovered = [];
$token_file = '/var/run/secrets/kubernetes.io/serviceaccount/token';
$ca_file    = '/var/run/secrets/kubernetes.io/serviceaccount/ca.crt';
$k8s_host   = 'https://kubernetes.default.svc';

if (file_exists($token_file) && file_exists($ca_file)) {
    $token = trim(file_get_contents($token_file));
    $ctx = stream_context_create([
        'ssl'  => ['cafile' => $ca_file, 'verify_peer' => true, 'verify_peer_name' => false],
        'http' => ['timeout' => 3, 'header' => "Authorization: Bearer $token\r\n"]
    ]);
    $response = @file_get_contents("$k8s_host/api/v1/namespaces/default/services", false, $ctx);
    if ($response !== false) {
        $data = json_decode($response, true);
        foreach ($data['items'] ?? [] as $svc) {
            $name = $svc['metadata']['name'] ?? '';
            $ip   = $svc['spec']['clusterIP'] ?? '';
            if (strpos($name, 'ext-nordenort-service') !== false && $ip && $ip !== 'None') {
                $check_ctx = stream_context_create(['http' => ['timeout' => 1]]);
                $responds = @file_get_contents("http://$ip/details.xml", false, $check_ctx) !== false;
                if ($responds) {
                    $discovered[] = ['ip' => $ip, 'name' => $name];
                }
            }
        }
    }
}

// Build lookup: ip => service name
$discovered_ips = [];
foreach ($discovered as $d) {
    $discovered_ips[$d['ip']] = $d['name'];
}
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title>IP-hanterare</title>
    <style>
        body { font-family: sans-serif; padding: 20px; line-height: 1.6; background: #000; color: #fff; font-size: 1.05em; font-weight: 500; }
        .ip-row { margin-bottom: 10px; }
        input[type="text"] { padding: 8px; width: 250px; border: 1px solid #555; border-radius: 4px; background: #333; color: #fff; font-size: 1em; font-weight: 500; }
        button { padding: 8px 15px; cursor: pointer; background: #28a745; color: white; border: none; border-radius: 4px; font-size: 1em; font-weight: 600; }
        .add-btn { background: #007bff; margin-bottom: 20px; }
        .del-btn { background: #dc3545; margin-left: 8px; }
        .msg { color: green; font-weight: bold; }
        .err { color: red; font-weight: bold; }
        .check { color: #28a745; font-weight: bold; margin-left: 8px; }
        .new-heading { margin: 20px 0 10px; font-weight: bold; color: #ffc107; }
        .new-row { padding: 6px; border: 1px dashed #ffc107; border-radius: 4px; display: inline-block; }
        .new-ip-input { opacity: 0.7; }
        .new-badge { color: #ffc107; font-weight: bold; margin: 0 8px; }
        .accept-btn { background: #28a745; margin-left: 4px; }
        .reject-btn { background: #dc3545; margin-left: 4px; }
    </style>
</head>
<body>

    <h1>Hantera IP-adresser</h1>

    <?php if (isset($message)) echo "<p class='" . (str_starts_with($message, 'FEL') ? 'err' : 'msg') . "'>$message</p>"; ?>

    <form method="post">
        <div id="ip-container">
            <?php foreach ($ip_list as $ip):
                $svc_name = $discovered_ips[$ip] ?? '';
                $short_name = $svc_name ? explode('-', $svc_name)[0] : '';
            ?>
                <div class="ip-row">
                    <input type="text" name="ips[]" value="<?php echo htmlspecialchars($ip); ?>"
                        placeholder="192.168.1.1" inputmode="decimal" maxlength="15">
                    <button type="button" class="del-btn" onclick="this.parentElement.remove()">Remove</button>
                    <?php if ($svc_name): ?>
                        <span class="check" title="<?php echo htmlspecialchars($svc_name); ?>">✔ <?php echo htmlspecialchars($short_name); ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php
            $new_discovered = array_filter($discovered, fn($d) => !in_array($d['ip'], $ip_list));
            if (!empty($new_discovered)):
            ?>
                <div class="new-heading">Newly discovered services (not in list):</div>
            <?php endif; ?>
            <?php foreach ($discovered as $d):
                if (in_array($d['ip'], $ip_list)) continue;
                $short_name = explode('-', $d['name'])[0];
            ?>
                <div class="ip-row new-row" data-ip="<?php echo htmlspecialchars($d['ip']); ?>">
                    <!-- Disabled input so it is NOT submitted until accepted -->
                    <input type="text" value="<?php echo htmlspecialchars($d['ip']); ?>" disabled
                        data-name="ips[]" class="new-ip-input">
                    <span class="new-badge" title="<?php echo htmlspecialchars($d['name']); ?>">NEW: <?php echo htmlspecialchars($short_name); ?> (<?php echo htmlspecialchars($d['ip']); ?>)</span>
                    <button type="button" class="accept-btn" onclick="acceptRow(this)">Accept</button>
                    <button type="button" class="reject-btn" onclick="this.closest('.ip-row').remove()">Reject</button>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="button" class="add-btn" onclick="addBox()">Add</button>
        <button type="submit">Save</button>
    </form>

    <script>
        const IP_PATTERN = /^(\d{1,3}\.){3}\d{1,3}$/;

        function validIP(val) {
            if (!IP_PATTERN.test(val)) return false;
            return val.split('.').every(n => parseInt(n) <= 255);
        }

        // Accept a discovered IP: turn it into a normal, submittable row
        function acceptRow(btn) {
            const row = btn.closest('.ip-row');
            const ip = row.getAttribute('data-ip');
            const svcTitle = row.querySelector('.new-badge').getAttribute('title');
            const shortName = row.querySelector('.new-badge').textContent
                .replace(/^NEW:\s*/, '').replace(/\s*\(.*\)$/, '');
            row.innerHTML =
                '<input type="text" name="ips[]" value="' + ip + '" placeholder="192.168.1.1" inputmode="decimal" maxlength="15">' +
                '<button type="button" class="del-btn" onclick="this.parentElement.remove()">Remove</button>' +
                '<span class="check" title="' + svcTitle + '">\u2714 ' + shortName + '</span>';
            row.classList.remove('new-row');
        }

        function addBox() {
            const container = document.getElementById('ip-container');
            const div = document.createElement('div');
            div.className = 'ip-row';
            div.innerHTML = '<input type="text" name="ips[]" placeholder="192.168.1.1" inputmode="decimal" maxlength="15"><button type="button" class="del-btn" onclick="this.parentElement.remove()">Remove</button>';
            container.appendChild(div);
            div.querySelector('input').focus();
        }

        document.querySelector('form').addEventListener('submit', function(e) {
            let valid = true;
            document.querySelectorAll('input[name="ips[]"]').forEach(input => {
                const val = input.value.trim();
                if (val && !validIP(val)) {
                    input.style.borderColor = '#dc3545';
                    input.title = 'Invalid IP address';
                    valid = false;
                } else {
                    input.style.borderColor = '';
                    input.title = '';
                }
            });
            if (!valid) e.preventDefault();
        });

        document.getElementById('ip-container').addEventListener('input', function(e) {
            if (e.target.matches('input[name="ips[]"]')) {
                const val = e.target.value.trim();
                if (val === '' || validIP(val)) {
                    e.target.style.borderColor = '#28a745';
                } else {
                    e.target.style.borderColor = '#dc3545';
                }
            }
        });
    </script>

</body>
</html>
