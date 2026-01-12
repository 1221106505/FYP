<?php
// test_path.php
echo "Current directory: " . __DIR__ . "<br>";
echo "Current file: " . __FILE__ . "<br>";

$paths = [
    'Database/databsae.php',
    '../Database/db_connection.php',
    '../../Database/db_connection.php',
    'C:/xampp/htdocs/FYP/Vistual BookStore/Database/db_connection.php'
];

echo "<br>Checking paths:<br>";
foreach ($paths as $path) {
    if (file_exists($path)) {
        echo "✓ Found: $path<br>";
    } else {
        echo "✗ Not found: $path<br>";
    }
}
?>