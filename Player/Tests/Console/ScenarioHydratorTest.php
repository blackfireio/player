<?php

namespace Blackfire\Player\Tests\Console;

use Blackfire\Player\Console\PlayerCommand;
use Blackfire\Player\Tests\VarDumper;
use Symfony\Component\Console\Input\ArrayInput;
use Blackfire\Player\Console\ScenarioHydrator;
use PHPUnit\Framework\TestCase;
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

        $stream = fopen('php://memory', 'r+');
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
  -scenarios: [
    Blackfire\Player\Scenario {
      -key: null
      -blockStep: Blackfire\Player\Step\VisitStep {
        -uri: "url('/pricing')"
        -method: null
        -parameters: []
        -body: null
        -expectations: []
        -variables: []
        -assertions: []
        -dumpValuesName: []
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
        #next: Blackfire\Player\Step\VisitStep {
          -uri: "url('/integrations')"
          -method: null
          -parameters: []
          -body: null
          -expectations: []
          -variables: []
          -assertions: []
          -dumpValuesName: []
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
          #next: null
          -name: ""
          -file: null
          -line: 12
          -errors: []
        }
        -name: ""
        -file: null
        -line: 11
        -errors: []
      }
      -variables: [
        "endpoint" => "'http://localhost'"
        "user_login" => "'escargot'"
        "user_password" => ""pwdsoupe""
      ]
      -endpoint: "'http://localhost'"
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
      #next: null
      -name: "'Visitor'"
      -file: null
      -line: 8
      -errors: []
    }
    Blackfire\Player\Scenario {
      -key: null
      -blockStep: Blackfire\Player\Step\VisitStep {
        -uri: "url('/login')"
        -method: null
        -parameters: []
        -body: null
        -expectations: []
        -variables: []
        -assertions: []
        -dumpValuesName: []
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
        #next: null
        -name: ""
        -file: null
        -line: 17
        -errors: []
      }
      -variables: [
        "endpoint" => "'http://localhost'"
        "user_login" => "'escargot'"
        "user_password" => ""pwdsoupe""
      ]
      -endpoint: ""http://tiptop.endpoint.minicontroll""
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
      #next: null
      -name: "'Authenticated'"
      -file: null
      -line: 14
      -errors: []
    }
  ]
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
}
EOEXPECTED;

        $this->assertSame($expected, $this->getVarDumperDump($scenarios));
    }

    private function getVarDumperDump($data)
    {
        $h = fopen('php://memory', 'r+b');
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
