<?php

return [
    'actions' => [
        'go_to_server' => 'Go to server',
    ],

    'sftp' => [
        'title' => 'SFTP',
        'intro' => 'Use these credentials in any SFTP client to manage your server files.',
        'address' => 'Address',
        'username' => 'Username',
        'password' => 'Password',
        'unavailable' => 'The panel could not be reached, so the connection details are unavailable right now.',
        'admin_account' => 'This server belongs to an administrator account on the panel. Its password can only be changed from the panel itself.',
        'copy' => 'Copy',
        'copied' => 'Copied',
        'generate' => 'Generate a new password',
        'confirm_title' => 'Replace the current SFTP password?',
        'confirm_body' => 'A new password will be generated and shown once. Any SFTP client still using the previous password will stop working immediately.',
        'confirm' => 'Generate',
        'cancel' => 'Cancel',
        'new_password' => 'New password',
        'new_password_hint' => 'Copy it now, it is shown only once and cannot be retrieved later.',
    ],
];
