<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Tests\Console;

use Blackfire\Player\Console\PlayerCommand;
use Blackfire\Player\Console\ScenarioHydrator;
use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Blackfire\Player\ExpressionLanguage\Provider as LanguageProvider;
use Blackfire\Player\ParserFactory;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Tests\VarDumper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\VarDumper\Cloner\Stub;
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
        ], (new PlayerCommand(null, null, 'a396ccc8-51e1-4047-93aa-ca3f3847f425'))->getDefinition());

        $hydrator = new ScenarioHydrator(new ParserFactory(new ExpressionLanguage(null, [new LanguageProvider()])));
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
  -version: 0
  -status: Blackfire\Player\Enum\BuildStatus {
    +name: "IN_PROGRESS"
    +value: "in_progress"
  }
  -endpoint: "'http://localhost'"
  -blackfireEnvironment: null
  -scenarios: [
    Blackfire\Player\Scenario {
      #next: null
      #blackfireProfileUuid: null
      #status: Blackfire\Player\Enum\BuildStatus {#1
        +name: "TODO"
        +value: "todo"
      }
      -name: "'Visitor'"
      -failingExpectations: []
      -failingAssertions: []
      -errors: []
      -deprecations: []
      -generatedSteps: []
      -uuid: "aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"
      -startedAt: null
      -finishedAt: null
      -file: null
      -line: 8
      -auth: null
      -headers: []
      -wait: null
      -json: null
      -followRedirects: null
      -blackfire: null
      -samples: null
      -warmup: null
      -blockStep: Blackfire\Player\Step\VisitStep {
        #next: Blackfire\Player\Step\VisitStep {
          #next: null
          #blackfireProfileUuid: null
          #status: Blackfire\Player\Enum\BuildStatus {#1}
          -name: null
          -failingExpectations: []
          -failingAssertions: []
          -errors: []
          -deprecations: []
          -generatedSteps: []
          -uuid: "aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"
          -startedAt: null
          -finishedAt: null
          -file: null
          -line: 12
          -auth: null
          -headers: []
          -wait: null
          -json: null
          -followRedirects: null
          -blackfire: null
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
        #status: Blackfire\Player\Enum\BuildStatus {#1}
        -name: null
        -failingExpectations: []
        -failingAssertions: []
        -errors: []
        -deprecations: []
        -generatedSteps: []
        -uuid: "aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"
        -startedAt: null
        -finishedAt: null
        -file: null
        -line: 11
        -auth: null
        -headers: []
        -wait: null
        -json: null
        -followRedirects: null
        -blackfire: null
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
      #blackfireBuildUuid: null
    }
    Blackfire\Player\Scenario {
      #next: null
      #blackfireProfileUuid: null
      #status: Blackfire\Player\Enum\BuildStatus {#1}
      -name: "'Authenticated'"
      -failingExpectations: []
      -failingAssertions: []
      -errors: []
      -deprecations: []
      -generatedSteps: []
      -uuid: "aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"
      -startedAt: null
      -finishedAt: null
      -file: null
      -line: 14
      -auth: null
      -headers: []
      -wait: null
      -json: null
      -followRedirects: null
      -blackfire: null
      -samples: null
      -warmup: null
      -blockStep: Blackfire\Player\Step\VisitStep {
        #next: null
        #blackfireProfileUuid: null
        #status: Blackfire\Player\Enum\BuildStatus {#1}
        -name: null
        -failingExpectations: []
        -failingAssertions: []
        -errors: []
        -deprecations: []
        -generatedSteps: []
        -uuid: "aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"
        -startedAt: null
        -finishedAt: null
        -file: null
        -line: 17
        -auth: null
        -headers: []
        -wait: null
        -json: null
        -followRedirects: null
        -blackfire: null
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
      #blackfireBuildUuid: null
    }
  ]
}
EOEXPECTED;

        $this->assertSame($expected, $this->getVarDumperDump($scenarios));
    }

    private function getVarDumperDump($data)
    {
        $casters = [
            AbstractStep::class => self::uuidCaster(...),
        ];

        $h = fopen('php://memory', 'r+');
        $cloner = new VarCloner($casters);
        $cloner->setMaxItems(-1);
        $dumper = new VarDumper($h);
        $dumper->setColors(false);
        $dumper->dump($cloner->cloneVar($data)->withRefHandles(false));
        $data = stream_get_contents($h, -1, 0);
        fclose($h);

        return rtrim($data);
    }

    private static function uuidCaster(AbstractStep $object, array $array, Stub $stub, bool $isNested, int $filter = 0)
    {
        $array["\x00Blackfire\Player\Step\AbstractStep\x00uuid"] = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';

        return $array;
    }
}
