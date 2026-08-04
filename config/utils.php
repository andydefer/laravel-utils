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
];
