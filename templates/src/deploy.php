<?php

declare(strict_types=1);

namespace Deployer;

// Include base recipes
require 'recipe/common.php';
require 'contrib/cachetool.php';
require 'contrib/rsync.php';

// Include hosts
import('.deployment/hosts.yaml');

// Define binaries
set('bin/php', 'php');
set('bin/typo3_console', '{{release_path}}/vendor/bin/typo3');

// Set maximum number of releases
set('keep_releases', 5);

// Use current date as release name
set('release_name', fn () => run('echo $(date "+%Y-%m-%dT%H-%M-%S")'));

// Set TYPO3 docroot
set('typo3_webroot', 'public');

// Set shared directories
$sharedDirectories = [
    '{{typo3_webroot}}/fileadmin',
    '{{typo3_webroot}}/typo3temp',
];
set('shared_dirs', $sharedDirectories);

// Set shared files
$sharedFiles = [
    '.env',
];
set('shared_files', $sharedFiles);

// Set writable directories
set('writable_dirs', [
    'config',
    'var',
    '{{typo3_webroot}}/fileadmin',
    '{{typo3_webroot}}/typo3conf',
    '{{typo3_webroot}}/typo3temp',
]);

// Define all rsync excludes
$exclude = [
    // OS specific files
    '.DS_Store',
    'Thumbs.db',
    // Project specific files and directories
    '.ddev',
    '.editorconfig',
    '.fleet',
    '.git*',
    '.idea',
    '.php-cs-fixer.dist.php',
    '.vscode',
    'auth.json',
    'deploy.php',
    'phpstan.neon',
    'phpunit.xml',
    'README*',
    'rector.php',
    'typoscript-lint.yml',
    '/.build',
    '/.deployment',
    '/var',
    '/**/Tests/*',
];

// Define rsync options
set('rsync', [
    'exclude' => array_merge($sharedDirectories, $sharedFiles, $exclude),
    'exclude-file' => false,
    'include' => [],
    'include-file' => false,
    'filter' => [],
    'filter-file' => false,
    'filter-perdir' => false,
    'flags' => 'az',
    'options' => ['delete'],
    'timeout' => 300,
]);
set('rsync_src', './');

// Use rsync to update code during deployment
task('deploy:update_code', function () {
    invoke('rsync:warmup');
    invoke('rsync');
});

// TYPO3 tasks
desc('Flush all caches');
task('typo3:cache_flush', function () {
    run('{{bin/typo3_console}} cache:flush');
});

desc('Warm up caches');
task('typo3:cache_warmup', function () {
    run('{{bin/typo3_console}} cache:warmup');
});

desc('Set up all installed extensions');
task('typo3:extension_setup', function () {
    run('{{bin/typo3_console}} extension:setup');
});

desc('Fix folder structure');
task('typo3:fix_folder_structure', function () {
    run('{{bin/typo3_console}} install:fixfolderstructure');
});

desc('Update language files');
task('typo3:language_update', function () {
    run('{{bin/typo3_console}} language:update');
});

desc('Execute upgrade wizards');
task('typo3:upgrade_all', function () {
    run('{{bin/typo3_console}} upgrade:run');
});

// Register TYPO3 tasks
before('deploy:symlink', function () {
    invoke('typo3:fix_folder_structure');
    invoke('typo3:language_update');
});
after('deploy:symlink', function () {
    invoke('typo3:extension_setup');
    invoke('typo3:upgrade_all');
    invoke('typo3:cache_flush');
    invoke('cachetool:clear:opcache');
    invoke('typo3:cache_warmup');
});

// Main deployment task
desc('Deploy TYPO3 project');
task('deploy', [
    'deploy:prepare',
    'deploy:publish',
]);

// Unlock on failed deployment
after('deploy:failed', 'deploy:unlock');
