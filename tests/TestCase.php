<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Several existing tests (and the Clinical feature tests) assume a
     * baseline Business/Branch already exists (id 1) — true on the real
     * dev DB they were written against, but not on a freshly migrated
     * database. RefreshDatabase's one-time migrate:fresh only migrates,
     * not seeds, unless this is set — this makes it also run
     * DatabaseSeeder (Business/Branch/reference data) right after, so
     * that assumption holds in the isolated test database too.
     */
    protected $seed = true;
}
