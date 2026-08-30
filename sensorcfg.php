<?php
// Inställningar för databasen
$host = "127.0.0.1";
$user = "dbuser";
$pass = "kmjmkm54C#";
$db   = "mydb";

require_once __DIR__ . '/jpgraph_colors.php';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Anslutning misslyckades: " . $conn->connect_error);

// --- 1. RADERA RAD ---
if (isset($_GET['delete'])) {
    $stmt = $conn->prepare("DELETE FROM sensorconfig WHERE id = ?");
    $stmt->bind_param("i", $_GET['delete']);
    $stmt->execute();
    $dump_result = shell_exec("sh -c '/usr/bin/mysqldump -h 127.0.0.1 -u dbuser -pkmjmkm54C# --no-create-info mydb sensorconfig 2>&1'");
    file_put_contents('/usr/storage/sensorconfig.sql', $dump_result);
    header("Location: sensorcfg.php");
    exit;
}

// --- 2. SPARA ÄNDRINGAR ---
if (isset($_POST['save'])) {
    $stmt = $conn->prepare("UPDATE sensorconfig SET sensorid=?, sensorname=?, color=?, visible=?, type=? WHERE id=?");
    $stmt->bind_param("sssssi",
        $_POST['sensorid'],
        $_POST['sensorname'],
        $_POST['color'],
        $_POST['visible'],
        $_POST['type'],
        $_POST['id']
    );
    $stmt->execute();
    $dump_result = shell_exec("sh -c '/usr/bin/mysqldump -h 127.0.0.1 -u dbuser -pkmjmkm54C# --no-create-info mydb sensorconfig 2>&1'");
    file_put_contents('/usr/storage/sensorconfig.sql', $dump_result);
    header("Location: sensorcfg.php");
    exit;
}

$edit_id = $_GET['edit'] ?? null;
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
                            <a href="?delete=<?php echo $row['id']; ?>"
                               class="btn btn-delete"
                               onclick="return confirm('Are you sure you want to remove this sensor?');">Remove</a>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

</body>
</html>
