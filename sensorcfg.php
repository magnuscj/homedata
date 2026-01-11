<?php
// Inställningar för databasen
$host = "127.0.0.1";
$user = "dbuser";
$pass = "kmjmkm54C#";
$db   = "mydb";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Anslutning misslyckades: " . $conn->connect_error);

// --- 1. RADERA RAD ---
if (isset($_GET['delete'])) {
    $stmt = $conn->prepare("DELETE FROM sensorconfig WHERE id = ?");
    $stmt->bind_param("i", $_GET['delete']);
    $stmt->execute();
    header("Location: sensorcfg.php"); // Ladda om sidan för att rensa URL:en
    exit;
}

// --- 2. SPARA �~DNDRINGAR ---
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
        body { font-family: sans-serif; margin: 20px; background: #f4f7f6; }
        table { border-collapse: collapse; width: 100%; background: white; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #007bff; color: white; }
        .btn { padding: 6px 12px; cursor: pointer; border-radius: 4px; border: none; text-decoration: none; display: inline-block; font-size: 0.9em; }
        .btn-edit { background: #ffc107; color: black; }
        .btn-delete { background: #dc3545; color: white; }
        .btn-save { background: #28a745; color: white; }
        .btn-refresh { background: #6c757d; color: white; margin-bottom: 15px; }
        .cancel { color: #dc3545; margin-left: 10px; }
    </style>
</head>
<body>

    <h2>Konfiguration av sensorer</h2>

    <button class="btn btn-refresh" onclick="window.location.href='sensorcfg.php';">Uppdatera tabell</button>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Sensor ID</th>
                <th>Namn</th>
                <th>Färg</th>
                <th>Synlig</th>
                <th>Typ</th>
                <th>�~Etgärder</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <?php if ($edit_id == $row['id']): ?>
                        <form method="POST">
                            <td><?php echo $row['id']; ?><input type="hidden" name="id" value="<?php echo $row['id']; ?>"></td>
                            <td><input type="text" name="sensorid" value="<?php echo htmlspecialchars($row['sensorid']); ?>"></td>
                            <td><input type="text" name="sensorname" value="<?php echo htmlspecialchars($row['sensorname']); ?>"></td>
                            <td><input type="text" name="color" value="<?php echo htmlspecialchars($row['color']); ?>"></td>
                            <td><input type="text" name="visible" value="<?php echo htmlspecialchars($row['visible']); ?>"></td>
                            <td><input type="text" name="type" value="<?php echo htmlspecialchars($row['type']); ?>"></td>
                            <td>
                                <button type="submit" name="save" class="btn btn-save">Spara</button>
                                <a href="sensorcfg.php" class="cancel">Avbryt</a>
                            </td>
                        </form>
                    <?php else: ?>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['sensorid']); ?></td>
                        <td><?php echo htmlspecialchars($row['sensorname']); ?></td>
                        <td><span style="color: <?php echo htmlspecialchars($row['color']); ?>;">�~W~O</span> <?php echo htmlspecialchars($row['color']); ?></td>
                        <td><?php echo htmlspecialchars($row['visible']); ?></td>
                        <td><?php echo htmlspecialchars($row['type']); ?></td>
                        <td>
                            <a href="?edit=<?php echo $row['id']; ?>" class="btn btn-edit">Redigera</a>
                            <a href="?delete=<?php echo $row['id']; ?>"
                               class="btn btn-delete"
                               onclick="return confirm('�~Dr du säker på att du vill ta bort denna sensor?');">Ta bort</a>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

</body>
</html>
