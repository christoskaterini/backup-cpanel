<?php
// Define the path to the config file
$configFile = 'config.json';
$config = [];

// Load existing config if the file exists
if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true);
}

// Helper to get values from config or return a default
function getValue($key, $default = '')
{
    global $config;
    return isset($config[$key]) ? htmlspecialchars($config[$key]) : $default;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Backup Configuration</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 2em;
            background-color: #f4f4f4;
        }

        .container {
            max-width: 800px;
            margin: auto;
            background: white;
            padding: 2em;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h1,
        h2 {
            color: #333;
        }

        label {
            display: block;
            margin-top: 1em;
            font-weight: bold;
        }

        input[type="text"],
        input[type="password"],
        input[type="email"],
        input[type="number"],
        textarea {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            border-radius: 4px;
            border: 1px solid #ccc;
            box-sizing: border-box;
        }

        textarea {
            height: 120px;
            resize: vertical;
        }

        .hint {
            font-size: 0.9em;
            color: #666;
        }

        button {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 1em;
            cursor: pointer;
            margin-top: 1.5em;
        }

        button:hover {
            background-color: #0056b3;
        }

        .message {
            padding: 1em;
            margin-bottom: 1em;
            border-radius: 4px;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Server Backup Configuration</h1>

        <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
            <p class="message success">Configuration saved successfully!</p>
        <?php endif; ?>

        <form action="save_config.php" method="POST">

            <h2>Step 1: Database Settings</h2>
            <label for="db_user">Database User</label>
            <input type="text" id="db_user" name="db_user" value="<?php echo getValue('db_user'); ?>" required>
            <p class="hint">The MySQL user that has access to all databases below.</p>

            <label for="db_pass">Database Password</label>
            <input type="password" id="db_pass" name="db_pass" placeholder="Enter new password or leave blank to keep existing">
            <p class="hint">Leave this blank to not change the password.</p>

            <label for="databases">Databases to Back Up</label>
            <textarea id="databases" name="databases" required><?php echo getValue('databases'); ?></textarea>
            <p class="hint">One database name per line. E.g., `database_sample`</p>

            <h2>Step 2: File & Folder Settings</h2>
            <label for="directories">Directories to Back Up</label>
            <textarea id="directories" name="directories" required><?php echo getValue('directories'); ?></textarea>
            <p class="hint">One full path per line. E.g., `/home/user/public_html`</p>

            <h2>Step 3: Destination (Google Drive)</h2>
            <label for="rclone_remote">Rclone Remote Name</label>
            <input type="text" id="rclone_remote" name="rclone_remote" value="<?php echo getValue('rclone_remote', 'gdrive_backup'); ?>" required>
            <p class="hint">The name you gave your remote during `rclone config`.</p>

            <label for="gdrive_folder">Google Drive Folder</label>
            <input type="text" id="gdrive_folder" name="gdrive_folder" value="<?php echo getValue('gdrive_folder', 'ServerBackups'); ?>" required>
            <p class="hint">The folder path inside your Google Drive.</p>

            <label for="remote_retention_days">Google Drive Retention (Days)</label>
            <input type="number" id="remote_retention_days" name="remote_retention_days" value="<?php echo getValue('remote_retention_days', 30); ?>" required>
            <p class="hint">Deletes backup folders on Google Drive older than this many days. Use 0 to disable remote cleanup.</p>

            <h2>Step 4: Notifications</h2>
            <label for="email_mode">When to Send Email Notifications</label>
            <select id="email_mode" name="email_mode">
                <option value="Always" <?php if (getValue('email_mode') == 'Always') echo 'selected'; ?>>Always (On Success or Failure)</option>
                <option value="On Failure" <?php if (getValue('email_mode') == 'On Failure') echo 'selected'; ?>>On Failure Only</option>
                <option value="Never" <?php if (getValue('email_mode') == 'Never') echo 'selected'; ?>>Never</option>
            </select>

            <label for="notify_email">Email Address for Notifications</label>
            <input type="email" id="notify_email" name="notify_email" value="<?php echo getValue('notify_email'); ?>" placeholder="your.email@example.com">
            <p class="hint">The email address to send notifications to. Only used if the setting above is not "Never".</p>

            <button type="submit">Save Configuration</button>
        </form>
    </div>
</body>

</html>