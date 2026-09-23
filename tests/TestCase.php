<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * Hard-block any real outbound HTTP call from any test. Tests that hit
     * the Graph API (or any other HTTP client call) must explicitly
     * Http::fake(...) it. This exists because a test once mutated a
     * Property's status without faking the queue/HTTP layer, and — since
     * QUEUE_CONNECTION is forced to "sync" for tests while the real
     * FACEBOOK_PAGE_TOKEN/INSTAGRAM_ACCESS_TOKEN still leak through from the
     * container's env_file — it silently published real garbage posts to
     * the production Facebook Page. Never remove this without replacing it
     * with an equally strong guard.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }
}
