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
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Backup Configuration</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts for a nicer look -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bs-body-bg: #fdfaf6;
            --bs-primary-rgb: 88, 129, 87;
            --bs-secondary-rgb: 243, 238, 232;
            --bs-body-font-family: 'Nunito Sans', sans-serif;
            --bs-card-border-color: rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background-color: rgb(var(--bs-primary-rgb));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-img {
            max-height: 50px;
            margin-right: 20px;
        }

        .btn-primary {
            --bs-btn-bg: rgb(var(--bs-primary-rgb));
            --bs-btn-border-color: rgb(var(--bs-primary-rgb));
            --bs-btn-hover-bg: #4a6e49;
            /* A slightly darker green */
            --bs-btn-hover-border-color: #4a6e49;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .info-box {
            background-color: rgb(var(--bs-secondary-rgb));
            border-left: 4px solid rgb(var(--bs-primary-rgb));
            padding: 10px 15px;
            margin-top: 8px;
            border-radius: 0 4px 4px 0;
            font-size: 0.9em;
            color: #555;
        }

        h1,
        h2,
        .form-label {
            font-weight: 700;
        }
    </style>
</head>

<body>
    <div class="container my-5">
        <div class="card shadow-sm border-0">
            <div class="card-header">
                <img src="logo.png" alt="Logo" class="logo-img my-2">
                <h1 class="h2 my-2">Server Backup Configuration</h1>
            </div>
            <div class="card-body p-4 p-md-5">

                <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
                    <div class="alert alert-success" role="alert">
                        Configuration saved successfully!
                    </div>
                <?php endif; ?>

                <form action="save_config.php" method="POST">
                    <div class="row g-4">

                        <!-- Left Column -->
                        <div class="col-lg-6">
                            <h2 class="h4 mb-3 border-bottom pb-2">Step 1: What to Back Up</h2>
                            <div class="mb-3">
                                <label for="databases" class="form-label">Databases</label>
                                <textarea class="form-control" id="databases" name="databases" rows="6" required><?php echo getValue('databases'); ?></textarea>
                                <div class="info-box">Enter one database name per line.</div>
                            </div>
                            <div class="mb-3">
                                <label for="directories" class="form-label">Directories</label>
                                <textarea class="form-control" id="directories" name="directories" rows="6" required><?php echo getValue('directories'); ?></textarea>
                                <div class="info-box">Enter one full, absolute path per line.<br>E.g., `/home/your_user/public_html`</div>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="col-lg-6">
                            <h2 class="h4 mb-3 border-bottom pb-2">Step 2: How to Back Up</h2>
                            <div class="mb-3">
                                <label for="db_user" class="form-label">Database User</label>
                                <input type="text" class="form-control" id="db_user" name="db_user" value="<?php echo getValue('db_user'); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="db_pass" class="form-label">Database Password</label>
                                <input type="password" class="form-control" id="db_pass" name="db_pass" placeholder="Leave blank to keep existing">
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-sm-7">
                                    <label for="rclone_remote" class="form-label">Cloud Remote Name</label>
                                    <input type="text" class="form-control" id="rclone_remote" name="rclone_remote" value="<?php echo getValue('rclone_remote', 'gdrive_backup'); ?>" required>
                                    <div class="info-box">The name you gave during rclone config.</div>
                                </div>
                                <div class="col-sm-5">
                                    <label for="remote_retention_days" class="form-label">Cloud Retention</label>
                                    <input type="number" class="form-control" id="remote_retention_days" name="remote_retention_days" value="<?php echo getValue('remote_retention_days', 30); ?>" required>
                                    <div class="info-box">Days to keep backups.</div>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label for="gdrive_folder" class="form-label">Cloud Storage Path</label>
                                <input type="text" class="form-control" id="gdrive_folder" name="gdrive_folder" value="<?php echo getValue('gdrive_folder', 'ServerBackups/MySite'); ?>" required>
                                <div class="info-box">The folder path inside your cloud storage.</div>
                            </div>

                            <h2 class="h4 mb-3 border-bottom pb-2">Step 3: Notifications</h2>
                            <div class="row g-3">
                                <div class="col-sm-7">
                                    <label for="notify_email" class="form-label">Notification Email</label>
                                    <input type="email" class="form-control" id="notify_email" name="notify_email" value="<?php echo getValue('notify_email'); ?>" placeholder="your.email@example.com">
                                </div>
                                <div class="col-sm-5">
                                    <label for="email_mode" class="form-label">Frequency</label>
                                    <select class="form-select" id="email_mode" name="email_mode">
                                        <option value="Always" <?php if (getValue('email_mode', 'Always') == 'Always') echo 'selected'; ?>>Always</option>
                                        <option value="On Failure" <?php if (getValue('email_mode') == 'On Failure') echo 'selected'; ?>>On Failure</option>
                                        <option value="Never" <?php if (getValue('email_mode') == 'Never') echo 'selected'; ?>>Never</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid mt-5">
                        <button type="submit" class="btn btn-primary btn-lg py-2">Save & Update Configuration</button>
                    </div>
                </form>
            </div>
        </div>
        <footer class="text-center text-muted mt-4">
            <small>Custom Backup System v4.0</small>
        </footer>
    </div>
</body>

</html>