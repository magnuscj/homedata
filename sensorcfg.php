<?php
// Inställningar för databasen
$host = "127.0.0.1";
$user = "dbuser";
$pass = "kmjmkm54C#";
$db   = "mydb";

require_once __DIR__ . '/jpgraph_colors.php';

// --- CSRF + method hardening ---------------------------------------------
// This page mutates the DB. It was previously reachable publicly (via the
// external port-forward) and performed DELETE/UPDATE on unauthenticated GET
// requests — a crawler (ClaudeBot) followed the ?delete=/?edit= links on
// 2026-09-30 and wiped sensorconfig. Mutations now REQUIRE a POST carrying a
// valid per-session CSRF token, so no amount of link-crawling (GET) can change
// data. Apache additionally denies this file from being served externally
// (see container/apache-admin-deny.conf); this is defence in depth.
session_start();
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf'];

function require_post_csrf() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST'
        || empty($_POST['csrf'])
        || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
        http_response_code(403);
        exit('Forbidden: invalid or missing CSRF token.');
    }
}

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Anslutning misslyckades: " . $conn->connect_error);

function refresh_pvc_seed() {
    // Persist the (healthy) table to the PVC seed after a legitimate change.
    $dump = shell_exec("sh -c '/usr/bin/mysqldump -h 127.0.0.1 -u dbuser -pkmjmkm54C# --no-create-info mydb sensorconfig 2>&1'");
    if ($dump !== null && strpos($dump, "INSERT INTO") !== false) {
        file_put_contents('/usr/storage/sensorconfig.sql', $dump);
    }
}

// --- 1. RADERA RAD (POST + CSRF only) ---
if (isset($_POST['delete'])) {
    require_post_csrf();
    $stmt = $conn->prepare("DELETE FROM sensorconfig WHERE id = ?");
    $stmt->bind_param("i", $_POST['delete']);
    $stmt->execute();
    refresh_pvc_seed();
    header("Location: sensorcfg.php");
    exit;
}

// --- 2. SPARA ÄNDRINGAR (POST + CSRF only) ---
if (isset($_POST['save'])) {
    require_post_csrf();
    // Canonicalise the sensor type to lowercase before storing. Reports branch
    // on `type` with case-sensitive comparisons ("price", "temp", ...), so a
    // stray-cased value entered here (e.g. "Price") would silently break tile
    // rendering. Normalising on write keeps the DB clean; getSensorNames() also
    // lowercases on read as defence in depth.
    $type = strtolower(trim($_POST['type']));
    $stmt = $conn->prepare("UPDATE sensorconfig SET sensorid=?, sensorname=?, color=?, visible=?, type=? WHERE id=?");
    $stmt->bind_param("sssssi",
        $_POST['sensorid'],
        $_POST['sensorname'],
        $_POST['color'],
        $_POST['visible'],
        $type,
        $_POST['id']
    );
    $stmt->execute();
    refresh_pvc_seed();
    header("Location: sensorcfg.php");
    exit;
}

$edit_id = $_GET['edit'] ?? null;  // read-only view toggle; safe as GET
$result = $conn->query("SELECT * FROM sensorconfig");
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title>Sensorhantering</title>
    <style>
        body { font-family: sans-serif; margin: 20px; background: #000; color: #fff; font-size: 1.05em; font-weight: 500; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #444; padding: 10px; text-align: left; }
        th { background-color: #007bff; color: white; }
        tr:nth-child(even) { background: #1a1a1a; }
        tr:nth-child(odd) { background: #111; }
        input[type="text"] { padding: 6px 8px; border: 1px solid #555; border-radius: 4px; background: #333; color: #fff; font-size: 1em; width: 120px; }
        select { padding: 6px 8px; border: 1px solid #555; border-radius: 4px; background: #333; color: #fff; font-size: 1em; }
        .btn { padding: 6px 12px; cursor: pointer; border-radius: 4px; border: none; text-decoration: none; display: inline-block; font-size: 0.9em; font-weight: 600; }
        .btn-edit   { background: #ffc107; color: #000; }
        .btn-delete { background: #dc3545; color: white; }
        .btn-save   { background: #28a745; color: white; }
        .btn-refresh { background: #6c757d; color: white; margin-bottom: 15px; }
        .cancel { color: #dc3545; margin-left: 10px; text-decoration: none; font-weight: 600; }
        .color-dot { display: inline-block; width: 14px; height: 14px; border-radius: 50%; margin-right: 6px; vertical-align: middle; border: 1px solid #888; }
    </style>
</head>
<body>

    <h2>Sensor configuration</h2>

    <button class="btn btn-refresh" onclick="window.location.href='sensorcfg.php';">Refresh</button>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Sensor ID</th>
                <th>ID2</th>
                <th>Name</th>
                <th>Color</th>
                <th>Visible</th>
                <th>Type</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <?php if ($edit_id == $row['id']): ?>
                        <form method="POST">
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($CSRF); ?>">
                            <td><?php echo $row['id']; ?><input type="hidden" name="id" value="<?php echo $row['id']; ?>"></td>
                            <td><input type="text" name="sensorid"   value="<?php echo htmlspecialchars($row['sensorid']); ?>"></td>
                            <td><input type="text" name="sensorname" value="<?php echo htmlspecialchars($row['sensorname']); ?>"></td>
                            <td>
                                <?php $current_color = $row['color']; $in_subset = in_array(strtolower($current_color), array_map('strtolower', $JPGRAPH_COLOR_SUBSET)); ?>
                                <select name="color">
                                    <?php if (!$in_subset && $current_color !== ''): ?>
                                        <option value="<?php echo htmlspecialchars($current_color); ?>" selected>
                                            <?php echo htmlspecialchars($current_color); ?> (nuvarande)
                                        </option>
                                    <?php endif; ?>
                                    <?php foreach ($JPGRAPH_COLOR_SUBSET as $c): ?>
                                        <option value="<?php echo htmlspecialchars($c); ?>"
                                            style="background-color: <?php echo htmlspecialchars(jpgraph_color_to_hex($c)); ?>; color: #000;"
                                            <?php if (strtolower($c) === strtolower($current_color)) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($c); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="text" name="visible"    value="<?php echo htmlspecialchars($row['visible']); ?>"></td>
                            <td><input type="text" name="type"       value="<?php echo htmlspecialchars($row['type']); ?>"></td>
                            <td>
                                <button type="submit" name="save" class="btn btn-save">Save</button>
                                <a href="sensorcfg.php" class="cancel">Cancel</a>
                            </td>
                        </form>
                    <?php else: ?>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['sensorid']); ?></td>
                        <td><?php echo htmlspecialchars($row['sensorname']); ?></td>
                        <td>
                            <span class="color-dot" style="background-color: <?php echo htmlspecialchars(jpgraph_color_to_hex($row['color'])); ?>;"></span>
                            <?php echo htmlspecialchars($row['color']); ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['visible']); ?></td>
                        <td><?php echo htmlspecialchars($row['type']); ?></td>
                        <td>
                            <a href="?edit=<?php echo $row['id']; ?>" class="btn btn-edit">Change</a>
                            <form method="POST" style="display:inline"
                                  onsubmit="return confirm('Are you sure you want to remove this sensor?');">
                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($CSRF); ?>">
                                <input type="hidden" name="delete" value="<?php echo $row['id']; ?>">
                                <button type="submit" class="btn btn-delete">Remove</button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

</body>
</html>
ndwhile; ?>
        </tbody>
    </table>

</body>
</html>
