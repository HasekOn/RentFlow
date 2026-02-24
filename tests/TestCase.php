<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * API route prefix for versioned endpoints.
     */
    protected function apiUrl(string $path): string
    {
        return '/api/v1' . $path;
    }
}
