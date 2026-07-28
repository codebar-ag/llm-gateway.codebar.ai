<?php

return [
    'panels' => [
        'identification' => [
            'title' => 'Identification',
            'id' => 'ID',
            'uuid' => 'UUID',
        ],

        'timestamps' => [
            'title' => 'Horodatages',
            'created_at' => 'créé à',
            'updated_at' => 'dernière mise à jour le',
            'deleted_at' => 'archivé le',
        ],
    ],

    'resources' => [

        'create' => 'Créer',
        'update' => 'Fichier',

        'user' => [
            'singular' => 'Utilisateur',
            'plural' => 'Utilisateurs',

            'fields' => [
                'microsoft_sso_linked' => 'Microsoft Entra',
            ],

            'relations' => [
                'microsoft_identity' => 'Lien Microsoft Entra',
            ],
        ],

        'microsoft_sso_identity' => [
            'singular' => 'Lien Microsoft Entra',
            'plural' => 'Liens Microsoft Entra',

            'fields' => [
                'microsoft_id' => 'ID d’objet Microsoft',
                'token_expires_at' => 'Expiration du jeton',
                'linked_at' => 'Lié le',
            ],

            'relations' => [
                'user' => 'Utilisateur',
            ],
        ],

        'password_reset_token' => [
            'singular' => 'Jeton de réinitialisation du mot de passe',
            'plural' => 'Jetons de réinitialisation du mot de passe',

            'fields' => [
                'email' => 'E-mail',
                'token' => 'Jeton',
                'created_at' => 'Créé le',
            ],
        ],
    ],

];
