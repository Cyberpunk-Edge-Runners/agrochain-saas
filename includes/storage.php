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
// goes. The rest of the app (upload-documents.php) never knows or cares
// which driver is active — it just calls storeDocumentFile() and gets
// back a string to save in documents.file_name.
//
// STORAGE_DRIVER=s3 is now fully implemented (see storeDocumentFileS3()
// below) — set STORAGE_DRIVER=local in .env for local dev without
// touching AWS at all, or STORAGE_DRIVER=s3 (+ AWS_REGION,
// AWS_S3_BUCKET, AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY) to actually
// upload to S3. Either way, nothing else in the app changes.

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
    $uploadDir = __DIR__ . '/../uploads/documents';

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
 * S3 storage — uploads the file to the AgroChain documents bucket using
 * the AWS SDK for PHP.
 */
function storeDocumentFileS3(string $tmpPath, string $safeName): string {
    // require_once (not require) means this only actually loads the SDK
    // the FIRST time this function runs in a given request — and since
    // it's called from inside this function rather than at the top of
    // the file, the SDK never loads at all when STORAGE_DRIVER=local
    // (the common case during dev). No reason to pull in a library you're
    // not using.
    //
    // BASE_PATH is defined in bootstrap.php as the project root (the
    // parent of both public/ and includes/) — inside the container,
    // that's /var/www, which is exactly where the Dockerfile's
    // `composer install` step created vendor/.
    require_once BASE_PATH . '/vendor/autoload.php';

    // S3Client is the SDK's main entry point for talking to S3
    // specifically (the SDK has a separate *Client class for every AWS
    // service — S3Client, DynamoDbClient, and so on).
    //
    // Notice there's NO 'credentials' key being passed here. That's
    // deliberate, not an oversight: the SDK automatically checks a
    // standard list of places for credentials — environment variables
    // named AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY are the first
    // place it looks — before falling back to other methods. Since
    // docker-compose.yml passes those two env vars into the container,
    // the SDK finds them on its own. This means our own code never
    // directly touches the raw secret key value at all — it can't leak
    // it in a log line or an error message, because it never has it.
    $s3 = new Aws\S3\S3Client([
        'version' => 'latest',
        'region'  => getenv('AWS_REGION'),
    ]);

    // S3 doesn't really have "folders" the way a filesystem does — it's
    // a flat store of objects, each identified by a "key" (basically a
    // string that LOOKS like a file path). Using a "documents/" prefix
    // here is purely organizational, for when you're browsing the
    // bucket in the AWS Console — S3 itself doesn't treat it specially.
    $key = 'documents/' . $safeName;

    // putObject() is the actual API call that uploads the file's bytes.
    // 'SourceFile' tells the SDK to stream the file straight from disk
    // rather than us reading it into a PHP variable first — important
    // for not blowing up memory usage on a large upload.
    //
    // 'ServerSideEncryption' => 'AES256' explicitly requests that S3
    // encrypt this object at rest. The bucket already has default
    // encryption turned on from when it was created, so this is
    // technically redundant — but being explicit here means this code
    // is still correct even if the bucket's default settings ever
    // change, rather than silently relying on a setting that lives
    // somewhere else entirely.
    //
    // There's deliberately no 'ACL' parameter. Older S3 tutorials often
    // show one (e.g. 'ACL' => 'private') — but this bucket has Block
    // Public Access enabled and uses the modern "Bucket owner enforced"
    // object ownership setting, which DISABLES object-level ACLs
    // entirely. Passing one would actually cause an error. Access here
    // is controlled entirely by the IAM policy attached to agrochain-app
    // (the credential can only PutObject/GetObject on this one bucket)
    // plus Block Public Access — a cleaner, more current model than
    // per-object ACLs.
    $s3->putObject([
        'Bucket'               => getenv('AWS_S3_BUCKET'),
        'Key'                  => $key,
        'SourceFile'           => $tmpPath,
        'ServerSideEncryption' => 'AES256',
    ]);

    // This is the string that gets saved into documents.file_name in the
    // database — for local storage that was just a filename, for S3
    // it's the object's key, which is what you'd need to fetch it back
    // again later with a getObject() call.
    return $key;
}