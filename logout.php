<?php
// Logout handler

require_once 'steam_auth.php';

SteamAuth::logout();
header('Location: index.php');
exit;
