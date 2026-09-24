<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // Les traits de RefreshDatabase sont appliqués par test (Pest) pour maîtriser
    // le coût : SQLite en mémoire, voir phpunit.xml (DB_CONNECTION=sqlite, :memory:).
}
