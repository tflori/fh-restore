#!/usr/bin/env php
<?php

use GetOpt\ArgumentException;
use GetOpt\ArgumentException\Missing;
use GetOpt\GetOpt;
use GetOpt\Operand;
use Hugga\Console;
use tflori\FhRestore\FileHistory;

require_once __DIR__ . '/vendor/autoload.php';

// ###################
// argument handling #
// ###################

$getOpt = new GetOpt();
$getOpt->addOperands([
  Operand::create('source', Operand::REQUIRED),
  Operand::create('target', Operand::REQUIRED),
]);

$getOpt->addOption(['?', 'help', GetOpt::NO_ARGUMENT, 'Show this help']);
$getOpt->addOption(['d', 'dry-run', GetOpt::NO_ARGUMENT, 'Just write what would happen']);
$getOpt->addOption(['e', 'exclude', GetOpt::MULTIPLE_ARGUMENT, 'Exclude files matching this pattern']);
$getOpt->addOption(['v', 'verbose', GetOpt::NO_ARGUMENT, 'Increase verbosity']);
$getOpt->addOption(['q', 'quite', GetOpt::NO_ARGUMENT, 'Decrease verbosity']);

// process arguments and catch user errors
try {
    try {
        $getOpt->process();
    } catch (Missing $exception) {
        // catch missing exceptions if help is requested
        if (!$getOpt->getOption('help')) {
            throw $exception;
        }
    }
} catch (ArgumentException $exception) {
    file_put_contents('php://stderr', $exception->getMessage() . PHP_EOL);
    echo PHP_EOL . $getOpt->getHelpText();
    return;
}

if ($getOpt->getOption('help')) {
    echo $getOpt->getHelpText();
    return;
}

// #############
// application #
// #############
$console = new Console();
$console->setVerbosity(Console::VERBOSITY_ORDER[2 + $getOpt->getOption('verbose') - $getOpt->getOption('quite')]);

$source = $getOpt->getOperand('source');
$target = $getOpt->getOperand('target');

if (!is_dir($source) || !is_dir($target)) {
    $console->error('<source> and <target> has to be a directory');
    exit(1);
}

$console->info('Beginning to restore from ' . $source . ' to ' . $target);
$fileHistory = new FileHistory($console, (bool)$getOpt->getOption('dry-run'));

foreach ($getOpt->getOption('exclude') as $pattern) {
    $fileHistory->exclude($pattern);
}

$fileHistory->restore($source, $target);
