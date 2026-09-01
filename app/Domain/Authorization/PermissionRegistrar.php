<?php

namespace App\Domain\Authorization;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class PermissionRegistrar
{
    private const CACHE_KEY = 'rbac.map';

    /**
     * Full permission map: name => group.
     *
     * @return array<string, string>
     */
    public static function catalog(): array
    {
        return [
            // Customer
            'customer.order.create' => 'Customer',
            'customer.order.cancel' => 'Customer',
            'customer.order.confirm' => 'Customer',
            'customer.order.review' => 'Customer',
            'customer.order.dispute' => 'Customer',
            'customer.address.manage' => 'Customer',
            'customer.project.manage' => 'Customer',
            'customer.proposal.select' => 'Customer',
            'customer.milestone.approve' => 'Customer',
            'customer.quotation.approve' => 'Customer',
            'customer.warranty.claim' => 'Customer',

            // Partner
            'partner.profile.manage' => 'Partner',
            'partner.service.manage' => 'Partner',
            'partner.order.accept' => 'Partner',
            'partner.order.reject' => 'Partner',
            'partner.order.progress' => 'Partner',
            'partner.order.complete' => 'Partner',
            'partner.availability.manage' => 'Partner',
            'partner.withdrawal.request' => 'Partner',
            'partner.review.respond' => 'Partner',
            'partner.warranty.handle' => 'Partner',

            // Vendor org
            'vendor.member.manage' => 'Vendor',
            'vendor.role.assign' => 'Vendor',
            'vendor.assignment.manage' => 'Vendor',
            'vendor.finance.view' => 'Vendor',
            'vendor.proposal.submit' => 'Vendor',
            'vendor.quotation.submit' => 'Vendor',
            'vendor.org.settings' => 'Vendor',

            // Operations
            'ops.order.assign' => 'Operations',
            'ops.order.reassign' => 'Operations',
            'ops.order.view_all' => 'Operations',
            'ops.incident.flag' => 'Operations',
            'ops.sla.manage' => 'Operations',

            // Finance
            'finance.refund.approve' => 'Finance',
            'finance.refund.execute' => 'Finance',
            'finance.settlement.execute' => 'Finance',
            'finance.withdrawal.approve' => 'Finance',
            'finance.withdrawal.process' => 'Finance',
            'finance.reconciliation.manage' => 'Finance',
            'finance.ledger.view' => 'Finance',
            'finance.commission.manage' => 'Finance',
            'finance.manual_transfer.confirm' => 'Finance',

            // KYC
            'kyc.review' => 'KYC',
            'kyc.approve' => 'KYC',
            'kyc.reject' => 'KYC',

            // Trust & safety
            'ts.report.handle' => 'TrustSafety',
            'ts.risk.review' => 'TrustSafety',
            'ts.user.suspend' => 'TrustSafety',

            // Disputes
            'dispute.manage' => 'Dispute',
            'dispute.resolve' => 'Dispute',

            // Marketing
            'marketing.voucher.manage' => 'Marketing',
            'marketing.promotion.manage' => 'Marketing',
            'marketing.referral.manage' => 'Marketing',
            'marketing.membership.manage' => 'Marketing',

            // Content
            'content.cms.manage' => 'Content',
            'content.blog.manage' => 'Content',
            'content.seo.manage' => 'Content',

            // Support
            'support.ticket.handle' => 'Support',
            'support.customer360.view' => 'Support',
            'support.vendor360.view' => 'Support',

            // Corporate
            'corporate.request.approve' => 'Corporate',
            'corporate.request.finance_approve' => 'Corporate',
            'corporate.org.manage' => 'Corporate',

            // Admin
            'admin.category.manage' => 'Admin',
            'admin.partner.verify' => 'Admin',
            'admin.settings.manage' => 'Admin',
            'admin.user.manage' => 'Admin',
            'admin.role.manage' => 'Admin',
            'audit.view' => 'Admin',
            'reports.view' => 'Admin',
        ];
    }

    public function catalogGroups(): array
    {
        $map = static::catalog();
        $groups = [];
        foreach ($map as $name => $group) {
            $groups[$group][] = $name;
        }

        return $groups;
    }

    /** @return array<string, array<int, string>> role => permissions */
    public function roleAssignments(): array
    {
        $catalog = static::catalog();

        $staff = [
            'SuperAdmin' => array_keys($catalog),
            'PlatformAdmin' => array_keys($catalog),
            'OpsManager' => array_keys(array_filter($catalog, fn ($g) => in_array($g, ['Operations', 'Support', 'Admin']))),
            'Dispatcher' => ['ops.order.view_all', 'ops.order.assign', 'ops.order.reassign', 'ops.incident.flag'],
            'FinanceManager' => array_keys(array_filter($catalog, fn ($g) => in_array($g, ['Finance', 'Admin']))),
            'FinanceStaff' => ['finance.ledger.view', 'finance.settlement.execute', 'finance.withdrawal.process', 'finance.manual_transfer.confirm', 'reports.view'],
            'CustomerSupport' => ['support.ticket.handle', 'support.customer360.view', 'support.vendor360.view', 'ops.order.view_all'],
            'KycOfficer' => ['kyc.review', 'kyc.approve', 'kyc.reject', 'support.vendor360.view'],
            'TrustSafetyOfficer' => ['ts.report.handle', 'ts.risk.review', 'ts.user.suspend', 'support.customer360.view', 'support.vendor360.view'],
            'DisputeOfficer' => ['dispute.manage', 'dispute.resolve', 'support.customer360.view', 'support.vendor360.view', 'finance.ledger.view'],
            'MarketingManager' => array_keys(array_filter($catalog, fn ($g) => in_array($g, ['Marketing', 'Content']))),
            'ContentManager' => array_keys(array_filter($catalog, fn ($g) => in_array($g, ['Content']))),
            'Auditor' => ['audit.view', 'finance.ledger.view', 'reports.view'],
            'ManagementViewer' => ['reports.view', 'finance.ledger.view'],
        ];

        return [
            'Customer' => [
                'customer.order.create', 'customer.order.cancel', 'customer.order.confirm',
                'customer.order.review', 'customer.order.dispute', 'customer.address.manage',
                'customer.project.manage', 'customer.proposal.select', 'customer.milestone.approve',
                'customer.quotation.approve', 'customer.warranty.claim',
            ],
            'Partner' => [
                'partner.profile.manage', 'partner.service.manage', 'partner.order.accept',
                'partner.order.reject', 'partner.order.progress', 'partner.order.complete',
                'partner.availability.manage', 'partner.withdrawal.request', 'partner.review.respond',
                'partner.warranty.handle', 'vendor.proposal.submit', 'vendor.quotation.submit',
            ],
            'VendorOwner' => array_merge($this->roleAssignmentsVendorBase(), ['vendor.member.manage', 'vendor.role.assign', 'vendor.org.settings']),
            'VendorManager' => array_merge($this->roleAssignmentsVendorBase(), ['vendor.member.manage', 'vendor.assignment.manage']),
            'VendorDispatcher' => ['partner.order.accept', 'partner.order.reject', 'vendor.assignment.manage', 'partner.order.progress'],
            'VendorFinance' => ['vendor.finance.view', 'partner.withdrawal.request'],
            'VendorPM' => ['vendor.proposal.submit', 'vendor.quotation.submit', 'vendor.assignment.manage'],
            'VendorWorker' => ['partner.order.progress', 'partner.order.complete'],
            ...$staff,
        ];
    }

    private function roleAssignmentsVendorBase(): array
    {
        return [
            'partner.profile.manage', 'partner.service.manage', 'partner.order.accept',
            'partner.order.reject', 'partner.order.progress', 'partner.order.complete',
            'partner.availability.manage', 'partner.review.respond', 'partner.warranty.handle',
            'vendor.assignment.manage', 'vendor.finance.view', 'vendor.proposal.submit', 'vendor.quotation.submit',
        ];
    }

    public function roles(): array
    {
        return [
            ['name' => 'Customer', 'label' => 'Customer', 'group' => 'customer', 'is_staff' => false, 'requires_two_factor' => false],
            ['name' => 'Partner', 'label' => 'Partner (Individual/Freelancer)', 'group' => 'partner', 'is_staff' => false, 'requires_two_factor' => false],
            ['name' => 'VendorOwner', 'label' => 'Vendor Owner', 'group' => 'partner', 'is_staff' => false, 'requires_two_factor' => false],
            ['name' => 'VendorManager', 'label' => 'Vendor Manager', 'group' => 'partner', 'is_staff' => false, 'requires_two_factor' => false],
            ['name' => 'VendorDispatcher', 'label' => 'Vendor Dispatcher', 'group' => 'partner', 'is_staff' => false, 'requires_two_factor' => false],
            ['name' => 'VendorFinance', 'label' => 'Vendor Finance', 'group' => 'partner', 'is_staff' => false, 'requires_two_factor' => false],
            ['name' => 'VendorPM', 'label' => 'Vendor Project Manager', 'group' => 'partner', 'is_staff' => false, 'requires_two_factor' => false],
            ['name' => 'VendorWorker', 'label' => 'Vendor Worker', 'group' => 'partner', 'is_staff' => false, 'requires_two_factor' => false],
            ['name' => 'SuperAdmin', 'label' => 'Super Admin', 'group' => 'internal', 'is_staff' => true, 'requires_two_factor' => true],
            ['name' => 'PlatformAdmin', 'label' => 'Platform Admin', 'group' => 'internal', 'is_staff' => true, 'requires_two_factor' => true],
            ['name' => 'OpsManager', 'label' => 'Operations Manager', 'group' => 'internal', 'is_staff' => true, 'requires_two_factor' => true],
            ['name' => 'Dispatcher', 'label' => 'Dispatcher', 'group' => 'internal', 'is_staff' => true, 'requires_two_factor' => false],
            ['name' => 'FinanceManager', 'label' => 'Finance Manager', 'group' => 'internal', 'is_staff' => true, 'requires_two_factor' => true],
            ['name' => 'FinanceStaff', 'label' => 'Finance Staff', 'group' => 'internal', 'is_staff' => true, 'requires_two_factor' => false],
            ['name' => 'CustomerSupport', 'label' => 'Customer Support', 'group' => 'internal', 'is_staff' => true, 'requires_two_factor' => false],
            ['name' => 'KycOfficer', 'label' => 'KYC Officer', 'group' => 'internal', 'is_staff' => true, 'requires_two_factor' => true],
            ['name' => 'TrustSafetyOfficer', 'label' => 'Trust & Safety Officer', 'group' => 'internal', 'is_staff' => true, 'requires_two_factor' => true],
            ['name' => 'DisputeOfficer', 'label' => 'Dispute Officer', 'group' => 'internal', 'is_staff' => true, 'requires_two_factor' => true],
            ['name' => 'MarketingManager', 'label' => 'Marketing Manager', 'group' => 'internal', 'is_staff' => true, 'requires_two_factor' => false],
            ['name' => 'ContentManager', 'label' => 'Content Manager', 'group' => 'internal', 'is_staff' => true, 'requires_two_factor' => false],
            ['name' => 'Auditor', 'label' => 'Auditor', 'group' => 'internal', 'is_staff' => true, 'requires_two_factor' => true],
            ['name' => 'ManagementViewer', 'label' => 'Management Viewer', 'group' => 'internal', 'is_staff' => true, 'requires_two_factor' => false],
            ['name' => 'CorporateApprover', 'label' => 'Corporate Approver', 'group' => 'corporate', 'is_staff' => false, 'requires_two_factor' => false],
            ['name' => 'CorporateFinanceApprover', 'label' => 'Corporate Finance Approver', 'group' => 'corporate', 'is_staff' => false, 'requires_two_factor' => false],
        ];
    }

    /** Cached map: role_name => [permission names]. */
    public function roleMap(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $rows = \DB::table('role_permission')
                ->join('roles', 'roles.id', '=', 'role_permission.role_id')
                ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
                ->selectRaw('roles.name as role, permissions.name as permission')
                ->get();

            $map = [];
            foreach ($rows as $row) {
                $map[$row->role][] = $row->permission;
            }

            return $map;
        });
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function userHasPermission(User $user, string $permission): bool
    {
        $map = $this->roleMap();

        foreach ($user->roles()->pluck('roles.name') as $roleName) {
            if (in_array($permission, $map[$roleName] ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    public function userPermissions(User $user): array
    {
        $map = $this->roleMap();
        $names = $user->roles()->pluck('roles.name')->all();
        $perms = [];
        foreach ($names as $roleName) {
            foreach ($map[$roleName] ?? [] as $perm) {
                $perms[$perm] = true;
            }
        }

        return array_keys($perms);
    }
}
