[![Code Coverage](https://scrutinizer-ci.com/g/Appdynamics/ansible-php/badges/coverage.png?b=develop)](https://scrutinizer-ci.com/g/Appdynamics/ansible-php/?branch=develop) [![Build Status](https://scrutinizer-ci.com/g/Appdynamics/ansible-php/badges/build.png?b=develop)](https://scrutinizer-ci.com/g/Appdynamics/ansible-php/build-status/develop)

# Writing an Ansible module in PHP

## Use case

Primarily the issue has been that most CLI apps that help to deploy applications can give little insight into what has actually changed. Sometimes it is possible to use `changed_when` and inspect stderr/stdout, but most of the time it is not possible to tell reliably from just that. This is the same with helper CLI applications for websites like Drush and WP-CLI.

## Getting started with Composer

Use the standard `composer require` command:

```bash
composer require appdynamics/ansible-php:dev-develop
```

## Boilerplate

Unfortunately there is not much of a way to get around Ansible's limitations with other languages besides Python. And there probably will never be. Therefore, you need to have this boilerplate at the top of your module:

```php
#!/usr/bin/env php
<?php
function _require_ansible_php() {
    global $argv;
    $argFile = $argv[count($argv) - 1];
    $args = preg_split('/\s+/', file_get_contents($argFile), null, PREG_SPLIT_NO_EMPTY);
    foreach ($args as $i => $arg) {
        list($key, $val) = preg_split('/=/', $arg, 2);
        if ($key === 'ansible_php') {
            require_once $val . '/vendor/autoload.php';
            return true;
        }
    }
    return false;
}
if (!_require_ansible_php() || !class_exists('AnsiblePhp\AnsibleModule')) {
    print json_encode(array('failed' => true, 'msg' => 'Failed to find AnsiblePhp library'));
    exit(1);
}
use AnsiblePhp\AnsibleModule;
```

## Example module

Here we are writing a very basic module to hook into WordPress and update an option idempotently (to not make any change if the option is already set to the value requested).

The task will look like this:

```yaml
# Variable exists like this in YAML:
# some_key_value: {dict_key: 'value'}

- name: 'Set some_key to [dict_key => value]'
  wordpress: ansible_php=/where-i-will-deploy-ansible-php
             working_dir=/srv/www
             option=some_key
             value={{some_key_value|to_json}}
```

Very important:

* You must *always* pass the `ansible_php` key and it must be a path where this source code lives. This can be any code base that is using Composer and has this Ansible PHP added as a dependency.
* Always use the `to_json` filter for non-scalar values.

```php
// Boilerplate goes here

function main() {
    $module = new AnsibleModule([
        'working_dir' => ['type' => 'directory', 'required' => true],
        'option' => [],
        'value' => [],
    ]);
    $changed = false;
    $data = [];

    // Get WordPress bootstrap include in (wp-config.php)
    $wpDir = realpath($module->params['working_dir']);
    define('ABSPATH', $wpDir.'/'); // Note the ending /
    $config = sprintf('%s/wp-config.php', $wpDir);
    require $config;

    // Idempotently update an option
    // Note that keys specified in the argument spec (the argument to AnsibleModule) are always existant but if not specified they are null
    // If a key is not specified by the task but has a default value, the key will have that value
    if ($module->params['option']) {
        if (!isset($module->params['value'])) {
            // You must *always* pass at least the msg key to #failJson()
            $module->failJson(['msg' => 'Value ("value" key) must be set when using option']);
        }

        $value = $module->params['value'];

        // We may want to only decode from JSON if we can see a complex type
        if ($value[0] == '{' || $value[0] == '[') {
            $value = $module->decodeJson($value); // #decodeJson checks for errors and will throw an exception
        }

        // Do not use null or anything non-random because null can be in the database just as easily
        $randomValue = md5(wp_rand().time());
        $current = get_option($module->params['option'], $randomValue);

        if ($current != $value || $current == $randomValue) {
            $changed = true;
            update_option($module->params['option'], $value);
        }
        else {
            $value = $current;
        }

        $data['option'] = [$module->params['option'] => $value];
    }

    // Exit correctly, and do not allow above code to change the 'changed' key
    $module->exitJson(array_merge($value, [
        'changed' => $changed,
    ]);
}

main();
```

This file lives in your Ansible playbook's `library` directory or a path you specify for modules in your Ansible configuration or command line. In this case it is named exactly `wordpress`.

## Notes

* There is an error handler and exception handler that will always act like `fail_json` and print out the exception or error message and a backtrace.
* Be careful when including 3rd party code and be sure to set up `php.ini` to your liking. On a production end-point you may want all errors off except for logging, even for CLI.
* WordPress plugins are of varying quality and may trigger warnings that will trigger exceptions in your module that may not appear on the site due to PHP settings and WordPress' very relaxed error handler by default. This can be similar (but less so) for Drupal.
* Regardless of framework, if your own site's code (that you wrote) is triggering errors in your Ansible module you should probably fix that code and preferably *not* send me an issue or pull request for some strange use case.

### Example error output (`#failJson`)

Note that it will not be prettified from Ansible (this is from `ansible-playbook -v`).

```json
{
    "msg": "Argument \"random\" is invalid",
    "line": 72,
    "bt": [{
        "file": "\/some-place\/test.php",
        "line": 22,
        "function": "__construct",
        "class": "AnsiblePhp\\AnsibleModule",
        "type": "->",
        "args": [{
            "dir": []
        }]
    }],
    "failed": true
}
```

## Locally testing

Instead of using `ansible` you can do something like the following:

```bash
# Set up a new project that has Ansible PHP
mkdir bin
curl -sS https://getcomposer.org/installer | php -- --install-dir=bin
php bin/composer.phar require appdynamics/ansible-php:dev-develop

# Create the argument file (just like Ansible does). Note the argument ansible_php
echo 'ansible_php=. working_dir=/something-real key=something value={"dict_key": "value"}' > args

# Run
php mymodule args ; echo
```

There is an `echo` here only because no newline is printed after the JSON output (which is on purpose).

# Deploying Ansible PHP alone

Before you can use Ansible-PHP in your own module, you need to send this library to the machine and then configure it with Composer. These are the tasks:

```yaml
- name: 'Install Composer'
  shell: curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer
         creates=/usr/bin/composer

- name: 'Install ansible-php'
  git: repo=git://github.com/Appdynamics/ansible-php
       dest=/opt/www/ansible-php
  register: ansible_php_git

- name: 'Set up ansible-php'
  composer: working_dir=/opt/www/ansible-php
  when: ansible_php_git.changed
```

Note: This is to install a barebones version of Ansible-PHP. You may want to consider using `composer require appdynamics/ansible-php:dev-develop` and building your own project so that you can handle all dependencies properly. You can also add Ansible PHP to any existing code base already using Composer and use that path (with `/vendor/ansible-php`) for where Ansible PHP lives.

# Contributing

* PSR standards
* Make a fork, a feature branch off the `develop` branch here (`man git-branch`), and send a pull request
