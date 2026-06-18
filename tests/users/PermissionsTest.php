<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

require_once __DIR__ . '/../../src/modules/users/models.php';

final class PermissionsTest extends DatabaseTestCase
{
    #[Test]
    #[DataProvider('manageUserProvider')]
    public function can_manage_user_respects_role_boundaries(string $actingRole, string $targetRole, bool $expected): void
    {
        $acting = ['role' => $actingRole];
        $target = ['role' => $targetRole];

        $this->assertSame($expected, can_manage_user($acting, $target));
    }

    public static function manageUserProvider(): array
    {
        return [
            ['super_admin', 'super_admin', true],
            ['admin', 'reviewer', true],
            ['admin', 'admin', false],
            ['reviewer', 'user', false],
        ];
    }

    #[Test]
    #[DataProvider('assignRoleProvider')]
    public function can_assign_role_limits_assignable_roles(string $actingRole, string $targetRole, bool $expected): void
    {
        $this->assertSame($expected, can_assign_role(['role' => $actingRole], $targetRole));
    }

    public static function assignRoleProvider(): array
    {
        return [
            ['super_admin', 'admin', true],
            ['super_admin', 'super_admin', false],
            ['admin', 'reviewer', true],
            ['admin', 'admin', false],
            ['user', 'reviewer', false],
        ];
    }

    #[Test]
    public function get_assignable_roles_returns_expected_labels_for_admin_and_super_admin(): void
    {
        $this->assertSame(['reviewer' => 'Reviewer', 'user' => 'User'], get_assignable_roles(['role' => 'admin']));
        $this->assertSame(['admin' => 'Admin', 'reviewer' => 'Reviewer', 'user' => 'User'], get_assignable_roles(['role' => 'super_admin']));
        $this->assertSame([], get_assignable_roles(['role' => 'reviewer']));
    }

    #[Test]
    public function get_visible_roles_hides_higher_privilege_accounts(): void
    {
        $this->assertSame(['super_admin', 'admin', 'reviewer', 'user'], get_visible_roles(['role' => 'super_admin']));
        $this->assertSame(['admin', 'reviewer', 'user'], get_visible_roles(['role' => 'admin']));
        $this->assertSame(['reviewer', 'user'], get_visible_roles(['role' => 'reviewer']));
        $this->assertSame(['user'], get_visible_roles(['role' => 'user']));
    }
}