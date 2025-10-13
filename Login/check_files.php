<?php
echo "<h2>File Existence Check</h2>";

$files_to_check = [
    'Register.php' => __DIR__ . '/Register.php',
    'Login.php' => __DIR__ . '/Login.php',
    '../include/database.php' => __DIR__ . '/../include/database.php'
];

foreach ($files_to_check as $name => $path) {
    if (file_exists($path)) {
        echo "<div style='color: green;'>✓ $name exists at: $path</div>";
    } else {
        echo "<div style='color: red;'>✗ $name NOT found at: $path</div>";
    }
}

echo "<h3>Current URL Information:</h3>";
echo "Server Name: " . ($_SERVER['SERVER_NAME'] ?? 'N/A') . "<br>";
echo "Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "<br>";
echo "Script Name: " . ($_SERVER['SCRIPT_NAME'] ?? 'N/A') . "<br>";
echo "PHP Self: " . ($_SERVER['PHP_SELF'] ?? 'N/A') . "<br>";
?>