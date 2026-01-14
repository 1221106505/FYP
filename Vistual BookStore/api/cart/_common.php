<?php
// /api/cart/_common.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../database.php'; // expects /api/database.php

function json_out(array $data): void {
  echo json_encode($data);
  exit;
}

function get_customer_id(): int {
  // Change these keys if your login uses a different session name
  $cid = $_SESSION['customer_id'] ?? $_SESSION['user_id'] ?? 0;

  // Dev fallback: allow ?customer_id=1 on localhost only
  if (!$cid) {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $isLocal = ($host === 'localhost' || str_starts_with($host, '127.0.0.1'));
    if ($isLocal && isset($_GET['customer_id'])) {
      $cid = (int)$_GET['customer_id'];
    }
  }

  if (!$cid) {
    json_out(['success' => false, 'error' => 'Not logged in']);
  }
  return (int)$cid;
}

function read_json(): array {
  $raw = file_get_contents('php://input');
  if (!$raw) return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}