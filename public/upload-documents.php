<?php
// public/upload-documents.php
//
// GET  -> show the upload form (category options depend on role) + history
// POST -> validate the file, hand it to storeDocumentFile(), record it
//
// Farmers and drivers both land here, but see different category choices —
// a farmer uploads a crop quality certificate, a driver uploads a license
// or vehicle insurance. Buyers have no reason to be here at all.

require_once __DIR__ . '/../includes/bootstrap.php';
require_once INCLUDES_PATH . '/storage.php';

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

// $error stays inline (no redirect) — a validation failure never wrote
// anything, so a refresh just re-shows the same harmless error. $success
// now goes through flashSet()/flashGet() + a redirect instead, same
// Post/Redirect/Get fix applied to products.php — otherwise refreshing
// after a successful upload would resubmit the file and create a
// duplicate row.
$error = '';
$success = flashGet('success');

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
        $error = 'Please choose a valid document type.';
    } elseif (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please choose a file to upload.';
    } elseif ($file['size'] > $maxBytes) {
        $error = 'File is too large — 5MB max.';
    } else {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $realType = $finfo->file($file['tmp_name']);

        if (!array_key_exists($realType, $allowedTypes)) {
            $error = 'Only PDF, JPG, or PNG files are accepted.';
        } else {
            try {
                $storedName = storeDocumentFile($file['tmp_name'], $file['name']);

                $stmt = $pdo->prepare(
                    "INSERT INTO documents (user_id, category, file_name) VALUES (?, ?, ?)"
                );
                $stmt->execute([$user['id'], $category, $storedName]);

                flashSet('success', 'Document uploaded successfully.');
                header('Location: ' . ROUTE_UPLOAD_DOCUMENTS);
                exit;
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

$stmt = $pdo->prepare("SELECT * FROM documents WHERE user_id = ? ORDER BY uploaded_at DESC");
$stmt->execute([$user['id']]);
$documents = $stmt->fetchAll();

$allCategoryLabels = array_merge(...array_values($categoriesForRole));

$pageTitle = 'Upload Documents';
require PARTIALS_PATH . '/header.php';
?>

<h1>Upload Verification Document</h1>
<p class="row-meta">
    <?= $user['role'] === 'farmer'
        ? 'Upload your regional FDA or Ministry of Food &amp; Agriculture crop quality certificate.'
        : 'Upload your driver\'s license or vehicle insurance for verification.' ?>
</p>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="ticket">
    <form method="POST" action="<?= ROUTE_UPLOAD_DOCUMENTS ?>" enctype="multipart/form-data">
        <div class="field">
            <label for="category">Document Type</label>
            <select id="category" name="category" required>
                <option value="">-- Select --</option>
                <?php foreach ($availableCategories as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="document">File (PDF, JPG, or PNG — 5MB max)</label>
            <input id="document" type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required>
        </div>

        <button type="submit" class="btn btn-primary">Upload</button>
    </form>
</div>

<h2>Your Uploaded Documents</h2>
<?php if (empty($documents)): ?>
    <p class="row-meta">No documents uploaded yet.</p>
<?php else: ?>
    <ul class="data-list">
        <?php foreach ($documents as $doc): ?>
            <li class="ticket">
                <span class="stamp"><?= htmlspecialchars($allCategoryLabels[$doc['category']] ?? $doc['category']) ?></span>
                <p class="row-meta">
                    <?= htmlspecialchars($doc['file_name']) ?> · Uploaded <?= htmlspecialchars($doc['uploaded_at']) ?>
                </p>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php require PARTIALS_PATH . '/footer.php'; ?>