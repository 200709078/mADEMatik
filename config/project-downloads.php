<?php

return [
    // Metadata verified against drAW's existing 0.0.3 release files on 2026-10-17.
    // Canonical GitHub release URLs; imports remain drafts until an administrator publishes them.
    'draw' => [
        [
            'title' => ['tr' => 'AppImage', 'en' => 'AppImage'],
            'platform' => 'linux', 'architecture' => 'x86_64',
            'distribution_type' => 'direct', 'channel' => 'stable', 'version' => '0.0.3',
            'download_url' => 'https://github.com/200709078/drAW/releases/download/v0.0.3/drAW-0.0.3-linux-x86_64.AppImage', 'file_size' => 98796802, 'released_at' => '2026-10-16',
            'notes' => ['tr' => 'AppImage dosyasına çalıştırma izni vererek uygulamayı açın.', 'en' => 'Allow executing the AppImage file, then open the application.'],
            'requirements' => ['tr' => '64 bit x86_64 Linux.', 'en' => '64-bit x86_64 Linux.'],
            'license_url' => 'https://github.com/200709078/drAW/blob/main/LICENSE',
            'sha256' => '78003b3cc5554190f783a684abdfd361e0b43b3c038dfcb119ab48bdd305c681',
            'sort_order' => 10, 'is_published' => false,
        ],
        [
            'title' => ['tr' => 'DEB Paketi', 'en' => 'DEB Package'],
            'platform' => 'linux', 'architecture' => 'amd64',
            'distribution_type' => 'direct', 'channel' => 'stable', 'version' => '0.0.3',
            'download_url' => 'https://github.com/200709078/drAW/releases/download/v0.0.3/drAW-0.0.3-linux-amd64.deb', 'file_size' => 100367184, 'released_at' => '2026-10-16',
            'notes' => ['tr' => 'DEB paketini dağıtımınızın paket yöneticisiyle kurun.', 'en' => 'Install the DEB package with your distribution’s package manager.'],
            'requirements' => ['tr' => '64 bit amd64 Linux ve DEB paket desteği. Bağımlılıklar: libgtk-3-0, libnotify4, libnss3, libxss1, libxtst6, xdg-utils, libatspi2.0-0, libuuid1, libsecret-1-0.', 'en' => '64-bit amd64 Linux with DEB package support. Dependencies: libgtk-3-0, libnotify4, libnss3, libxss1, libxtst6, xdg-utils, libatspi2.0-0, libuuid1, libsecret-1-0.'],
            'license_url' => 'https://github.com/200709078/drAW/blob/main/LICENSE',
            'sha256' => 'ca970d57916c85047ee125e82f93588fd11846de2d20a3d4194d092ae572a85e',
            'sort_order' => 20, 'is_published' => false,
        ],
    ],
];
