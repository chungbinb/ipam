<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Details</title>
    <link rel="stylesheet" href="/public/css/styles.css">
</head>
<body>
    <div class="container">
        <h1>Asset Details</h1>
        <?php if (isset($asset)): ?>
            <table>
                <tr>
                    <th>ID</th>
                    <td><?php echo htmlspecialchars($asset->id); ?></td>
                </tr>
                <tr>
                    <th>Name</th>
                    <td><?php echo htmlspecialchars($asset->name); ?></td>
                </tr>
                <tr>
                    <th>Type</th>
                    <td><?php echo htmlspecialchars($asset->type); ?></td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td><?php echo htmlspecialchars($asset->status); ?></td>
                </tr>
            </table>
        <?php else: ?>
            <p>No asset found.</p>
        <?php endif; ?>
        <a href="/index.php">Back to Asset List</a>
    </div>
</body>
</html>