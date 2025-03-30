<?php
if (!file_exists('../vendor/autoload.php')) {
    die('Autoload file not found. Run "composer install" first.');
}
require_once '../vendor/autoload.php';

try {
    $client = new Google_Client();
    $client->setClientId('1060397700614-ciqshpee4u3ap8rcht01pr2dlih5s6s7.apps.googleusercontent.com');
    $client->setClientSecret('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');
    $client->setRedirectUri('http://localhost/tes/public/callback.php');
    $client->addScope('email');
    $client->addScope('profile');
} catch (Exception $e) {
    die('Google Client Error: ' . $e->getMessage());
}
?>