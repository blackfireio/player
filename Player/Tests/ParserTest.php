<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Tests;

use Blackfire\Player\Exception\ExpressionSyntaxErrorException;
use Blackfire\Player\Exception\InvalidArgumentException;
use Blackfire\Player\Exception\LogicException;
use Blackfire\Player\Exception\SyntaxErrorException;
use Blackfire\Player\Parser;
use Blackfire\Player\Scenario;
use Blackfire\Player\ScenarioSet;
use Blackfire\Player\Step\ReloadStep;
use Blackfire\Player\Step\VisitStep;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{
    public function testParsingSeparatedScenario()
    {
        $parser = new Parser();
        $scenarioSet = $parser->parse(<<<'EOF'
scenario Test 1
    set env "prod"
    endpoint 'http://toto.com'

    # A comment
    visit url('/blog/')
        expect "prod" == env

scenario Test2
    reload
EOF
);
        $this->assertCount(2, $scenarioSet);

        /** @var Scenario $scenario */
        $scenario = $scenarioSet->getIterator()[0];
        $this->assertEquals('Test 1', $scenario->getKey());
        $this->assertInstanceOf(VisitStep::class, $scenario->getBlockStep());
        $this->assertEquals([
            'env' => '"prod"',
            'endpoint' => '\'http://toto.com\'',
        ], $scenario->getVariables());

        /** @var Scenario $scenario */
        $scenario = $scenarioSet->getIterator()[1];
        $this->assertEquals('Test2', $scenario->getKey());
        $this->assertInstanceOf(ReloadStep::class, $scenario->getBlockStep());
        $this->assertEquals(['endpoint' => ''], $scenario->getVariables());
    }

    public function testParsingGlobalConfiguration()
    {
        $parser = new Parser();
        $scenarioSet = $parser->parse(<<<'EOF'
set env "prod"
endpoint 'http://toto.com'

scenario Test 1
    # A comment
    visit url('/blog/')
        header "Accept-Language: en-US"
        samples 10
        expect "prod" == env

scenario Test2
    reload
EOF
        );
        $this->assertCount(2, $scenarioSet);

        $this->assertEquals([
            'env' => '"prod"',
            'endpoint' => '\'http://toto.com\'',
        ], $parser->getGlobalVariables());

        /** @var Scenario $scenario */
        $scenario = $scenarioSet->getIterator()[0];
        $this->assertEquals('Test 1', $scenario->getKey());
        $this->assertInstanceOf(VisitStep::class, $scenario->getBlockStep());
        $this->assertEquals(['endpoint' => '\'http://toto.com\''], $scenario->getVariables());
        $this->assertEquals([
            '"Accept-Language: en-US"',
        ], $scenario->getBlockStep()->getHeaders());
        $this->assertEquals(10, $scenario->getBlockStep()->getSamples());

        /** @var Scenario $scenario */
        $scenario = $scenarioSet->getIterator()[1];
        $this->assertEquals('Test2', $scenario->getKey());
        $this->assertInstanceOf(ReloadStep::class, $scenario->getBlockStep());
        $this->assertEquals(['endpoint' => '\'http://toto.com\''], $scenario->getVariables());
    }

    /**
     * @dataProvider warmupConfigProvider
     */
    public function testWarmupStepConfig($content, $expectedStep, $expectedScenario = null)
    {
        $parser = new Parser();
        $scenarioSet = $parser->parse($content);

        /** @var Scenario $scenario */
        $scenario = $scenarioSet->getIterator()[0];
        $this->assertEquals($expectedStep, $scenario->getBlockStep()->getWarmup());
        $this->assertEquals($expectedScenario, $scenario->getWarmup());
    }

    public function warmupConfigProvider()
    {
        yield [<<<'EOF'
scenario
    visit url('/blog/')
        warmup
EOF
            ,
            'true',
        ];

        yield [<<<'EOF'
scenario
    visit url('/blog/')
        warmup true
EOF
            ,
            'true',
        ];

        yield [<<<'EOF'
scenario
    visit url('/blog/')
        warmup false
EOF
            ,
            'false',
        ];

        yield [<<<'EOF'
scenario
    visit url('/blog/')
        warmup 8
EOF
            ,
            '8',
        ];

        yield [<<<'EOF'
scenario
    visit url('/blog/')
        warmup 1 + 4
EOF
            ,
            '1 + 4',
        ];

        yield [<<<'EOF'
scenario
    warmup true

    visit url('/blog/')
EOF
            ,
            null,
            'true',
        ];
    }

    /**
     * @dataProvider provideDocSamples
     */
    public function testDocSamples($input)
    {
        $parser = new Parser();
        $scenarioSet = $parser->parse($input);

        $this->assertInstanceOf(ScenarioSet::class, $scenarioSet);
    }

    public function provideDocSamples()
    {
        yield [<<<'EOF'
scenario
    name "Scenario Name"
    endpoint "http://example.com/"

    visit url('/')
        expect status_code() == 200
EOF
        ];

        yield [<<<'EOF'
scenario
    visit url('/')
        expect status_code() == 200

    visit url('/blog/')
        expect status_code() == 200
EOF
        ];

        yield [<<<'EOF'
scenario
    visit url('/')
        expect status_code() == 200

    click link('Read more')
        expect status_code() == 200
EOF
        ];

        yield [<<<'EOF'
# This is a comment
scenario
    # Comment are ignored
    visit url('/')
        expect status_code() == 200
EOF
        ];

        yield [<<<'EOF'
scenario
    visit url('/')
        method 'POST'
EOF
        ];

        yield [<<<'EOF'
scenario
    visit url('/')
        method 'PUT'
        body '{ "title": "New Title" }'
EOF
        ];

        yield [<<<'EOF'
scenario
    click link("Add a blog post")
EOF
        ];

        yield [<<<'EOF'
scenario
    submit button("Submit")
        param title 'Happy Scraping'
        param content 'Scraping with Blackfire Player is so easy!'

        # File Upload:
        # the path is relative to the current .bkf file
        # the name parameter is optional
        param image file('relative/path/to/image.png', 'blackfire.png')
EOF
        ];

        yield [<<<'EOF'
scenario
    submit button("Submit")
        param title fake('sentence', 5)
        param content join(fake('paragraphs', 3), "\n\n")
EOF
        ];

        yield [<<<'EOF'
scenario
    visit "redirect.php"
        expect status_code() == 302
        expect header('Location') == '/redirected.php'
EOF
        ];

        yield [<<<'EOF'
scenario
    visit "redirect.php"
        expect status_code() == 302
        expect header('Location') == '/redirected.php'

    follow
        expect status_code() == 200
EOF
        ];

        yield [<<<'EOF'
scenario
    follow_redirects true
EOF
        ];

        yield [<<<'EOF'
scenario
    visit "redirect.php"
        follow_redirects
EOF
        ];

        yield [<<<'EOF'
group login
    visit url('/login')
        expect status_code() == 200

    submit button('Login')
        param user 'admin'
        param password 'admin'
EOF
        ];

        // Adapted
        yield [<<<'EOF'
load "Player/Tests/fixtures-run/group/group.bkf"

scenario
    name "Scenario Name"

    include homepage

    visit url('/admin')
        expect status_code() == 200
EOF
        ];

        yield [<<<'EOF'
scenario
    visit url('/')
        header "Accept-Language: en-US"
EOF
        ];

        yield [<<<'EOF'
scenario
    visit url('/')
        header 'User-Agent: ' ~ fake('firefox')
EOF
        ];

        yield [<<<'EOF'
scenario
    visit url('/')
        auth "username:password"
EOF
        ];

        yield [<<<'EOF'
scenario
    visit url('/')
        wait 10000
EOF
        ];

        yield [<<<'EOF'
scenario
    visit url('/')
        wait fake('numberBetween', 1000, 3000)
EOF
        ];

        yield [<<<'EOF'
scenario
    visit url('/')
        method 'POST'
        param foo "bar"
        json true
EOF
        ];

        yield [<<<'EOF'
scenario
    auth "username:password"
    header "Accept-Language: en-US"
EOF
        ];

        yield [<<<'EOF'
scenario
    visit url('/')
        header "Accept-Language: false"
        auth false
EOF
        ];

        yield [<<<'EOF'
scenario
    name "Scenario Name"
     # Use the environment name (or UUID) you're targeting or false to disable
    blackfire "Environment name"
EOF
        ];

        yield [<<<'EOF'
scenario
    name "Scenario Name"
    # Use the environment name (or UUID) you're targeting or false to disable
    blackfire true
EOF
        ];

        yield [<<<'EOF'
scenario
    visit url('/blog/')
        name "Blog homepage"
        assert main.peak_memory < 10M
        samples 2
        warmup 'auto'
EOF
        ];

        yield [<<<'EOF'
scenario
    set env "prod"

    # no Twig template compilation in production
    # not enforced in other environments
    visit url('/blog/')
        assert "prod" == env and metrics.twig.compile.count == 0
        warmup true
EOF
        ];

        yield [<<<'EOF'
scenario
    visit url('/')
        expect status_code() == 200
        set latest_post_title css(".post h2").first()
        set latest_post_href css(".post h2 a").first().attr("href")
        set latest_posts css(".post h2 a").extract('_text', 'href')
        set age header("Age")
        set content_type header("Content-Type")
        set token regex('/name="_token" value="([^"]+)"/')
EOF
        ];

        // Adapted
        yield [<<<'EOF'
set api_username "user"
set api_password "password"

scenario
    name "Scenario name"
    auth api_username ~ ':' ~ api_password
    set profile_uuid 'zzzz'

    visit url('/profiles' ~ profile_uuid)
        expect status_code() == 200
        set sql_queries json('arguments."sql.pdo.queries".keys(@)')
        set store_url json("_links.store.href")

    visit url(store_url)
        method 'POST'
        body '{ "foo": "batman" }'
        expect status_code() == 200
EOF
        ];
    }

    /**
     * @dataProvider variableDeclarationProvider
     */
    public function testVariableCannotBeRedeclared($exceptionMessage, $scenario)
    {
        if ($exceptionMessage) {
            $this->expectException(LogicException::class);
            $this->expectExceptionMessage($exceptionMessage);
        }

        $parser = new Parser();
        $this->assertInstanceOf(ScenarioSet::class, $parser->parse($scenario));
    }

    public function variableDeclarationProvider()
    {
        // Valid

        yield [false, <<<'EOF'
set env "prod"

scenario Test
    set env "dev"
EOF
        ];

        yield [false, <<<'EOF'
set env "-"

scenario Test
    set env "prod"

    visit url('test')
        set env "dev"
EOF
        ];

        // Invalid

        yield ['You cannot redeclare the global variable "env" at line 2', <<<'EOF'
set env "prod"
set env "dev"
EOF
        ];

        yield ['You cannot redeclare the variable "env" at line 3', <<<'EOF'
scenario Test
    set env "prod"
    set env "dev"
EOF
        ];

        yield ['You cannot redeclare the variable "env" at line 4', <<<'EOF'
scenario Test
    visit url('/')
        set env "prod"
        set env "dev"
EOF
        ];
    }

    /**
     * @dataProvider stepConfigProvider
     */
    public function testStepConfig($exceptionMessage, $scenario)
    {
        if ($exceptionMessage) {
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage($exceptionMessage);
        }

        $parser = new Parser();
        $this->assertInstanceOf(ScenarioSet::class, $parser->parse($scenario));
    }

    public function stepConfigProvider()
    {
        // Valid

        yield [false, <<<'EOF'
scenario Test
    set env "dev"
    visit url('/')
EOF
        ];

        yield [false, <<<'EOF'
scenario Test
    name "Test"
    visit url('/')
EOF
        ];

        // Invalid

        yield ['A "set" can only be defined before steps at line 3', <<<'EOF'
scenario Test
    visit url('/')
    set env "dev"
EOF
        ];

        yield ['A "name" can only be defined at root at line 3.', <<<'EOF'
scenario Test
    visit url('/')
    name "Test"
EOF
        ];
    }

    public function testCannotLoadFileWithBadExtension()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('#Cannot load file ".*/Player/Tests/ParserTest.php" because it does not have the right extension. Expected "bkf", got "php".#');

        $parser = new Parser();
        $parser->load(__FILE__);
    }

    public function testLoadBlackfireBkfViaStdin()
    {
        $s = fopen(__DIR__.'/fixtures-run/simple/scenario.bkf', 'r');
        $copy = fopen('php://memory', 'r+b');
        stream_copy_to_stream($s, $copy);
        $parser = new Parser();
        $scenarios = $parser->load($copy);
        $this->assertCount(1, $scenarios->getIterator());
    }

    public function testLoadBlackfireBkfViaFile()
    {
        $parser = new Parser();
        $scenarios = $parser->load(__DIR__.'/fixtures-run/simple/scenario.bkf');
        $this->assertCount(1, $scenarios->getIterator());
    }

    public function testLoadBlackfireYamlViaFile()
    {
        $parser = new Parser();
        $scenarios = $parser->load(__DIR__.'/fixtures/yaml/.blackfire.yaml');
        $this->assertCount(1, $scenarios->getIterator());
    }

    public function testLineContinuation()
    {
        $parser = new Parser();
        $scenarioSet = $parser->parse(<<<'EOF'
set single 'a\
b\
c'
set multi '\
a\n\
b\n\
c\
'
scenario Test 1
    set single_indented 'x\
y\
z\
'
    visit url('/')
        body '{\
first: "premier",\n\
second: "deuxieme"\
}'
EOF
        );
        $this->assertEquals("'a b c'", $scenarioSet->getVariables()['single']);
        $this->assertEquals("' a\\n b\\n c '", $scenarioSet->getVariables()['multi']);
        $scenario = iterator_to_array($scenarioSet)[0];
        $this->assertEquals("'{ first: \"premier\",\\n second: \"deuxieme\" }'", $scenario->getBlockStep()->getBody());
    }

    public function testMultiLines()
    {
        $parser = new Parser();
        $scenarioSet = $parser->parse(<<<'EOF'
set multi
"""
a
b\r
'c'
"d"
 e
\tf
"""

scenario Test 1
    visit url('/')
        body
        """
        {
            first: "premier",
            second: "deuxieme"
        }
        """
EOF
        );

        $expected = '\'a\nb\r\n\\\'c\\\'\n"d"\n e\n\tf\'';
        $this->assertEquals($expected, $scenarioSet->getVariables()['multi']);

        $expected = '\'{\n    first: "premier",\n    second: "deuxieme"\n}\'';
        $scenario = iterator_to_array($scenarioSet)[0];
        $this->assertEquals($expected, $scenario->getBlockStep()->getBody());
    }

    public function testMultiLinesInterpolationNotEnabled()
    {
        $parser = new Parser();
        $scenarioSet = $parser->parse(<<<'EOF'
set multi
"""
${ variable }
${variable}
${ 1 + sum }
${ 2 ~ 2 }
${ (2 ~ 2) }
${ (2 ~ 2) }
${ test.property }
${ array["key"] }
"""
EOF
        );

        $expected = '\'${ variable }\n${variable}\n${ 1 + sum }\n${ 2 ~ 2 }\n${ (2 ~ 2) }\n${ (2 ~ 2) }\n${ test.property }\n${ array["key"] }\'';
        $this->assertEquals($expected, $scenarioSet->getVariables()['multi']);
    }

    public function testMultiLinesInterpolation()
    {
        $parser = new Parser();
        $scenarioSet = $parser->parse(<<<'EOF'
set multi
"""i
${ variable }
${variable}
${ someCamelCase }
${ some_snake_123 }
"""
EOF
        );

        $expected = '\'\' ~ variable ~ \'\n\' ~ variable ~ \'\n\' ~ someCamelCase ~ \'\n\' ~ some_snake_123 ~ \'\'';
        $this->assertEquals($expected, $scenarioSet->getVariables()['multi']);
    }

    public function testMultiLinesEscapingInterpolation()
    {
        $parser = new Parser();
        $scenarioSet = $parser->parse(<<<'EOF'
set multi
"""i
\${ variable }
"""
EOF
        );

        $expected = '\'\${ variable }\'';
        $this->assertEquals($expected, $scenarioSet->getVariables()['multi']);
    }

    public function testMultiLinesInvalidIndentation()
    {
        $this->expectException(SyntaxErrorException::class);
        $this->expectExceptionMessage('Incorrect indentation in multi-lines string at line 8.');

        $parser = new Parser();
        $parser->parse(<<<'EOF'
scenario Test 1
    visit url('/')
        body
        """
        {
            first: "premier",
            second: "deuxieme"
       }
        """
EOF
        );
    }

    public function testUndefinedVariableThrowsAnException()
    {
        $this->expectException(ExpressionSyntaxErrorException::class);
        $this->expectExceptionMessage(<<<'EOF'
Variable "env" is not valid around position 1 for expression `env != "prod"`.

Did you forget to declare it?
You can declare it in your file using the "set" option, or with the "--variable" CLI option.
If the Player is run through a Blackfire server, you can declare it in the "Variables" panel of the "Builds" tab.

EOF
);

        $parser = new Parser();
        $parser->parse(<<<'EOF'
scenario Test 1
    when env != "prod"
        visit url('/customers')
            expect status_code() == 200
EOF
        );
    }

    public function testDetectMissingVariables()
    {
        $parser = new Parser([], true);
        $parser->parse(<<<'EOF'
scenario Test 1
    when env != "prod"
        visit url('/customers')
            expect status_code() == 200
EOF
        );

        $this->assertEquals(['env'], $parser->getMissingVariables());
    }


    public function testMultiLinesWithoutInterpolatedVariables()
    {
        $parser = new Parser();
        $scenarioSet = $parser->parse(<<<'EOF'
set login admin
set password qwerty

scenario Test 1
    visit url('/')
        body
        """
        {
            login: "${login}",
            password: "${password}"
        }
        """
EOF
        );

        $expected = '\'{\n    login: "${login}",\n    password: "${password}"\n}\'';
        $scenario = iterator_to_array($scenarioSet)[0];
        $this->assertEquals($expected, $scenario->getBlockStep()->getBody());
        $this->assertEmpty($parser->getMissingVariables());
    }

    public function testMultiLinesWithInterpolatedVariables()
    {
        $parser = new Parser();
        $scenarioSet = $parser->parse(<<<'EOF'
set login admin
set password qwerty

scenario Test 1
    visit url('/')
        body
        """i
        {
            login: "${login}",
            password: "${ password }"
        }
        """
EOF
        );

        $expected = '\'{\n    login: "\' ~ login ~ \'",\n    password: "\' ~ password ~ \'"\n}\'';
        $scenario = iterator_to_array($scenarioSet)[0];
        $this->assertEquals($expected, $scenario->getBlockStep()->getBody());
        $this->assertEmpty($parser->getMissingVariables());
    }

    public function testMissingInterpolatedVariables()
    {
        $parser = new Parser([], true);
        $parser->parse(<<<'EOF'
scenario Test 1
    visit url('/')
        body
        """i
        {
            login: "${login}",
            password: "${password}"
        }
        """
EOF
        );

        $this->assertEquals(['login', 'password'], $parser->getMissingVariables());
    }

    public function testMissingNotinterpolatedVariables()
    {
        $parser = new Parser([], true);
        $parser->parse(<<<'EOF'
scenario Test 1
    visit url('/')
        body
        """
        {
            login: "${login}",
            password: "${password}"
        }
        """
EOF
        );
        $this->assertEquals([], $parser->getMissingVariables());
    }
}
