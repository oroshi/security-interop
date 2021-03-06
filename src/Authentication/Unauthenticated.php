<?php declare(strict_types=1);
/**
 * This file is part of the daikon-cqrs/security-interop project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Daikon\Security\Authentication;

use Laminas\Permissions\Acl\Role\RoleInterface;

class Unauthenticated implements AuthenticatorInterface, RoleInterface
{
    public const UNAUTHENTICATED = 'unauthenticated';

    public function getRoleId(): string
    {
        return self::UNAUTHENTICATED;
    }
}
