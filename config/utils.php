<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Git Repositories
    |--------------------------------------------------------------------------
    |
    | Configure your Git repositories for push directives.
    |
    */
    'repositories' => [
        'github' => 'git@github.com:andydefer/laravel-utils.git',
    ],

    /*
    |--------------------------------------------------------------------------
    | File Extensions
    |--------------------------------------------------------------------------
    |
    | Default extensions for git diff and extension recipes.
    |
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
    |
    | Configure your deployment settings for O2Switch.
    |
    */
    'deployment' => [
        'ssh_key' => env('DEPLOY_SSH_KEY', 'o2switch'),
        'remote_path' => env('DEPLOY_REMOTE_PATH', '~/sites/laravel-utils.com'),
        'git_branch' => env('DEPLOY_GIT_BRANCH', 'master'),
    ],
];
