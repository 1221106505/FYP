<?php
require_once __DIR__ . "/_common.php";

// test query
$res = $conn->query("SELECT DATABASE() AS dbname");
$row = $res->fetch_assoc();

json_response([
  "success" => true,
  "database" => $row["dbname"] ?? null,
  "session_user_id" => $_SESSION["user_id"] ?? null
]);
