#!/usr/bin/env php

<?php

$buildRoot = __DIR__;
$phar = new Phar($buildRoot . '/build/ccu.phar', 0, 'ccu.phar');
$include = '/^(?=(.*src|.*bin|.*vendor))(.*)$/i';
$phar->buildFromDirectory($buildRoot, $include);
$phar->setStub("#!/usr/bin/env php\n" . $phar->createDefaultStub("bin/ccu"));
