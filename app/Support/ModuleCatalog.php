<?php

namespace App\Support;

final class ModuleCatalog
{
    /** @return array<string, array{name:string, description:string, status:string, sort_order:int}> */
    public static function modules(): array
    {
        return [
            'core' => ['name' => 'Platform Core', 'description' => 'Identity, tenant context, users, workflow, security, and audit.', 'status' => 'active', 'sort_order' => 10],
            'procurement' => ['name' => 'Procurement', 'description' => 'Purchase requests, purchase orders, and supplier purchasing control.', 'status' => 'active', 'sort_order' => 20],
            'inventory' => ['name' => 'Inventory', 'description' => 'Goods receipt, stock balances, movements, and stock count.', 'status' => 'active', 'sort_order' => 30],
            'assets' => ['name' => 'SaMS — Super Asset Management System', 'description' => 'Asset register, maintenance, lifecycle, and history.', 'status' => 'active', 'sort_order' => 40],
            'budgeting' => ['name' => 'Budgeting', 'description' => 'Department budget allocation, commitment, actual, and controls.', 'status' => 'active', 'sort_order' => 50],
            'reporting' => ['name' => 'Reporting & Intelligence', 'description' => 'Operational reports, exports, print, and management insights.', 'status' => 'active', 'sort_order' => 60],
            'accounting' => ['name' => 'SaS — Super Accounting System', 'description' => 'General ledger, journals, AP, cash, bank, tax, and closing.', 'status' => 'active', 'sort_order' => 70],
            'pos' => ['name' => 'SPoS — Super Point of Sale', 'description' => 'Outlet, cashier, order, payment, shift, and sales control.', 'status' => 'planned', 'sort_order' => 80],
            'hotel' => ['name' => 'SHMS — Super Hotel Management System', 'description' => 'Reservation, front office, rooms, folio, housekeeping, and night audit.', 'status' => 'planned', 'sort_order' => 90],
            'hris' => ['name' => 'SHRiS — Super Human Resource Information System', 'description' => 'Employee records, attendance, leave, payroll, and workforce controls.', 'status' => 'active', 'sort_order' => 100],
            'mobile' => ['name' => 'Mobile Operations', 'description' => 'Approval, QR asset, stock count, and maintenance mobile tools.', 'status' => 'planned', 'sort_order' => 110],
            'intelligence' => ['name' => 'Super Intelligence', 'description' => 'AI-assisted analytics, anomaly detection, forecast, and narratives.', 'status' => 'active', 'sort_order' => 120],
        ];
    }

    /** @return array<string, array{module:string, name:string, description:string, sort_order:int}> */
    public static function permissions(): array
    {
        return [
            'core.dashboard.view' => self::permission('core', 'View dashboard', 10),
            'core.master.view' => self::permission('core', 'View master data', 20),
            'core.master.manage' => self::permission('core', 'Manage master data', 30),
            'core.approvals.view' => self::permission('core', 'View approval center', 40),
            'core.approvals.act' => self::permission('core', 'Approve or reject documents', 50),
            'core.users.manage' => self::permission('core', 'Manage company users', 60),
            'core.audit.view' => self::permission('core', 'View audit trail', 70),
            'core.settings.manage' => self::permission('core', 'Manage company settings', 80),
            'core.access.manage' => self::permission('core', 'Manage modules and permissions', 90),
            'core.attachments.manage' => self::permission('core', 'Upload and manage attachments', 100),

            'procurement.pr.view' => self::permission('procurement', 'View purchase requests', 10),
            'procurement.pr.manage' => self::permission('procurement', 'Create and edit purchase requests', 20),
            'procurement.pr.approve' => self::permission('procurement', 'Approve or reject purchase requests', 30),
            'procurement.po.view' => self::permission('procurement', 'View purchase orders', 40),
            'procurement.po.manage' => self::permission('procurement', 'Create and submit purchase orders', 50),
            'procurement.po.approve' => self::permission('procurement', 'Approve purchase orders', 60),

            'inventory.gr.view' => self::permission('inventory', 'View goods receipts', 10),
            'inventory.gr.manage' => self::permission('inventory', 'Create and post goods receipts', 20),
            'inventory.stock.view' => self::permission('inventory', 'View stock and stock counts', 30),
            'inventory.stock.manage' => self::permission('inventory', 'Create and post stock counts', 40),

            'assets.register.view' => self::permission('assets', 'View asset register', 10),
            'assets.register.manage' => self::permission('assets', 'Register and manage assets', 20),
            'assets.maintenance.view' => self::permission('assets', 'View asset maintenance', 30),
            'assets.maintenance.manage' => self::permission('assets', 'Create and complete maintenance', 40),

            'budgeting.view' => self::permission('budgeting', 'View budget control', 10),

            'reporting.view' => self::permission('reporting', 'Open report center and inventory reports', 10),
            'reporting.procurement.view' => self::permission('reporting', 'View procurement and supplier reports', 20),
            'reporting.assets.view' => self::permission('reporting', 'View asset maintenance reports', 30),

            'accounting.view' => self::permission('accounting', 'View accounting and reports', 10),
            'accounting.manage' => self::permission('accounting', 'Create accounts and journal vouchers', 20),
            'accounting.post' => self::permission('accounting', 'Post journal vouchers', 30),
            'accounting.consolidate' => self::permission('accounting', 'Manage entity consolidation and eliminations', 40),

            'hris.view' => self::permission('hris', 'View workforce directory and HR dashboard', 10),
            'hris.manage' => self::permission('hris', 'Manage organization and employee lifecycle', 20),
            'hris.leave.self' => self::permission('hris', 'Submit and manage own leave requests', 30),
            'hris.leave.approve' => self::permission('hris', 'Approve or reject employee leave', 40),
            'hris.sensitive.view' => self::permission('hris', 'View sensitive employee data and documents', 50),

            'intelligence.view' => self::permission('intelligence', 'View AI operational insights', 10),
            'intelligence.generate' => self::permission('intelligence', 'Generate AI operational insights', 20),
        ];
    }

    /** @return array<string, array{name:string, description:string}> */
    public static function roles(): array
    {
        return [
            'super_admin' => ['name' => 'Super Admin', 'description' => 'Protected company administrator with full enabled-module access.'],
            'purchasing' => ['name' => 'Purchasing', 'description' => 'Procurement operations, supplier control, assets, and reports.'],
            'warehouse' => ['name' => 'Warehouse', 'description' => 'Receiving, inventory, stock count, assets, and maintenance.'],
            'finance' => ['name' => 'Finance', 'description' => 'Approvals, budget controls, and management reporting.'],
            'hr' => ['name' => 'Human Resources', 'description' => 'Employee lifecycle, leave administration, sensitive records, and workforce controls.'],
            'staff' => ['name' => 'Staff', 'description' => 'Operational request creation and read-only access.'],
        ];
    }

    /** @return array<string, array<int, string>> */
    public static function legacyRolePermissions(): array
    {
        $all = array_keys(self::permissions());

        return [
            'super_admin' => $all,
            'purchasing' => [
                'core.dashboard.view', 'core.master.view', 'core.master.manage', 'core.attachments.manage',
                'procurement.pr.view', 'procurement.pr.manage', 'procurement.po.view', 'procurement.po.manage',
                'inventory.gr.view', 'inventory.stock.view',
                'assets.register.view', 'assets.register.manage', 'assets.maintenance.view', 'assets.maintenance.manage',
                'budgeting.view', 'reporting.view', 'reporting.procurement.view', 'reporting.assets.view',
                'accounting.view', 'accounting.manage',
                'intelligence.view', 'intelligence.generate',
            ],
            'warehouse' => [
                'core.dashboard.view', 'core.master.view', 'core.master.manage', 'core.attachments.manage',
                'procurement.pr.view', 'procurement.pr.manage', 'procurement.po.view',
                'inventory.gr.view', 'inventory.gr.manage', 'inventory.stock.view', 'inventory.stock.manage',
                'assets.register.view', 'assets.register.manage', 'assets.maintenance.view', 'assets.maintenance.manage',
                'reporting.view', 'reporting.assets.view',
                'intelligence.view',
            ],
            'finance' => [
                'core.dashboard.view', 'core.master.view', 'core.approvals.view', 'core.approvals.act', 'core.attachments.manage',
                'procurement.pr.view', 'procurement.pr.approve', 'procurement.po.view', 'procurement.po.approve',
                'inventory.gr.view', 'inventory.stock.view',
                'intelligence.view', 'intelligence.generate',
                'assets.register.view', 'assets.maintenance.view',
                'budgeting.view', 'reporting.view', 'reporting.procurement.view', 'reporting.assets.view',
                'accounting.view', 'accounting.manage', 'accounting.post', 'accounting.consolidate',
            ],
            'hr' => [
                'core.dashboard.view', 'core.master.view', 'core.attachments.manage', 'core.audit.view',
                'reporting.view', 'hris.view', 'hris.manage', 'hris.leave.self', 'hris.leave.approve', 'hris.sensitive.view',
            ],
            'staff' => [
                'core.dashboard.view', 'core.master.view', 'core.attachments.manage',
                'procurement.pr.view', 'procurement.pr.manage', 'procurement.po.view',
                'inventory.gr.view', 'inventory.stock.view',
                'assets.register.view', 'assets.maintenance.view', 'reporting.view',
                'hris.view', 'hris.leave.self',
            ],
        ];
    }

    /** @return array{module:string, name:string, description:string, sort_order:int} */
    private static function permission(string $module, string $name, int $sortOrder): array
    {
        return ['module' => $module, 'name' => $name, 'description' => $name, 'sort_order' => $sortOrder];
    }
}
