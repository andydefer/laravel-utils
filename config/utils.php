<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Git Repositories
    |--------------------------------------------------------------------------
    |
    | Define your git remote repositories here. The key is the alias used
    | in the command line, and the value is the remote URL.
    |
    | Example:
    | 'github' => 'git@github.com:andydefer/laravel-utils.git'
    |
    */
    'repositories' => [
        'github' => env('GIT_REPO_GITHUB', 'git@github.com:andydefer/laravel-utils.git'),
        'o2switch' => env('GIT_REPO_O2SWITCH', 'ssh://kaan9852@kaan9852.odns.fr/home/kaan9852/git/afya-medical.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Extensions for Git Diff
    |--------------------------------------------------------------------------
    |
    | Define the default file extensions to include when generating a git diff.
    | These will be pre-selected in the interactive mode.
    |
    | Example:
    | 'default_extensions' => ['php', 'js', 'ts', 'vue', 'css', 'html']
    |
    */
    'default_extensions' => [
        'php',
        'js',
        'ts',
        'tsx',
        'jsx',
        'vue',
        'css',
        'scss',
        'html',
        'xml',
        'json',
        'yaml',
        'yml',
        'md',
        'sh',
        'bash',
    ],

    /*
    |--------------------------------------------------------------------------
    | Extension Recipes
    |--------------------------------------------------------------------------
    |
    | Define named groups of extensions for quick selection.
    | Each recipe is a key-value pair where the key is the recipe name
    | and the value is an array of extensions.
    |
    | Example:
    | 'extension_recipes' => [
    |     'frontend' => ['js', 'ts', 'tsx', 'jsx', 'vue', 'css', 'scss', 'html'],
    |     'backend' => ['php', 'py', 'rb', 'go', 'rs', 'java'],
    |     'fullstack' => ['php', 'js', 'ts', 'tsx', 'jsx', 'vue', 'css', 'scss', 'html'],
    | ]
    |
    */
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
];
