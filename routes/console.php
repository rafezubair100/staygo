<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('staygo:about', function () {
    $this->info('Staygo - worldwide hotel discovery and booking foundation.');
})->purpose('Display Staygo project information');
