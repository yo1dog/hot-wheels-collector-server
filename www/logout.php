<?php
require_once __DIR__ . '/../utils/authManager.php';

AuthManager::logout();

header('Location: /');
?>