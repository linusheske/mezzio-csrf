<?php

declare(strict_types=1);

namespace MezzioTest\Csrf;

use Mezzio\Csrf\CsrfGuardFactoryInterface;
use Mezzio\Csrf\CsrfGuardInterface;
use Mezzio\Csrf\CsrfMiddleware;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CsrfMiddlewareTest extends TestCase
{
    public function setUp()
    {
        $this->guardFactory = $this->prophesize(CsrfGuardFactoryInterface::class);
    }

    public function testConstructorUsesSaneAttributeKeyByDefault()
    {
        $middleware = new CsrfMiddleware($this->guardFactory->reveal());
        $this->assertAttributeSame($this->guardFactory->reveal(), 'guardFactory', $middleware);
        $this->assertAttributeSame($middleware::GUARD_ATTRIBUTE, 'attributeKey', $middleware);
    }

    public function testConstructorAllowsProvidingAlternateAttributeKey()
    {
        $middleware = new CsrfMiddleware($this->guardFactory->reveal(), 'alternate-key');
        $this->assertAttributeSame($this->guardFactory->reveal(), 'guardFactory', $middleware);
        $this->assertAttributeSame('alternate-key', 'attributeKey', $middleware);
    }

    public function attributeKeyProvider()
    {
        return [
            'null-default' => [null],
            'custom'       => ['alternate-key'],
        ];
    }

    /**
     * @dataProvider attributeKeyProvider
     */
    public function testProcessDelegatesNewRequestContainingGeneratedGuardInstance($attributeKey)
    {
        $guard = $this->prophesize(CsrfGuardInterface::class)->reveal();
        $request = $this->prophesize(ServerRequestInterface::class);
        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $middleware = $attributeKey
            ? new CsrfMiddleware($this->guardFactory->reveal(), $attributeKey)
            : new CsrfMiddleware($this->guardFactory->reveal());

        $attributeKey = $attributeKey ?: CsrfMiddleware::GUARD_ATTRIBUTE;

        $this->guardFactory->createGuardFromRequest($request->reveal())->willReturn($guard);
        $request->withAttribute($attributeKey, $guard)->will([$request, 'reveal']);

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle(Argument::that([$request, 'reveal']))->willReturn($response);

        $this->assertSame($response, $middleware->process($request->reveal(), $handler->reveal()));
    }
}
