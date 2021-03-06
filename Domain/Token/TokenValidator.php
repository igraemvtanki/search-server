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

namespace Apisearch\Server\Domain\Token;

use Apisearch\Exception\InvalidTokenException;
use Apisearch\Token\Token;
use Carbon\Carbon;

/**
 * Class TokenValidator.
 */
class TokenValidator
{
    /**
     * @var TokenLocator[]
     *
     * Token locators
     */
    private $tokenLocators = [];

    /**
     * Add token locator.
     *
     * @param TokenLocator $tokenLocator
     */
    public function addTokenLocator(TokenLocator $tokenLocator)
    {
        $this->tokenLocators[] = $tokenLocator;
    }

    /**
     * Validate token given basic fields.
     *
     * If is valid, return valid Token
     *
     * @param string $appId
     * @param string $indexId
     * @param string $tokenReference
     * @param string $referrer
     * @param string $path
     * @param string $verb
     *
     * @return Token $token
     */
    public function validateToken(
        string $appId,
        string $indexId,
        string $tokenReference,
        string $referrer,
        string $path,
        string $verb
    ): Token {
        $token = null;
        foreach ($this->tokenLocators as $tokenLocator) {
            if (!$tokenLocator->isValid()) {
                continue;
            }

            $token = $tokenLocator->getTokenByReference(
                $appId,
                $tokenReference
            );

            if ($token instanceof Token) {
                break;
            }
        }

        $endpoint = strtolower($verb.'~~'.trim($path, '/'));

        if (
            (!$token instanceof Token) ||
            (
                $appId !== $token->getAppId()
            ) ||
            (
                !empty($token->getHttpReferrers()) &&
                !in_array($referrer, $token->getHttpReferrers())
            ) ||
            (
                !empty($indexId) &&
                !empty($token->getIndices()) &&
                !in_array($indexId, $token->getIndices())
            ) ||
            (
                !empty($token->getEndpoints()) &&
                !in_array($endpoint, $token->getEndpoints())
            ) ||
            (
                $token->getSecondsValid() > 0 &&
                $token->getUpdatedAt() + $token->getSecondsValid() < Carbon::now('UTC')->timestamp
            )
        ) {
            throw InvalidTokenException::createInvalidTokenPermissions($tokenReference);
        }

        return $token;
    }
}
