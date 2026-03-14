<?php
// public/test-db.php

// Make sure we load the config file (this is the critical missing line)
require_once __DIR__ . '/../src/config.php';

echo "<h1>Database Connection Test</h1>";

if (isset($db) && $db instanceof PDO) {
    try {
        $db->query("SELECT 1");
        echo "<p style='color: green; font-size: 1.5em; font-weight: bold;'>";
        echo "✅ Successfully connected to database: <b>" . DB_NAME . "</b>";
        echo "</p>";

        // Bonus: show MySQL version
        $row = $db->query("SELECT VERSION() AS version")->fetch();
        echo "<p>MySQL version: " . $row['version'] . "</p>";

    } catch (Exception $e) {
        echo "<p style='color: red; font-size: 1.4em;'>";
        echo "❌ Query failed: " . htmlspecialchars($e->getMessage());
        echo "</p>";
    }
} else {
    echo "<p style='color: orange; font-size: 1.4em;'>";
    echo "⚠️ \$db variable is not set — config.php probably failed to run or define \$db.";
    echo "</p>";
}

echo "<br><br><a href='index.php'>← Back to home</a>";