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

    /*
    |--------------------------------------------------------------------------
    | Binary Path
    |--------------------------------------------------------------------------
    |
    | The path to your application binary (relative to project root).
    | Example: 'bin/afya' or 'bin/ut'
    |
    */
    'binary_path' => env('DEPLOY_BINARY_PATH', 'bin/ut'),

    /*
    |--------------------------------------------------------------------------
    | Export Tracker Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the export tracker for tracking which files have been exported.
    | TTL: 0 means never expire (infinite storage).
    |
    */
    'export_tracker_base_path' => env('DEPLOY_EXPORT_TRACKER_PATH', 'storage/app/export_tracker'),
    'export_tracker_ttl' => env('DEPLOY_EXPORT_TRACKER_TTL', 0),

    /*
    |--------------------------------------------------------------------------
    | Publish Configuration
    |--------------------------------------------------------------------------
    |
    | Configure source and target paths for publishing directives.
    |
    */
    'publish_source' => 'app/Directives',
    'publish_target' => 'app/Directives',

    /*
    |--------------------------------------------------------------------------
    | Export Assets Configuration
    |--------------------------------------------------------------------------
    |
    | Configure assets to export during deployment.
    |
    */
    'export_assets' => [
        'storage/app/public/images',
        'storage/app/public/videos',
        'storage/app/public/assets',
    ],

    /*
    |--------------------------------------------------------------------------
    | HLS Configuration
    |--------------------------------------------------------------------------
    |
    | Configure HLS generation settings for videos:hls directive.
    |
    */
    'hls' => [
        'segment_duration' => 4,
        'crf' => 28,
        'preset' => 'fast',
        'audio_bitrate' => '128k',
        'resolutions' => ['144', '240', '360', '480', '720'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Video Compression Configuration
    |--------------------------------------------------------------------------
    |
    | Configure video compression settings for videos:compress directive.
    |
    */
    'video_compress' => [
        'width' => 0,
        'height' => 0,
        'crf' => 28,
        'preset' => 'medium',
        'video_codec' => 'libx264',
        'audio_codec' => 'aac',
        'audio_bitrate' => '128k',
        'pixel_format' => 'yuv420p',
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Compression Configuration
    |--------------------------------------------------------------------------
    |
    | Configure image compression settings for images:compress directive.
    |
    */
    'image_compress' => [
        'png_quality' => '45-50',
        'jpg_quality' => 50,
        'max_size' => 0,
        'strip_meta' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pipelines Configuration
    |--------------------------------------------------------------------------
    |
    | Configure custom pipelines to execute after deployment.
    | ⚠️ IMPORTANT: Only string signatures are supported!
    |
    | Examples:
    | 'pipelines' => [
    |     'queue:restart',
    |     'afya:seed --force',
    | ],
    |
    */
    'pipelines' => [
        'queue:restart',
    ],
];
