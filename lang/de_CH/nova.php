<?php

return [
    'panels' => [
        'identification' => [
            'title' => 'Identifikation',
            'id' => 'ID',
            'uuid' => 'UUID',
        ],

        'timestamps' => [
            'title' => 'Zeitstempel',
            'created_at' => 'Erstellt am',
            'updated_at' => 'Zuletzt aktuallisiert am',
            'deleted_at' => 'Archiviert am',
        ],
    ],

    'resources' => [

        'create' => 'Anlegen',
        'update' => 'Aktualisieren',

        'user' => [
            'singular' => 'Benutzer',
            'plural' => 'Benutzer',

            'fields' => [
                'name' => 'Name',
                'email' => 'E-Mail',
                'email_verified_at' => 'E-Mail verifiziert am',
                'password' => 'Passwort',
                'locale' => 'Sprache',
                'created_at' => 'Erstellt am',
                'updated_at' => 'Aktualisiert am',
                'microsoft_sso_linked' => 'Microsoft Entra',
            ],

            'relations' => [
                'microsoft_identity' => 'Microsoft Entra-Verknüpfung',
                'roles' => 'Rollen',
            ],
        ],

        'role' => [
            'singular' => 'Rolle',
            'plural' => 'Rollen',

            'fields' => [
                'name' => 'Name',
                'created_at' => 'Erstellt am',
                'updated_at' => 'Aktualisiert am',
            ],

            'relations' => [
                'users' => 'Benutzer',
            ],
        ],

        'activity' => [
            'singular' => 'Aktivität',
            'plural' => 'Aktivitäten',

            'fields' => [
                'log_name' => 'Name',
                'description' => 'Beschreibung',
                'event' => 'Ereignis',
                'properties' => 'Eigenschaften',
                'created_at' => 'Erstellt am',
            ],

            'relations' => [
                'subject' => 'Betreff',
                'causer' => 'Verursacher',
            ],
        ],

        'microsoft_sso_identity' => [
            'singular' => 'Microsoft Entra-Verknüpfung',
            'plural' => 'Microsoft Entra-Verknüpfungen',

            'fields' => [
                'microsoft_id' => 'Microsoft-Objekt-ID',
                'token_expires_at' => 'Token gültig bis',
                'linked_at' => 'Verknüpft am',
            ],

            'relations' => [
                'user' => 'Benutzer',
            ],
        ],

        'password_reset_token' => [
            'singular' => 'Passwort-Zurücksetzungs-Token',
            'plural' => 'Passwort-Zurücksetzungs-Tokens',

            'fields' => [
                'email' => 'E-Mail',
                'token' => 'Token',
                'created_at' => 'Erstellt am',
            ],
        ],
    ],
];
