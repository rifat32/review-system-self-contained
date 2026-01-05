<?php


$superadminPermissions = include(__DIR__ . '/permissions_superadmin.php');
$businessOwnerPermissions = include(__DIR__ . '/permissions_business_owner.php');

return [
    "roles_permission" => [
        [
            "role" => "superadmin",
            "permissions" => $superadminPermissions['permissions'],
        ],
        [
            "role" => "business_owner",
            "permissions" => $businessOwnerPermissions['permissions'],
        ],
        [
            "role" => "business_staff",
            "permissions" => [],
        ],
        [
            "role" => "branch_manager",
            "permissions" => [],
        ],
        [
            "role" => "customer",
            "permissions" => [],
        ],
    ],


    "permissions" => [],

    "permissions_titles" => [],
    "unchangeable_roles" => [],
    "unchangeable_permissions" => [],

    "beautified_permissions_titles" => [],

    "beautified_permissions" => [],



];
