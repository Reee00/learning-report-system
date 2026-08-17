<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\AuthorizationService;
use PHPUnit\Framework\TestCase;

class AuthorizationServiceTest extends TestCase
{
    public function test_superadmin_has_wildcard_access(): void
    {
        $service = new AuthorizationService();
        $user = new User(['role' => User::ROLE_SUPERADMIN]);

        $this->assertTrue($service->allows($user, 'users.manage'));
        $this->assertTrue($service->allows($user, 'attendance.export_csv'));
    }

    public function test_relation_has_operational_permissions_with_report_review(): void
    {
        $service = new AuthorizationService();
        $user = new User(['role' => User::ROLE_RELATION]);

        $this->assertTrue($service->allows($user, 'schools.create'));
        $this->assertTrue($service->allows($user, 'schools.update'));
        $this->assertTrue($service->allows($user, 'schools.delete'));
        $this->assertTrue($service->allows($user, 'attendance.export'));
        $this->assertTrue($service->allows($user, 'reports.view_all'));
        $this->assertTrue($service->allows($user, 'reports.review'));
        $this->assertFalse($service->allows($user, 'users.manage'));
        $this->assertFalse($service->allows($user, 'coaches.view'));
    }

    public function test_spv_coach_can_view_reports_but_not_review(): void
    {
        $service = new AuthorizationService();
        $user = new User(['role' => User::ROLE_SPV_COACH]);

        $this->assertTrue($service->allows($user, 'reports.view_all'));
        $this->assertFalse($service->allows($user, 'reports.review'));
    }

    public function test_coach_has_own_report_permission_but_not_global_console(): void
    {
        $service = new AuthorizationService();
        $user = new User(['role' => User::ROLE_COACH]);

        $this->assertTrue($service->allows($user, 'reports.view'));
        $this->assertFalse($service->allows($user, 'reports.view_all'));
        $this->assertFalse($service->allows($user, 'reports.review'));
    }

    public function test_finance_can_export_csv_but_cannot_manage_master_data(): void
    {
        $service = new AuthorizationService();
        $user = new User(['role' => User::ROLE_FINANCE]);

        $this->assertTrue($service->allows($user, 'attendance.view'));
        $this->assertTrue($service->allows($user, 'attendance.export_csv'));
        $this->assertFalse($service->allows($user, 'schools.create'));
    }
}
