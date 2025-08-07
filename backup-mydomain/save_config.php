<?php
// Define the path to the config file
$configFile = 'config.json';
$config = [];

// Load existing config to preserve password if it's not being changed
if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Line Endings ---
    $databases_list = preg_replace('~\R~u', "\n", $_POST['databases']);
    $directories_list = preg_replace('~\R~u', "\n", $_POST['directories']);

    // Sanitize and prepare data from the form
    $newConfig = [
        'db_user' => trim($_POST['db_user']),
        'databases' => trim($databases_list),
        'directories' => trim($directories_list),
        'rclone_remote' => trim($_POST['rclone_remote']),
        'gdrive_folder' => trim($_POST['gdrive_folder']),
        'remote_retention_days' => intval($_POST['remote_retention_days']),
        'email_mode' => trim($_POST['email_mode']),
        'notify_email' => trim($_POST['notify_email'])
    ];

    // Only update the password if a new one was provided
    if (!empty($_POST['db_pass'])) {
        $newConfig['db_pass'] = $_POST['db_pass'];
    } else {
        // Keep the old password if it exists
        $newConfig['db_pass'] = isset($config['db_pass']) ? $config['db_pass'] : '';
    }

    // Convert the data to JSON format
    $json_data = json_encode($newConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    // Write the data to the config file
    if (file_put_contents($configFile, $json_data)) {
        // Redirect back to the main page with a success message
        header('Location: index.php?status=success');
        exit();
    } else {
        die('Error: Could not write to config.json. Please check file permissions.');
    }
} else {
    // If not a POST request, redirect to the form
    header('Location: index.php');
    exit();
}
