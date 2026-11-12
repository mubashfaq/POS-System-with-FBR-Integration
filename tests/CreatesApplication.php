<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $storagePath = '/tmp/pos_testing_storage';
        if (!is_dir($storagePath . '/framework/cache')) {
            @mkdir($storagePath . '/framework/cache/data', 0777, true);
            @mkdir($storagePath . '/framework/views', 0777, true);
            @mkdir($storagePath . '/framework/sessions', 0777, true);
            @mkdir($storagePath . '/logs', 0777, true);
        }
        $app->useStoragePath($storagePath);

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
