# composer-check-update

_composer-check-update_ is a simple tool to see if some of your
composer dependencies are outdated.

A dependency listed in <span style="color:red">red is a **major** update</span>.

A dependency listed in <span style="color:cyan">cyan is a **minor** update</span>.

A dependency listed in <span style="color:green">green is a **patch** update</span>.

### Usage
`./bin/ccu`

OR

`./ccu.phar`

### Parameters
##### Composer.json path
If _composer.json_ is not located in the same directory, use `--composer /path/to/composer`
or `-c /path/to/composer`.

##### Ignore «require-dev» section
Add `--no-dev` flag

##### Usage example
```
$ ./ccu.phar 

Found 29 packages in «require» section.  Scanning…
 29/29 [============================] 100%
Found 13 packages in «require-dev» section.  Scanning…
 13/13 [============================] 100%

+-----------------------------------+-----------------+--------------+
| Package                           | Current version | Last version |
+-----------------------------------+-----------------+--------------+
| algolia/algoliasearch-client-php  | ^2.1            | ^2.5         |
| anhskohbo/no-captcha              | ^3.0            | ^3.1         |
| fideloper/proxy                   | ~4.0            | ~4.2         |
| hoa/option                        | ~1.0            | ~1.17        |
| laravel/framework                 | 5.7.*           | 6.7.*        |
| laravel/horizon                   | ^3.1            | ^3.4         |
| laravel/passport                  | ^7.4            | ^8.0         |
| laravelcollective/html            | ^5.7            | ^6.0         |
| myclabs/php-enum                  | ^1.5            | ^1.7         |
| php-http/guzzle6-adapter          | ^1.1            | ^2.0         |
| sentry/sentry-laravel             | 1.0.1           | 1.5.0        |
| skagarwal/google-places-api       | ^1.2            | ^1.5         |
| spatie/array-to-xml               | ^2.7            | ^2.9         |
| spatie/laravel-cors               | ^1.0            | ^1.6         |
| spatie/laravel-fractal            | ^5.3            | ^5.6         |
| spatie/laravel-menu               | ^3.0            | ^3.4         |
| spatie/laravel-translation-loader | ^2.1            | ^2.4         |
| spipu/html2pdf                    | ^5.1            | ^5.2         |
| sybio/image-workshop              | ^2.0            | ^2.1         |
| barryvdh/laravel-ide-helper       | ^2.5            | ^2.6         |
| beyondcode/laravel-dump-server    | ^1.2            | ^1.3         |
| filp/whoops                       | ~2.0            | ~2.5         |
| fzaninotto/faker                  | ~1.4            | ~1.9         |
| laravel/tinker                    | ^1.0            | ^2.0         |
| mockery/mockery                   | ^1.0            | ^1.3         |
+-----------------------------------+-----------------+--------------+
There are 25 packages to update.
Tip: Re-run the command with «-u» to update your composer.json
```
