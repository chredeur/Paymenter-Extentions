<?php

return [
    'actions' => [
        'go_to_server' => 'Accéder au serveur',
    ],

    'sftp' => [
        'title' => 'SFTP',
        'intro' => 'Utilisez ces identifiants dans un client SFTP pour gérer les fichiers de votre serveur.',
        'address' => 'Adresse',
        'username' => 'Utilisateur',
        'password' => 'Mot de passe',
        'unavailable' => 'Le panel est injoignable, les informations de connexion ne sont pas disponibles pour le moment.',
        'admin_account' => 'Ce serveur appartient à un compte administrateur du panel. Son mot de passe ne peut être modifié que depuis le panel lui-même.',
        'copy' => 'Copier',
        'copied' => 'Copié',
        'generate' => 'Générer un nouveau mot de passe',
        'confirm_title' => 'Remplacer le mot de passe SFTP actuel ?',
        'confirm_body' => 'Un nouveau mot de passe sera généré et affiché une seule fois. Tout client SFTP utilisant encore le précédent cessera immédiatement de fonctionner.',
        'confirm' => 'Générer',
        'cancel' => 'Annuler',
        'new_password' => 'Nouveau mot de passe',
        'new_password_hint' => 'Copiez-le maintenant : il n\'est affiché qu\'une seule fois et ne pourra pas être retrouvé.',
    ],
];
