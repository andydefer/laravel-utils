<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Git Repositories
    |--------------------------------------------------------------------------
    */
    'repositories' => [
        'github' => 'git@github.com:andydefer/laravel-utils.git',
    ],

    /*
    |--------------------------------------------------------------------------
    | File Extensions
    |--------------------------------------------------------------------------
    */
    'default_extensions' => [
        'php',
        'js',
        'ts',
        'css',
        'html',
        'json',
        'yaml',
        'md',
    ],

    'extension_recipes' => [
        'frontend' => [
            'js',
            'ts',
            'tsx',
            'jsx',
            'vue',
            'css',
            'scss',
            'sass',
            'less',
            'html',
            'xml',
        ],
        'backend' => [
            'php',
            'py',
            'rb',
            'go',
            'rs',
            'java',
            'c',
            'cpp',
            'h',
            'hpp',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Deployment Configuration
    |--------------------------------------------------------------------------
    */
    'deployment' => [
        'ssh_key' => env('DEPLOY_SSH_KEY', 'o2switch'),
        'remote_path' => env('DEPLOY_REMOTE_PATH', '~/sites/laravel-utils.com'),
        'git_branch' => env('DEPLOY_GIT_BRANCH', 'master'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Binary Path
    |--------------------------------------------------------------------------
    */
    'binary_path' => env('DEPLOY_BINARY_PATH', 'bin/ut'),

    /*
    |--------------------------------------------------------------------------
    | Export Tracker Configuration
    |--------------------------------------------------------------------------
    */
    'export_tracker_base_path' => env('DEPLOY_EXPORT_TRACKER_PATH', 'storage/app/export_tracker'),
    'export_tracker_ttl' => env('DEPLOY_EXPORT_TRACKER_TTL', 0),

    /*
    |--------------------------------------------------------------------------
    | Publish Configuration
    |--------------------------------------------------------------------------
    */
    'publish_source' => 'app/Directives',
    'publish_target' => 'app/Directives',

    /*
    |--------------------------------------------------------------------------
    | Export Assets Configuration
    |--------------------------------------------------------------------------
    */
    'export_assets' => [
        'storage/app/public/images',
        'storage/app/public/videos',
        'storage/app/public/assets',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pipelines Configuration
    |--------------------------------------------------------------------------
    | ⚠️ IMPORTANT: Only string signatures are supported!
    |
    | Examples:
    | 'pipelines' => [
    |     'queue:restart',
    |     'afya:seed --force',
    | ],
    */
    'pipelines' => [
        'utils:support --all',
    ],
];
