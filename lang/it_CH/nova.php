<?php

return [

    'panels' => [
        'identification' => [
            'title' => 'Identificazione',
            'id' => 'ID',
            'uuid' => 'UUID',
        ],

        'timestamps' => [
            'created_at' => 'creato presso',
            'updated_at' => 'aggiornato a',
            'deleted_at' => 'archiviato in',
        ],
    ],

    'resources' => [

        'create' => 'Creare',
        'update' => 'Aggiornare',

        'user' => [
            'singular' => 'Utente',
            'plural' => 'Utenti',

            'fields' => [
                'microsoft_sso_linked' => 'Microsoft Entra',
            ],

            'relations' => [
                'microsoft_identity' => 'Collegamento Microsoft Entra',
            ],
        ],

        'microsoft_sso_identity' => [
            'singular' => 'Collegamento Microsoft Entra',
            'plural' => 'Collegamenti Microsoft Entra',

            'fields' => [
                'microsoft_id' => 'ID oggetto Microsoft',
                'token_expires_at' => 'Scadenza token',
                'linked_at' => 'Collegato il',
            ],

            'relations' => [
                'user' => 'Utente',
            ],
        ],

        'password_reset_token' => [
            'singular' => 'Token di reimpostazione password',
            'plural' => 'Token di reimpostazione password',

            'fields' => [
                'email' => 'E-mail',
                'token' => 'Token',
                'created_at' => 'Creato il',
            ],
        ],
    ],

];
