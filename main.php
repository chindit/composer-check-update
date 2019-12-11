<?php
declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

use App\Command\CheckUpdatesCommand;
use Symfony\Component\Console\Application;

$application = new Application('composer-check-update', '0.0.1');
$command = new CheckUpdatesCommand();

$application->add($command);

$application->setDefaultCommand($command->getName(), true);
$application->run();
