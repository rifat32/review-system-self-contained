<?php

namespace App\Traits;

use App\Models\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Trait AuthorizesRoles
 * 
 * Provides reusable role authorization methods for controllers
 */
trait AuthorizesRoles
{
    /**
     * Ensure authenticated user is a super admin
     * 
     * @throws AccessDeniedHttpException
     */
    protected function ensureSuperAdmin(): void
    {
        $this->ensureRole(User::USER_ROLE['SUPER_ADMIN']);
    }

    /**
     * Ensure authenticated user is a business owner
     * 
     * @throws AccessDeniedHttpException
     */
    protected function ensureBusinessOwner(): void
    {
        $this->ensureRole(User::USER_ROLE['BUSINESS_OWNER']);
    }

    /**
     * Ensure authenticated user is a branch manager
     * 
     * @throws AccessDeniedHttpException
     */
    protected function ensureBranchManager(): void
    {
        $this->ensureRole(User::USER_ROLE['BRANCH_MANAGER']);
    }

    /**
     * Ensure authenticated user is a staff member
     * 
     * @throws AccessDeniedHttpException
     */
    protected function ensureStaff(): void
    {
        $this->ensureRole(User::USER_ROLE['STAFF']);
    }

    /**
     * Ensure authenticated user has a specific role
     * 
     * @param string $role Role name to check
     * @throws AccessDeniedHttpException
     */
    protected function ensureRole(string $role): void
    {
        if (!auth()->user()->hasRole($role)) {
            throw new AccessDeniedHttpException("Access denied: You cannot perform this action");
        }
    }

    /**
     * Ensure authenticated user has at least one of the specified roles
     * 
     * @param array $roles Array of role names
     * @throws AccessDeniedHttpException
     */
    protected function ensureAnyRole(array $roles): void
    {
        if (!auth()->user()->hasAnyRole($roles)) {
            throw new AccessDeniedHttpException('Access denied: Insufficient permissions');
        }
    }

    /**
     * Ensure authenticated user has all of the specified roles
     * 
     * @param array $roles Array of role names
     * @throws AccessDeniedHttpException
     */
    protected function ensureAllRoles(array $roles): void
    {
        if (!auth()->user()->hasAllRoles($roles)) {
            throw new AccessDeniedHttpException('Access denied: Insufficient permissions');
        }
    }
}
