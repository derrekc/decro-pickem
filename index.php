<?php

// Retrieve instance of the framework
$f3=require('lib/base.php');

\Template::instance()->extend('pagebrowser', '\Pagination::renderTag');

// Initialize CMS
$f3->config('app/config.ini');

// Define routes
$f3->config('app/routes.ini');

$f3->route('GET @welcome: /', 'DashboardController->home');

// Execute application
$f3->run();
