<?php

return [
    'actions' => [
        'go_to_server' => 'Accéder au serveur',
        'generate_sftp_password' => 'Générer un mot de passe SFTP',
    ],

    'sftp' => [
        'title' => 'SFTP',
        'intro' => 'Utilisez ces identifiants dans un client SFTP pour gérer les fichiers de votre serveur.',
        'address' => 'Adresse',
        'username' => 'Utilisateur',
        'unavailable' => 'Le panel est injoignable, les informations de connexion ne sont pas disponibles pour le moment.',
        'copy' => 'Copier',
        'copied' => 'Copié',
        'new_password' => 'Nouveau mot de passe',
        'new_password_hint' => 'Copiez-le maintenant : il n\'est affiché qu\'une seule fois et ne pourra pas être retrouvé.',
        'no_password_hint' => 'Aucun mot de passe n\'est défini tant que vous n\'en générez pas un avec le bouton ci-dessus. En générer un nouveau interrompt immédiatement tout client SFTP utilisant le précédent.',
    ],
];
