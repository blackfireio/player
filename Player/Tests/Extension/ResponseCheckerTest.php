<?php

declare(strict_types=1);

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Tests\Extension;

use Blackfire\Player\Exception\ExpectationFailureException;
use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Blackfire\Player\ExpressionLanguage\Provider;
use Blackfire\Player\Extension\ResponseChecker;
use Blackfire\Player\Http\CrawlerFactory;
use Blackfire\Player\Http\Request;
use Blackfire\Player\Http\Response;
use Blackfire\Player\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ResponseCheckerTest extends TestCase
{
    private ResponseChecker $responseChecker;

    protected function setUp(): void
    {
        $language = new ExpressionLanguage(null, [new Provider()]);
        $this->responseChecker = new ResponseChecker($language);
    }

    #[DataProvider('checkSuccessProvider')]
    public function testCheckSuccess(array $expectations, array $variables): void
    {
        $this->expectNotToPerformAssertions();

        $this->responseChecker->check($expectations, $variables);
    }

    public static function checkSuccessProvider(): \Generator
    {
        yield 'simple assertions' => [
            [
                '1 == 1',
            ],
            [],
        ];

        yield 'comparing two jsonresponse properties' => [
            [
                'json("foo.bar") == json("foo.zee")',
            ],
            [
                '_response' => new Response(
                    new Request('GET', 'https://app.dev.bkf/some.json'),
                    200, [],
                    Json::encode(['foo' => [
                        'bar' => 400,
                        'zee' => 400,
                    ]]),
                    []
                ),
            ],
        ];
    }

    #[DataProvider('checkFailuresProvider')]
    public function testCheckFailure(array $expectations, array $variables, array $expectedResolvedExpressions): void
    {
        try {
            $this->responseChecker->check($expectations, $variables);
        } catch (ExpectationFailureException $e) {
            $this->assertEquals($expectedResolvedExpressions, $e->getResults());

            return;
        }

        $this->fail('No exception were thrown, one expected');
    }

    public static function checkFailuresProvider(): \Generator
    {
        yield 'comparing two json response properties whose values are different' => [
            [
                'json("foo.bar") == json("foo.zee")',
            ],
            [
                '_response' => new Response(
                    new Request('GET', 'https://app.dev.bkf/some.json'),
                    200, [],
                    Json::encode(['foo' => [
                        'bar' => 400,
                        'zee' => 200,
                    ]]),
                    []
                ),
            ],
            [
                [
                    'expression' => 'json("foo.bar")',
                    'result' => 400,
                ],
                [
                    'expression' => 'json("foo.zee")',
                    'result' => 200,
                ],
            ],
        ];

        $response = new Response(
            new Request('GET', 'https://app.dev.bkf/some.json'),
            200,
            [
                'content-type' => ['text/html'],
            ],
            '<!DOCTYPE html><html><head><title>Page title</title></head><body><h1>List</h1><ul class="items"><li class="item">Item 1</li><li class="item">Item 2</li></ul></body></html>',
            []
        );
        yield 'searching using a css property which is not in the response' => [
            [
                'css(".items") and css(".items .item:nth-child(3)").text() matches "/Newer posts/"',
            ],
            [
                '_crawler' => CrawlerFactory::create($response, $response->request->uri),
                '_response' => $response,
            ],
            [
                [
                    'expression' => 'css(".items") and css(".items .item:nth-child(3)").text() matches "/Newer posts/"',
                    'result' => 'The current node list is empty.',
                ],
            ],
        ];
    }
}
