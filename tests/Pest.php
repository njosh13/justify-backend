<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');

uses()->in('Unit');

require_once __DIR__.'/Feature/Aro/Helpers.php';
require_once __DIR__.'/Feature/Billing/Helpers.php';
