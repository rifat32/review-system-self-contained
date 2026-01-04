<?php

$superadminPermissions = include(__DIR__ . '/permissions_superadmin.php');
$resellerPermissions = include(__DIR__ . '/permissions_reseller.php');
$businessOwnerPermissions = include(__DIR__ . '/permissions_business_owner.php');
$businessAdminPermissions = include(__DIR__ . '/permissions_business_admin.php');
$businessManagerPermissions = include(__DIR__ . '/permissions_business_manager.php');
$businessEmployeePermissions = include(__DIR__ . '/permissions_business_employee.php');

return [
    "roles_permission" => [
        [
            "role" => "superadmin",
            "permissions" => $superadminPermissions['permissions'],
        ],
        // [
        //     "role" => "reseller",
        //     "permissions" => $resellerPermissions['permissions'],
        // ],
        [
            "role" => "business_owner",
            "permissions" => $businessOwnerPermissions['permissions'],
        ],
        // [
        //     "role" => "business_admin",
        //     "permissions" => $businessAdminPermissions['permissions'],
        // ],
        // [
        //     "role" => "business_manager",
        //     "permissions" => $businessManagerPermissions['permissions'],
        // ],
        // [
        //     "role" => "business_employee",
        //     "permissions" => $businessEmployeePermissions['permissions'],
        // ],
        [
            "role" => "business_staff",
            "permissions" => [],
        ],
        [
            "role" => "branch_manager",
            "permissions" => [],
        ],
    ],
    "roles" => [
        "superadmin",
        "business_owner",
        "business_staff",
        "branch_manager",
    ],
    "permissions" => [],

    "permissions_titles" => [],
    "unchangeable_roles" => [],
    "unchangeable_permissions" => [],

    "beautified_permissions_titles" => [],

    "beautified_permissions" => [],

    "folder_locations" => [],



    "temporary_files_location" => "temporary_files",

    "reminder_options"  => [],

    "system_modules" => []





];
