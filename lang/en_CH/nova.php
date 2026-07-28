<?php

return [
    'panels' => [
        'identification' => [
            'title' => 'Identification',
            'id' => 'ID',
            'uuid' => 'UUID',
        ],

        'timestamps' => [
            'title' => 'Timestamps',
            'created_at' => 'Created at',
            'updated_at' => 'Update at',
            'deleted_at' => 'Archived at',
        ],
    ],

    'resources' => [

        'create' => 'Create',
        'update' => 'Update',

        'user' => [
            'singular' => 'User',
            'plural' => 'Users',

            'fields' => [
                'name' => 'Name',
                'email' => 'Email',
                'email_verified_at' => 'Email verified at',
                'password' => 'Password',
                'locale' => 'Locale',
                'created_at' => 'Created at',
                'updated_at' => 'Updated at',
                'microsoft_sso_linked' => 'Microsoft Entra',
            ],

            'relations' => [
                'roles' => 'Roles',
                'microsoft_identity' => 'Microsoft Entra link',
            ],
        ],

        'role' => [
            'singular' => 'Role',
            'plural' => 'Roles',

            'fields' => [
                'name' => 'Name',
                'created_at' => 'Created at',
                'updated_at' => 'Updated at',
            ],

            'relations' => [
                'users' => 'Users',
            ],
        ],

        'activity' => [
            'singular' => 'Activity',
            'plural' => 'Activities',

            'fields' => [
                'log_name' => 'Name',
                'description' => 'Description',
                'event' => 'Event',
                'properties' => 'Properties',
                'created_at' => 'Created at',
            ],

            'relations' => [
                'subject' => 'Subject',
                'causer' => 'Causer',
            ],
        ],

        'microsoft_sso_identity' => [
            'singular' => 'Microsoft Entra link',
            'plural' => 'Microsoft Entra links',

            'fields' => [
                'microsoft_id' => 'Microsoft object ID',
                'token_expires_at' => 'Token expires at',
                'linked_at' => 'Linked at',
            ],

            'relations' => [
                'user' => 'User',
            ],
        ],

        'password_reset_token' => [
            'singular' => 'Password reset token',
            'plural' => 'Password reset tokens',

            'fields' => [
                'email' => 'Email',
                'token' => 'Token',
                'created_at' => 'Created at',
            ],
        ],
    ],
];
