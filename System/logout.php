<?php
session_start();

// 调试信息
error_log("Logout requested from: " . $_SERVER['REMOTE_ADDR']);

// 清除会话
session_destroy();

// 确保重定向
header("Location: Main.html");
exit();
?>