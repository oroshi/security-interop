<?php declare(strict_types=1);
/**
 * This file is part of the daikon-cqrs/security-interop project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Daikon\Security\Middleware;

use Daikon\Boot\Middleware\Action\ActionInterface;
use Daikon\Boot\Middleware\Action\DaikonRequest;
use Daikon\Boot\Middleware\ActionHandler;
use Daikon\Interop\Assertion;
use Daikon\Interop\AssertionFailedException;
use Daikon\Interop\DaikonException;
use Daikon\Interop\RuntimeException;
use Daikon\Security\Exception\AuthenticationException;
use Daikon\Security\Exception\AuthorizationException;
use Daikon\Security\Middleware\Action\SecureActionInterface;
use Daikon\Validize\Validation\ValidatorDefinition;
use Daikon\Validize\ValueObject\Severity;
use Middlewares\Utils\Factory;
use Psr\Http\Message\ResponseInterface;

final class SecureActionHandler extends ActionHandler
{
    protected function execute(ActionInterface $action, DaikonRequest $request): ResponseInterface
    {
        try {
            // Check action access first before running validation
            if ($action instanceof SecureActionInterface) {
                if (!$action->isAuthorized($request)) {
                    return Factory::createResponse(self::STATUS_FORBIDDEN);
                }
            }

            if ($validator = $action->getValidator($request)) {
                $validatorDefinition = (new ValidatorDefinition('$', Severity::critical()))->withArgument($request);
                $request = $request->withPayload($validator($validatorDefinition));
                Assertion::noContent($request->getErrors());
            }

            // Run secondary resource authorization after validation
            if ($action instanceof SecureActionInterface) {
                if (!$action->isAuthorized($request)) {
                    return Factory::createResponse(self::STATUS_FORBIDDEN);
                }
            }

            $request = $action($request);
        } catch (DaikonException $error) {
            switch (true) {
                case $error instanceof AssertionFailedException:
                    $statusCode = self::STATUS_UNPROCESSABLE_ENTITY;
                    break;
                case $error instanceof AuthenticationException:
                    $statusCode = self::STATUS_UNAUTHORIZED;
                    break;
                case $error instanceof AuthorizationException:
                    $statusCode = self::STATUS_FORBIDDEN;
                    break;
                default:
                    $this->logger->error($error->getMessage(), ['exception' => $error->getTrace()]);
                    $statusCode = self::STATUS_INTERNAL_SERVER_ERROR;
            }
            $request = $action->handleError(
                $request
                    ->withStatusCode($request->getStatusCode($statusCode))
                    ->withErrors($request->getErrors($error))
            );
        }

        if (!$responder = $this->resolveResponder($request)) {
            throw $error ?? new RuntimeException(
                sprintf("Unable to determine responder for '%s'.", get_class($action))
            );
        }

        return $responder->handle($request);
    }
}
