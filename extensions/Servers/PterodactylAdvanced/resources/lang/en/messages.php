<?php

return [
    'actions' => [
        'go_to_server' => 'Go to server',
        'generate_sftp_password' => 'Generate SFTP password',
    ],

    'sftp' => [
        'title' => 'SFTP',
        'intro' => 'Use these credentials in any SFTP client to manage your server files.',
        'address' => 'Address',
        'username' => 'Username',
        'unavailable' => 'The panel could not be reached, so the connection details are unavailable right now.',
        'copy' => 'Copy',
        'copied' => 'Copied',
        'new_password' => 'New password',
        'new_password_hint' => 'Copy it now, it is shown only once and cannot be retrieved later.',
        'no_password_hint' => 'No password is set until you generate one with the button above. Generating a new one immediately stops any SFTP client still using the previous password.',
    ],
];
