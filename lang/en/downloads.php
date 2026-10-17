<?php

return [
    'heading' => 'Downloads', 'intro' => 'Choose a package or store link for your device.', 'jump' => 'Download', 'open_browser' => 'Open in Browser',
    'platforms' => ['windows' => 'Windows', 'macos' => 'macOS', 'linux' => 'Linux', 'android' => 'Android', 'ios' => 'iOS', 'ipados' => 'iPadOS', 'other' => 'Other'],
    'types' => ['direct' => 'Direct Download', 'store' => 'Store', 'test' => 'Test Link'],
    'channels' => ['stable' => 'Stable', 'beta' => 'Beta'],
    'actions' => ['direct' => 'Download', 'store' => 'Open Store', 'test' => 'Try Test Version', 'google_play' => 'Open Google Play', 'app_store' => 'Open App Store', 'microsoft_store' => 'Open Microsoft Store', 'testflight' => 'Try with TestFlight'],
    'fields' => ['title' => 'Package name', 'platform' => 'Platform', 'architecture' => 'Processor architecture', 'distribution_type' => 'Distribution type', 'channel' => 'Release channel', 'version' => 'Version', 'download_url' => 'Download or store URL', 'file_size' => 'File size (bytes)', 'released_at' => 'Release date', 'notes' => 'Installation notes', 'requirements' => 'System requirements', 'license_url' => 'License / terms URL', 'sha256' => 'SHA-256', 'sort_order' => 'Sort order', 'is_published' => 'Show to visitors'],
    'help' => ['title' => 'Optional; for example AppImage, DEB or Portable.', 'platform' => 'Choose a suggestion or enter a new platform code using lowercase English letters, digits, hyphens and underscores.', 'architecture' => 'Optional; for example x86_64, arm64 or universal.', 'download_url' => 'An HTTPS URL or site path starting with /. Required to publish; drafts may leave it blank.', 'file_size' => 'Optional exact size in bytes. Store links may leave this blank.', 'sha256' => 'Optional 64-character SHA-256 digest of the download.', 'is_published' => 'Uncheck to hide without deleting. The project must also be published.', 'text' => 'Optional plain text. HTML is escaped.'],
    'add' => 'Add Download', 'edit' => 'Edit Download', 'save' => 'Save Download', 'back' => 'Back to Project',
    'manage' => 'Manage packages and store links by platform. Drafts are hidden from visitors.', 'empty' => 'No download has been added to this project.',
    'published' => 'Published', 'draft' => 'Draft', 'missing_url' => 'Download URL pending', 'delete' => 'Delete Record', 'confirm_delete' => 'Delete this download record? The linked file will not be deleted.',
    'license' => 'License and Terms', 'checksum' => 'File Verification', 'date' => 'Release Date', 'size' => 'File Size', 'version' => 'Version', 'requirements' => 'System Requirements', 'notes' => 'Installation Notes',
    'validation' => ['safe_url' => 'The :attribute must be an HTTPS URL without credentials or a safe site path.'],
    'messages' => ['created' => 'Download added.', 'updated' => 'Download updated.', 'deleted' => 'Download deleted.'],
];
