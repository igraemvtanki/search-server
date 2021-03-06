<?php

/*
 * This file is part of the Apisearch Server
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Feel free to edit as you please, and have fun.
 *
 * @author Marc Morera <yuhu@mmoreram.com>
 */

declare(strict_types=1);

namespace Apisearch\Plugin\Callbacks\Tests\Functional\Middleware\Query;

use Apisearch\Plugin\Callbacks\Tests\Functional\EndpointsFunctionalTest;
use Apisearch\Query\Query;
use Apisearch\Token\Token;
use Apisearch\Token\TokenUUID;

/**
 * Class CallbacksMiddlewareAddTokenTest.
 */
class CallbacksMiddlewareAddTokenTest extends EndpointsFunctionalTest
{
    /**
     * Get callbacks configuration.
     *
     * @return array
     */
    protected static function getCallbacksConfiguration(): array
    {
        return [
            'http_client_adapter' => 'http_test',
            'callbacks' => [
                'my_query_callback_before' => [
                    'command' => 'AddToken',
                    'endpoint' => '/plugin/endpoints/empty_endpoint?'.static::getUrlQuery(),
                    'method' => 'GET',
                    'moment' => 'before',
                ],
                'my_query_callback_after' => [
                    'command' => 'AddToken',
                    'endpoint' => '/plugin/endpoints/empty_endpoint?'.static::getUrlQuery(),
                    'method' => 'GET',
                    'moment' => 'after',
                ],
            ],
        ];
    }

    /**
     * Test something.
     */
    public function testSomething()
    {
        $this->addToken(new Token(TokenUUID::createById('lalaland'), self::$appId));
        $this->assertCount(1, $this->getRegister());
        $this->query(Query::createMatchAll(), self::$appId, self::$index, new Token(TokenUUID::createById('lalaland'), self::$appId));
    }
}
