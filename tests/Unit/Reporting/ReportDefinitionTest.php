<?php

namespace Tests\Unit\Reporting;

use App\Domain\Reporting\Services\ReportDefinition;
use PHPUnit\Framework\TestCase;

class ReportDefinitionTest extends TestCase
{
    public function test_missing_and_null_presentation_options_use_defaults(): void
    {
        foreach ([[], ['chart' => ['type' => null], 'view' => ['mode' => null]]] as $input) {
            $definition = ReportDefinition::normalize($input);
            $this->assertSame('none', $definition['chart']['type']);
            $this->assertSame('table', $definition['view']['mode']);
        }

        $definition = ReportDefinition::normalize(['view' => ['kpis' => [[], ['fn' => null]]]]);
        $this->assertSame(['count', 'count'], array_column($definition['view']['kpis'], 'fn'));
    }

    public function test_valid_and_invalid_presentation_options_are_normalized(): void
    {
        $definition = ReportDefinition::normalize([
            'chart' => ['type' => 'bar'],
            'view' => ['mode' => 'dashboard', 'kpis' => [['fn' => 'sum'], ['fn' => 'invalid']]],
        ]);
        $this->assertSame('bar', $definition['chart']['type']);
        $this->assertSame('dashboard', $definition['view']['mode']);
        $this->assertSame(['sum', 'count'], array_column($definition['view']['kpis'], 'fn'));
    }
}
