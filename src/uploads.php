<?php
declare(strict_types=1);

/**
 * Item photo uploads.
 */

/**
 * Stores an uploaded photo ($_FILES entry) in public/uploads under a random name.
 * Returns the image_url (relative to public/), null when no file was sent, or sets $error.
 */
function save_photo(?array $file, ?string &$error): ?string
{
    $error = null;
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $file['size'] > MAX_UPLOAD_MB * 1024 * 1024) {
        $error = 'Image must be ' . MAX_UPLOAD_MB . ' MB or smaller.';
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        $error = 'Upload failed. Please try again.';
        return null;
    }
    // Trust the file contents, not the client's filename or declared type.
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(IMAGE_TYPES[$mime]) || @getimagesize($file['tmp_name']) === false) {
        $error = 'Only JPG, PNG or WEBP images are allowed.';
        return null;
    }
    $name = bin2hex(random_bytes(8)) . '.' . IMAGE_TYPES[$mime];
    is_dir(UPLOAD_DIR) || mkdir(UPLOAD_DIR, 0755, true);
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $name)) {
        $error = 'Could not save the image.';
        return null;
    }
    return 'uploads/' . $name;
}
