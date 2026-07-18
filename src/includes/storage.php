<?php
// src/includes/storage.php
//
// WHY THIS FILE EXISTS
// ---------------------
// Right now there's no AWS account, so user document uploads just get saved
// to a local folder inside the container. Once there IS an AWS account,
// the plan is to store them in S3 instead. Without this file, "switch to
// S3" would mean hunting down every place in the codebase that calls
// move_uploaded_file() and rewriting each one.
//
// Instead, every upload goes through storeDocumentFile() below. It
// looks at STORAGE_DRIVER (set in .env) to decide where the file actually
// goes. The rest of the app (upload-document.php) never knows or cares
// which driver is active — it just calls storeDocumentFile() and gets
// back a string to save in documents.file_name.
//
// To switch to S3 later:
//   1. composer require aws/aws-sdk-php
//   2. Fill in storeDocumentFileS3() below with real S3Client calls
//   3. Set STORAGE_DRIVER=s3 (+ AWS credentials) in .env
//   4. Nothing else in the app needs to change.

/**
 * Save an uploaded document and return the string that should be
 * stored in documents.file_name to retrieve it again later.
 *
 * @param string $tmpPath      The temp path PHP gave the upload ($_FILES[...]['tmp_name'])
 * @param string $originalName The original filename the user's browser sent
 * @throws RuntimeException on failure
 */
function storeDocumentFile(string $tmpPath, string $originalName): string {
    $driver = getenv('STORAGE_DRIVER') ?: 'local';

    // Build a filesystem/S3-key-safe filename: strip anything that isn't
    // alphanumeric/dot/dash/underscore, and prefix with a timestamp so two
    // users uploading "certificate.pdf" on the same day don't collide.
    $safeName = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);

    return match ($driver) {
        's3'    => storeDocumentFileS3($tmpPath, $safeName),
        default => storeDocumentFileLocal($tmpPath, $safeName),
    };
}

/**
 * Local-disk storage — the default until an AWS account exists.
 * Saves into /var/www/uploads/documents inside the container, which
 * docker-compose.yml mounts to a named volume so files survive a restart.
 */
function storeDocumentFileLocal(string $tmpPath, string $safeName): string {
    $uploadDir = __DIR__ . '/../../uploads/documents';

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Could not create local upload directory.');
    }

    $destination = $uploadDir . '/' . $safeName;

    // move_uploaded_file() (rather than a plain rename/copy) specifically
    // verifies $tmpPath really was created by a PHP file upload — this
    // guards against a path-traversal trick where someone crafts a request
    // pointing $tmpPath at an arbitrary file already on the server.
    if (!move_uploaded_file($tmpPath, $destination)) {
        throw new RuntimeException('Failed to save the uploaded file.');
    }

    return $safeName;
}

/**
 * S3 storage — NOT implemented yet. This is intentionally left as a stub
 * with a clear error rather than silently falling back to local storage,
 * so that setting STORAGE_DRIVER=s3 too early fails loudly instead of
 * quietly saving files to disk and confusing you later about where they
 * actually went.
 */
function storeDocumentFileS3(string $tmpPath, string $safeName): string {
    throw new RuntimeException(
        'STORAGE_DRIVER is set to "s3" but S3 upload isn\'t implemented yet. ' .
        'Set STORAGE_DRIVER=local in .env until the AWS S3 integration is built.'
    );

    // Once there's an AWS account and `composer require aws/aws-sdk-php`
    // has been run, this becomes roughly:
    //
    // $s3 = new Aws\S3\S3Client([
    //     'version' => 'latest',
    //     'region'  => getenv('AWS_REGION'),
    //     // Credentials should come from IAM role / env vars picked up
    //     // automatically by the SDK — never hardcode keys here.
    // ]);
    // $s3->putObject([
    //     'Bucket'     => getenv('AWS_S3_BUCKET'),
    //     'Key'        => 'documents/' . $safeName,
    //     'SourceFile' => $tmpPath,
    //     'ACL'        => 'private', // uploaded docs aren't public
    // ]);
    // return 'documents/' . $safeName; // the S3 key, stored in file_name
}