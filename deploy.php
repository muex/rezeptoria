<?php
namespace Deployer;

require 'recipe/symfony.php';

// Project name
set('application', 'rezeptoria');
set('http_user', 'ssh-w0186f22');
set('http_group', 'w0186f22');
set('env', ['APP_ENV' => 'prod']);
// Project repository
set('repository', 'https://github.com/muex/rezeptoria.git');
set('writable_mode', 'chmod');
// Define binaries
set('/usr/bin/php', 'php');
// [Optional] Allocate tty for git clone. Default value is false.
//set('git_tty', true);
// Set maximum number of releases
set('keep_releases', 5);

// Use current date as release name
set('release_name', fn() => run('echo $(date "+%Y-%m-%dT%H-%M-%S")'));
host(getenv('DEPLOYER_HOST'))
    ->set('remote_user', getenv('DEPLOYER_USER'))
    ->set('identity_file', '~/.ssh/id_rsa') // Pfad zum privaten Schlüssel
    ->set('deploy_path', '/www/htdocs/w0186f22/rezeptoria.de');
// Shared files/dirs between deploys
add('shared_files', ['.env.local']);
add('shared_dirs', []);

// Writable dirs by web server
add('writable_dirs', []);


// Hosts

host('w0186f22.kasserver.com')
    ->set('deploy_path', '/www/htdocs/w0186f22/rezeptoria.de');

// Tasks

task('build', function () {
    run('cd {{release_path}} && build');
    run('cd {{release_path}} && php bin/console tailwind:build --minify');
    run('cd {{release_path}} && php bin/console asset-map:compile');

});
before('deploy:cache:clear', 'build');
task('deploy:cache:clear', function () {
    run('php {{release_path}}/bin/console cache:clear --env=prod --no-debug');
});
// [Optional] if deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');

// Migrate database before symlink new release.

before('deploy:symlink', 'database:migrate');
