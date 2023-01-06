<?php

namespace Blackfire\Player\Tests\Console;

use Blackfire\Player\Console\PlayerCommand;
use Blackfire\Player\Console\ScenarioHydrator;
use Blackfire\Player\Tests\VarDumper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\VarDumper\Cloner\VarCloner;

class ScenarioHydratorTest extends TestCase
{
    public function testItLoads()
    {
        $bkf = <<<EOF
name "BKF Scenarios"

endpoint "http://tiptop.endpoint"

set user_login "user_logouille"
set user_password "pwdsoupe"

scenario
    name 'Visitor'

    visit url('/pricing')
    visit url('/integrations')

scenario
    name 'Authenticated'
    endpoint "http://tiptop.endpoint.minicontroll"
    visit url('/login')

EOF;

        $stream = fopen('php://memory', 'r+b');
        fwrite($stream, $bkf);
        fseek($stream, 0);

        $input = new ArrayInput([
            'run',
            'file' => $stream,
            '--endpoint' => 'http://localhost',
            '--variable' => ['user_login=escargot'],
        ], (new PlayerCommand())->getDefinition());

        $hydrator = new ScenarioHydrator();
        $scenarios = $hydrator->hydrate($input);

        $expected = <<<EOEXPECTED
Blackfire\Player\ScenarioSet {
  -keys: [
    "" => true
  ]
  -extraBag: Blackfire\Player\ValueBag {
    -values: []
  }
  -name: ""BKF Scenarios""
  -variables: [
    "endpoint" => "'http://localhost'"
    "user_login" => "'escargot'"
    "user_password" => ""pwdsoupe""
  ]
  -endpoint: "'http://localhost'"
  -blackfireEnvironment: null
  -scenarios: [
    Blackfire\Player\Scenario {
      #next: null
      #blackfireProfileUuid: null
      -name: "'Visitor'"
      -errors: []
      -file: null
      -line: 8
      -auth: null
      -headers: []
      -wait: null
      -json: null
      -followRedirects: null
      -blackfire: null
      -blackfireRequest: null
      -blackfireScenario: null
      -samples: null
      -warmup: null
      -blockStep: Blackfire\Player\Step\VisitStep {
        #next: Blackfire\Player\Step\VisitStep {
          #next: null
          #blackfireProfileUuid: null
          -name: null
          -errors: []
          -file: null
          -line: 12
          -auth: null
          -headers: []
          -wait: null
          -json: null
          -followRedirects: null
          -blackfire: null
          -blackfireRequest: null
          -blackfireScenario: null
          -samples: null
          -warmup: null
          -expectations: []
          -variables: []
          -assertions: []
          -dumpValuesName: []
          -method: null
          -parameters: []
          -body: null
          -uri: "url('/integrations')"
        }
        #blackfireProfileUuid: null
        -name: null
        -errors: []
        -file: null
        -line: 11
        -auth: null
        -headers: []
        -wait: null
        -json: null
        -followRedirects: null
        -blackfire: null
        -blackfireRequest: null
        -blackfireScenario: null
        -samples: null
        -warmup: null
        -expectations: []
        -variables: []
        -assertions: []
        -dumpValuesName: []
        -method: null
        -parameters: []
        -body: null
        -uri: "url('/pricing')"
      }
      -variables: [
        "endpoint" => "'http://localhost'"
        "user_login" => "'escargot'"
        "user_password" => ""pwdsoupe""
      ]
      -endpoint: "'http://localhost'"
      -key: null
    }
    Blackfire\Player\Scenario {
      #next: null
      #blackfireProfileUuid: null
      -name: "'Authenticated'"
      -errors: []
      -file: null
      -line: 14
      -auth: null
      -headers: []
      -wait: null
      -json: null
      -followRedirects: null
      -blackfire: null
      -blackfireRequest: null
      -blackfireScenario: null
      -samples: null
      -warmup: null
      -blockStep: Blackfire\Player\Step\VisitStep {
        #next: null
        #blackfireProfileUuid: null
        -name: null
        -errors: []
        -file: null
        -line: 17
        -auth: null
        -headers: []
        -wait: null
        -json: null
        -followRedirects: null
        -blackfire: null
        -blackfireRequest: null
        -blackfireScenario: null
        -samples: null
        -warmup: null
        -expectations: []
        -variables: []
        -assertions: []
        -dumpValuesName: []
        -method: null
        -parameters: []
        -body: null
        -uri: "url('/login')"
      }
      -variables: [
        "endpoint" => "'http://localhost'"
        "user_login" => "'escargot'"
        "user_password" => ""pwdsoupe""
      ]
      -endpoint: ""http://tiptop.endpoint.minicontroll""
      -key: null
    }
  ]
}
EOEXPECTED;

        $this->assertSame($expected, $this->getVarDumperDump($scenarios));
    }

    private function getVarDumperDump($data)
    {
        $h = fopen('php://memory', 'r+');
        $cloner = new VarCloner();
        $cloner->setMaxItems(-1);
        $dumper = new VarDumper($h);
        $dumper->setColors(false);
        $dumper->dump($cloner->cloneVar($data)->withRefHandles(false));
        $data = stream_get_contents($h, -1, 0);
        fclose($h);

        return rtrim($data);
    }
}
