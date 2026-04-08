<?php

$configPath = __DIR__ . '/config.php';
$exampleConfigPath = __DIR__ . '/config.example.php';

if (file_exists($configPath)) {
    require_once $configPath;
} elseif (file_exists($exampleConfigPath)) {
    require_once $exampleConfigPath;
}
