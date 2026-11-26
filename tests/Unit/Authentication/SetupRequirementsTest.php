<?php

namespace Tests\Unit\Authentication;

use App\Domain\Setup\Services\RequirementsChecker;
use Tests\TestCase;

class SetupRequirementsTest extends TestCase
{
    public function test_requirements_match_supported_php_and_database_drivers(): void
    {
        $checks = collect((new RequirementsChecker)->checks())->keyBy('key');

        $this->assertSame('8.3.0', RequirementsChecker::MIN_PHP);
        $this->assertTrue($checks['ext.database_driver']['required']);
        $this->assertSame(extension_loaded('pdo_mysql') || extension_loaded('pdo_sqlite'), $checks['ext.database_driver']['passed']);
        $this->assertFalse($checks['ext.pdo_mysql']['required']);
        $this->assertFalse($checks['ext.pdo_sqlite']['required']);
    }
}
