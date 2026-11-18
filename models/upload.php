<?php

function saveDataUrlUpload($dataUrl, $category, $existingPath = null)
{
    if (!$dataUrl || strpos($dataUrl, 'data:') !== 0) return $existingPath;
    if (!preg_match('/^data:(image\/(jpeg|png|gif|webp)|application\/pdf);base64,(.*)$/s', $dataUrl, $matches)) {
        throw new InvalidArgumentException('Only image or PDF files are allowed.');
    }

    $contents = base64_decode($matches[3], true);
    if ($contents === false || strlen($contents) > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Uploaded files must be valid and no larger than 5 MB.');
    }

    $extension = $matches[1] === 'application/pdf' ? 'pdf' : ($matches[2] === 'jpeg' ? 'jpg' : $matches[2]);
    $safeCategory = preg_replace('/[^a-z0-9_-]/i', '_', $category);
    $directory = __DIR__ . '/../uploads';
    if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
        throw new RuntimeException('Unable to create the uploads directory.');
    }

    $filename = $safeCategory . '_' . bin2hex(random_bytes(10)) . '.' . $extension;
    if (file_put_contents($directory . '/' . $filename, $contents) === false) {
        throw new RuntimeException('Unable to save the uploaded file.');
    }

    return 'uploads/' . $filename;
}

function saveImageDataUrlUpload($dataUrl, $category, $existingPath = null)
{
    if ($dataUrl === null || $dataUrl === '') return $existingPath;
    if (!is_string($dataUrl) || strpos($dataUrl, 'data:') !== 0) {
        throw new InvalidArgumentException('Please upload a JPEG, PNG, GIF, or WebP image.');
    }
    if (!preg_match('/^data:image\/(jpeg|png|gif|webp);base64,([A-Za-z0-9+\/=]+)$/D', $dataUrl, $matches)) {
        throw new InvalidArgumentException('Please upload a JPEG, PNG, GIF, or WebP image.');
    }

    $contents = base64_decode($matches[2], true);
    if ($contents === false || strlen($contents) > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Uploaded images must be valid and no larger than 5 MB.');
    }
    $imageInfo = @getimagesizefromstring($contents);
    if (!$imageInfo || !in_array($imageInfo['mime'] ?? '', ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
        throw new InvalidArgumentException('The uploaded file is not a supported image.');
    }

    return saveDataUrlUpload($dataUrl, $category, $existingPath);
}

function uploadUrl($path)
{
    return $path && strpos($path, 'uploads/') === 0 ? '../' . ltrim($path, '/') : '';
}
