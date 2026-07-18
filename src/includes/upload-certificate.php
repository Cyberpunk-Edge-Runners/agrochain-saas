<?php
// src/upload-certificate.php
//
// GET  -> show the upload form + list this farmer's previously uploaded certs
// POST -> validate the file, hand it to storeCertificateFile(), record it

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/storage.php';
require_once __DIR__ . '/db.php';

// Only farmers upload certificates — buyers/drivers have no reason to be
// here. requireRole() bounces anyone else with a 403.
$user = requireRole('farmer');

$error = '';
$success = '';

// A conservative allow-list rather than a block-list: we only accept file
// types we've actually thought about, instead of trying to guess every
// dangerous extension to reject (block-lists are easy to bypass — e.g.
// .php5, .phtml — allow-lists aren't).
$allowedTypes = [
    'application/pdf' => 'pdf',
    'image/jpeg'       => 'jpg',
    'image/png'        => 'png',
];
$maxBytes = 5 * 1024 * 1024; // 5MB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file = $_FILES['certificate'] ?? null;

    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please choose a file to upload.';
    } elseif ($file['size'] > $maxBytes) {
        $error = 'File is too large — 5MB max.';
    } else {
        // Don't trust $file['type'] — that's just whatever the browser
        // claimed in the request, trivially spoofable. finfo actually
        // reads the file's real content to determine its type.
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $realType = $finfo->file($file['tmp_name']);

        if (!array_key_exists($realType, $allowedTypes)) {
            $error = 'Only PDF, JPG, or PNG files are accepted.';
        } else {
            try {
                // This is the swap point described in storage.php — today
                // it saves locally, later it'll upload to S3, and this line
                // doesn't change either way.
                $storedName = storeCertificateFile($file['tmp_name'], $file['name']);

                $stmt = $pdo->prepare(
                    "INSERT INTO certificates (farmer_id, file_name) VALUES (?, ?)"
                );
                $stmt->execute([$user['id'], $storedName]);

                $success = 'Certificate uploaded successfully.';
            } catch (RuntimeException $e) {
                error_log($e->getMessage());
                $error = 'Upload failed: ' . $e->getMessage();
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $error = 'Upload saved, but recording it in the database failed. Please try again.';
            }
        }
    }
}

// Show this farmer their own upload history. Scoped to farmer_id = ? —
// nobody sees another farmer's certificate list.
$stmt = $pdo->prepare("SELECT * FROM certificates WHERE farmer_id = ? ORDER BY uploaded_at DESC");
$stmt->execute([$user['id']]);
$certificates = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Certificate — AgroChain</title>
</head>
<body>
    <h1>Upload Crop Quality Certificate</h1>
    <p>Upload your regional FDA or Ministry of Food &amp; Agriculture safety certificate.</p>

    <?php if ($error): ?>
        <p style="color:red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p style="color:green;"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <form method="POST" action="/upload-certificate.php" enctype="multipart/form-data">
        <input type="file" name="certificate" accept=".pdf,.jpg,.jpeg,.png" required>
        <button type="submit">Upload</button>
    </form>

    <h2>Your Uploaded Certificates</h2>
    <?php if (empty($certificates)): ?>
        <p><em>No certificates uploaded yet.</em></p>
    <?php else: ?>
        <ul>
            <?php foreach ($certificates as $cert): ?>
                <li><?= htmlspecialchars($cert['file_name']) ?> — <?= htmlspecialchars($cert['uploaded_at']) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <p><a href="/dashboard.php">&larr; Back to Dashboard</a></p>
</body>
</html>