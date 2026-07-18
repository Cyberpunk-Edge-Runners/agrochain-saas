<?php
// src/upload-document.php
//
// GET  -> show the upload form (category options depend on role) + history
// POST -> validate the file, hand it to storeDocumentFile(), record it
//
// Farmers and drivers both land here, but see different category choices —
// a farmer uploads a crop quality certificate, a driver uploads a license
// or vehicle insurance. Buyers have no reason to be here at all.

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/storage.php';
require_once __DIR__ . '/db.php';

$user = requireAnyRole(['farmer', 'driver']);

// Which document categories this role is even allowed to upload. Keeping
// this server-side (not just in the HTML <select>) means a farmer can't
// force through a 'drivers_license' upload by tampering with the form —
// we re-check $categoriesForRole below when the POST comes in.
$categoriesForRole = [
    'farmer' => ['crop_quality_certificate' => 'Crop Quality Certificate'],
    'driver' => [
        'drivers_license'   => "Driver's License",
        'vehicle_insurance' => 'Vehicle Insurance',
    ],
];
$availableCategories = $categoriesForRole[$user['role']];

$error = '';
$success = '';

$allowedTypes = [
    'application/pdf' => 'pdf',
    'image/jpeg'       => 'jpg',
    'image/png'        => 'png',
];
$maxBytes = 5 * 1024 * 1024; // 5MB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file     = $_FILES['document'] ?? null;
    $category = $_POST['category'] ?? '';

    if (!array_key_exists($category, $availableCategories)) {
        // Either the dropdown got tampered with, or someone POSTed here
        // directly with a category their role doesn't have access to.
        $error = 'Please choose a valid document type.';
    } elseif (!$file || $file['error'] !== UPLOAD_ERR_OK) {
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
                // Swap point described in storage.php — saves locally today,
                // uploads to S3 later, this line doesn't change either way.
                $storedName = storeDocumentFile($file['tmp_name'], $file['name']);

                $stmt = $pdo->prepare(
                    "INSERT INTO documents (user_id, category, file_name) VALUES (?, ?, ?)"
                );
                $stmt->execute([$user['id'], $category, $storedName]);

                $success = 'Document uploaded successfully.';
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

// Show this user their own upload history, scoped to user_id = ? — nobody
// sees anyone else's documents.
$stmt = $pdo->prepare("SELECT * FROM documents WHERE user_id = ? ORDER BY uploaded_at DESC");
$stmt->execute([$user['id']]);
$documents = $stmt->fetchAll();

// Human-readable labels for displaying category values in the history list
// below (covers both roles' categories, not just the current user's role,
// in case that ever changes).
$allCategoryLabels = array_merge(...array_values($categoriesForRole));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Document — AgroChain</title>
</head>
<body>
    <h1>Upload Verification Document</h1>
    <p>
        <?= $user['role'] === 'farmer'
            ? 'Upload your regional FDA or Ministry of Food &amp; Agriculture crop quality certificate.'
            : 'Upload your driver\'s license or vehicle insurance for verification.' ?>
    </p>

    <?php if ($error): ?>
        <p style="color:red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p style="color:green;"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <form method="POST" action="/upload-documents.php" enctype="multipart/form-data">
        <label>Document Type
            <select name="category" required>
                <option value="">-- Select --</option>
                <?php foreach ($availableCategories as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label><br>

        <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required>
        <button type="submit">Upload</button>
    </form>

    <h2>Your Uploaded Documents</h2>
    <?php if (empty($documents)): ?>
        <p><em>No documents uploaded yet.</em></p>
    <?php else: ?>
        <ul>
            <?php foreach ($documents as $doc): ?>
                <li>
                    <strong><?= htmlspecialchars($allCategoryLabels[$doc['category']] ?? $doc['category']) ?>:</strong>
                    <?= htmlspecialchars($doc['file_name']) ?> — <?= htmlspecialchars($doc['uploaded_at']) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <p><a href="/dashboard.php">&larr; Back to Dashboard</a></p>
</body>
</html>