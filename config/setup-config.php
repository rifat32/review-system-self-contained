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
    ],
    "roles" => [
        "superadmin",
        "business_owner",
    ],
    "permissions" => [

        "email_setting_update",
        "email_setting_view",

        "handle_self_registered_businesses",
        "business_owner",
        "business_admin",
        "business_employee",

        "superadmin",
        'reseller',
        "business_admin",
        "business_manager",

        "reminder_create",
        "reminder_update",
        "reminder_view",
        "reminder_delete",

        "system_setting_update",
        "system_setting_view",

        "module_update",
        "module_view",

        "business_tier_create",
        "business_tier_update",
        "business_tier_view",
        "business_tier_delete",

        "user_create",
        "user_update",
        "user_view",
        "user_delete",

        "employee_document_create",
        "employee_document_update",
        "employee_document_view",
        "employee_document_delete",

        "employee_job_history_create",
        "employee_job_history_update",
        "employee_job_history_view",
        "employee_job_history_delete",

        "employee_education_history_create",
        "employee_education_history_update",
        "employee_education_history_view",
        "employee_education_history_delete",


        "user_letter_create",
        "user_letter_update",

        "user_letter_view",
        "user_letter_delete",



        "employee_payslip_create",
        "employee_payslip_update",
        "employee_payslip_view",
        "employee_payslip_delete",

        "employee_note_create",
        "employee_note_update",
        "employee_note_view",
        "employee_note_delete",


        "employee_address_history_create",
        "employee_address_history_update",
        "employee_address_history_view",
        "employee_address_history_delete",

        "employee_passport_history_create",
        "employee_passport_history_update",
        "employee_passport_history_view",
        "employee_passport_history_delete",

        "employee_visa_history_create",
        "employee_visa_history_update",
        "employee_visa_history_view",
        "employee_visa_history_delete",

        "employee_right_to_work_history_create",
        "employee_right_to_work_history_update",
        "employee_right_to_work_history_view",
        "employee_right_to_work_history_delete",

        "employee_sponsorship_history_create",
        "employee_sponsorship_history_update",
        "employee_sponsorship_history_view",
        "employee_sponsorship_history_delete",

        "employee_pension_history_create",
        "employee_pension_history_update",
        "employee_pension_history_view",
        "employee_pension_history_delete",


        "employee_asset_create",
        "employee_asset_update",
        "employee_asset_view",
        "employee_asset_delete",

        "employee_social_site_create",
        "employee_social_site_update",
        "employee_social_site_view",
        "employee_social_site_delete",


        "role_create",
        "role_update",
        "role_view",
        "role_delete",

        "business_create",
        "business_update",
        "business_view",
        "business_delete",


        "template_create",
        "template_update",
        "template_view",
        "template_delete",



        "payment_type_create",
        "payment_type_update",
        "payment_type_view",
        "payment_type_delete",


        "product_category_create",
        "product_category_update",
        "product_category_view",
        "product_category_delete",

        "product_create",
        "product_update",
        "product_view",
        "product_delete",


        "department_create",
        "department_update",
        "department_view",
        "department_delete",

        "payrun_create",
        "payrun_update",
        "payrun_view",
        "payrun_delete",



        "job_listing_create",
        "job_listing_update",
        "job_listing_view",
        "job_listing_delete",

        "job_platform_create",
        "job_platform_update",
        "job_platform_activate",
        "job_platform_view",
        "job_platform_delete",

        "task_category_create",
        "task_category_update",
        "task_category_activate",
        "task_category_view",
        "task_category_delete",


        "project_create",
        "project_update",
        "project_view",
        "project_delete",

        "task_create",
        "task_update",
        "task_view",
        "task_delete",

        "label_create",
        "label_update",
        "label_view",
        "label_delete",

        "comment_create",
        "comment_update",
        "comment_view",
        "comment_delete",


        "holiday_create",
        "holiday_update",
        "holiday_view",
        "holiday_delete",

        "work_shift_create",
        "work_shift_update",
        "work_shift_view",
        "work_shift_delete",

        "employee_rota_create",
        "employee_rota_update",
        "employee_rota_view",
        "employee_rota_delete",


        "announcement_create",
        "announcement_update",
        "announcement_view",
        "announcement_delete",



        "social_site_create",
        "social_site_update",
        "social_site_view",
        "social_site_delete",



        "designation_create",
        "designation_update",
        "designation_view",
        "designation_activate",
        "designation_delete",


        "termination_type_create",
        "termination_type_update",
        "termination_type_view",
        "termination_type_activate",
        "termination_type_delete",

        "termination_reason_create",
        "termination_reason_update",
        "termination_reason_view",
        "termination_reason_activate",
        "termination_reason_delete",

        "bank_create",
        "bank_update",
        "bank_activate",
        "bank_view",
        "bank_delete",


        "job_type_create",
        "job_type_update",
        "job_type_activate",
        "job_type_view",
        "job_type_delete",

        "work_location_create",
        "work_location_update",
        "work_location_activate",
        "work_location_view",
        "work_location_delete",

        "recruitment_process_create",
        "recruitment_process_update",
        "recruitment_process_activate",
        "recruitment_process_view",
        "recruitment_process_delete",



        "employment_status_create",
        "employment_status_update",
        "employment_status_activate",
        "employment_status_view",
        "employment_status_delete",


        "asset_type_create",
        "asset_type_update",
        "asset_type_activate",
        "asset_type_view",
        "asset_type_delete",




        "letter_template_create",
        "letter_template_update",
        "letter_template_activate",
        "letter_template_view",
        "letter_template_delete",

        "setting_leave_type_create",
        "setting_leave_type_update",
        "setting_leave_type_activate",
        "setting_leave_type_view",
        "setting_leave_type_delete",

        "setting_leave_create",




        "leave_create",
        "leave_update",
        "leave_approve",
        "leave_view",
        "leave_delete",

        "candidate_create",
        "candidate_update",
        "candidate_view",
        "candidate_delete",


        "setting_attendance_create",

        "attendance_create",
        "attendance_update",
        "attendance_approve",
        "attendance_view",
        "attendance_delete",

        "setting_payroll_create",

        "business_times_update",
        "business_times_view",

    ],

    "permissions_titles" => [],
    "unchangeable_roles" => [
        // "superadmin",
        // "reseller"
    ],
    "unchangeable_permissions" => [
        // "business_update",
        // "business_view",
    ],

    "beautified_permissions_titles" => [




        "system_setting_update" => "update",
        "system_setting_view" => "view",

        "module_update" => "enable",
        "module_view" => "view",




        "user_create" => "create",
        "user_update" => "update",
        "user_view" => "view",
        "user_delete" => "delete",









        "business_tier_create" => "create",
        "business_tier_update" => "update",
        "business_tier_view" => "view",
        "business_tier_delete" => "delete",






        "employee_document_create" => "create",
        "employee_document_update"  => "update",
        "employee_document_view"  => "view",
        "employee_document_delete"  => "delete",

        "employee_job_history_create" => "create",
        "employee_job_history_update"  => "update",
        "employee_job_history_view"  => "view",
        "employee_job_history_delete"  => "delete",


        "employee_education_history_create" => "create",
        "employee_education_history_update"  => "update",
        "employee_education_history_view"  => "view",
        "employee_education_history_delete"  => "delete",


        "user_letter_create" => "create",
        "user_letter_update" => "update",
        "user_letter_view" => "view",
        "user_letter_delete" => "delete",



        "employee_payslip_create" => "create",
        "employee_payslip_update"  => "update",
        "employee_payslip_view"  => "view",
        "employee_payslip_delete"  => "delete",




        "employee_address_history_create" => "create",
        "employee_address_history_update"  => "update",
        "employee_address_history_view"  => "view",
        "employee_address_history_delete"  => "delete",

        "employee_passport_history_create" => "create",
        "employee_passport_history_update" => "update",
        "employee_passport_history_view" => "view",
        "employee_passport_history_delete" => "delete",

        "employee_visa_history_create" => "create",
        "employee_visa_history_update" => "update",
        "employee_visa_history_view" => "view",
        "employee_visa_history_delete" => "delete",

        "employee_right_to_work_history_create" => "create",
        "employee_right_to_work_history_update" => "update",
        "employee_right_to_work_history_view" => "view",
        "employee_right_to_work_history_delete" => "delete",








        "employee_sponsorship_history_create" => "create",
        "employee_sponsorship_history_update" => "update",
        "employee_sponsorship_history_view" => "view",
        "employee_sponsorship_history_delete" => "delete",


        "employee_pension_history_create" => "create",
        "employee_pension_history_update" => "update",
        "employee_pension_history_view" => "view",
        "employee_pension_history_delete" => "delete",







        "employee_asset_create" => "create",
        "employee_asset_update" => "update",
        "employee_asset_view" => "view",
        "employee_asset_delete" => "delete",


        "employee_social_site_create" => "create",
        "employee_social_site_update" => "update",
        "employee_social_site_view" => "view",
        "employee_social_site_delete" => "delete",




        "role_create" => "create",
        "role_update" => "update",
        "role_view" => "view",
        "role_delete" => "delete",

        "business_create" => "create",
        "business_update" => "update",
        "business_view" => "view",
        "business_delete" => "delete",

        "template_create" => "create",
        "template_update" => "update",
        "template_view" => "view",
        "template_delete" => "delete",

        "payment_type_create" => "create",
        "payment_type_update" => "update",
        "payment_type_view" => "view",
        "payment_type_delete" => "delete",

        "product_category_create" => "create",
        "product_category_update" => "update",
        "product_category_view" => "view",
        "product_category_delete" => "delete",

        "product_create" => "create",
        "product_update" => "update",
        "product_view" => "view",
        "product_delete" => "delete",

        "department_create" => "create",
        "department_update" => "update",
        "department_view" => "view",
        "department_delete" => "delete",

        "payrun_create" => "create",
        "payrun_update" => "update",
        "payrun_view" => "view",
        "payrun_delete" => "delete",



        "job_listing_create" => "create",
        "job_listing_update" => "update",
        "job_listing_view" => "view",
        "job_listing_delete" => "delete",

        "project_create" => "create",
        "project_update" => "update",
        "project_view" => "view",
        "project_delete" => "delete",

        "task_create" => "create",
        "task_update" => "update",
        "task_view" => "view",
        "task_delete" => "delete",

        "label_create" => "create",
        "label_update" => "update",
        "label_view" => "view",
        "label_delete" => "delete",



        "comment_create" => "create",
        "comment_update" => "update",
        "comment_view" => "view",
        "comment_delete" => "delete",


        "holiday_create" => "create",
        "holiday_update" => "update",
        "holiday_view" => "view",
        "holiday_delete" => "delete",

        "work_shift_create" => "create",
        "work_shift_update" => "update",
        "work_shift_view" => "view",
        "work_shift_delete" => "delete",

        "employee_rota_create" => "create",
        "employee_rota_update" => "update",
        "employee_rota_view" => "view",
        "employee_rota_delete" => "delete",


        "announcement_create" => "create",
        "announcement_update" => "update",
        "announcement_view" => "view",
        "announcement_delete" => "delete",




        "job_platform_create" => "create",
        "job_platform_update" => "update",
        "job_platform_activate" => "activate",
        "job_platform_view" => "view",
        "job_platform_delete" => "delete",

        "task_category_create" => "create",
        "task_category_update" => "update",
        "task_category_activate" => "activate",
        "task_category_view" => "view",
        "task_category_delete" => "delete",

        "social_site_create" => "create",
        "social_site_update" => "update",
        "social_site_view" => "view",
        "social_site_delete" => "delete",


        "designation_create" => "create",
        "designation_update" => "update",
        "designation_activate" => "activate",
        "designation_view" => "view",
        "designation_delete" => "delete",


        "termination_type_create" => "create",
        "termination_type_update" => "update",
        "termination_type_activate" => "activate",
        "termination_type_view" => "view",
        "termination_type_delete" => "delete",


        "termination_reason_create" => "create",
        "termination_reason_update" => "update",
        "termination_reason_activate" => "activate",
        "termination_reason_view" => "view",
        "termination_reason_delete" => "delete",


        "bank_create" => "create",
        "bank_update" => "update",
        "bank_activate" => "activate",
        "bank_view" => "view",
        "bank_delete" => "delete",




        "job_type_create" => "create",
        "job_type_update" => "update",
        "job_type_activate" => "activate",
        "job_type_view" => "view",
        "job_type_delete" => "delete",


        "work_location_create" => "create",
        "work_location_update" => "update",
        "work_location_activate" => "activate",
        "work_location_view" => "view",
        "work_location_delete" => "delete",

        "recruitment_process_create" => "create",
        "recruitment_process_update" => "update",
        "recruitment_process_activate" => "activate",
        "recruitment_process_view" => "view",
        "recruitment_process_delete" => "delete",



        "employment_status_create" => "create",
        "employment_status_update" => "update",
        "employment_status_activate" => "activate",
        "employment_status_view" => "view",
        "employment_status_delete" => "delete",

        "asset_type_create" => "create",
        "asset_type_update" => "update",
        "asset_type_activate" => "activate",
        "asset_type_view" => "view",
        "asset_type_delete" => "delete",

        "letter_template_create" => "create",
        "letter_template_update" => "update",
        "letter_template_activate" => "activate",
        "letter_template_view" => "view",
        "letter_template_delete" => "delete",



        "setting_leave_type_create" => "create",
        "setting_leave_type_update" => "update",
        "setting_leave_type_activate" => "activate",
        "setting_leave_type_view" => "view",
        "setting_leave_type_delete" => "delete",

        "setting_leave_create" => "create",




        "leave_create" => "create",
        "leave_update" => "update",
        "leave_approve" => "approve",

        "leave_view" => "view",
        "leave_delete" => "delete",


        "candidate_create" => "create",
        "candidate_update" => "update",

        "candidate_view" => "view",
        "candidate_delete" => "delete",




        "setting_attendance_create" => "create",


        "attendance_create" => "create",
        "attendance_update" => "update",
        "attendance_approve" => "approve",
        "attendance_view" => "view",
        "attendance_delete" => "delete",


        "setting_payroll_create" => "create",





    ],

    "beautified_permissions" => [


        [
            "header" => "letter_template",
            "permissions" => [
                "letter_template_create",
                "letter_template_update",
                "letter_template_activate",
                "letter_template_view",
                "letter_template_delete",
            ],
        ],


        [
            "header" => "system_setting",
            "permissions" => [
                "system_setting_update",
                "system_setting_view",
            ],
        ],

        [
            "header" => "module",
            "permissions" => [
                "module_update",
                "module_view",
            ],
        ],



        [
            "header" => "business_tier",
            "permissions" => [
                "business_tier_create",
                "business_tier_update",
                "business_tier_view",
                "business_tier_delete",
            ],

        ],





        [
            "header" => "user",
            "permissions" => [
                "user_create",
                "user_update",
                "user_view",
                "user_delete",

            ],
        ],





        [
            "header" => "employee_document",
            "permissions" => [
                "employee_document_create",
                "employee_document_update",
                "employee_document_view",
                "employee_document_delete",



            ],
        ],

        [
            "header" => "employee_job_history",
            "permissions" => [
                "employee_job_history_create",
                "employee_job_history_update",
                "employee_job_history_view",
                "employee_job_history_delete",



            ],
        ],

        [
            "header" => "employee_education_history",
            "permissions" => [
                "employee_education_history_create",
                "employee_education_history_update",
                "employee_education_history_view",
                "employee_education_history_delete",

            ],
        ],


        [
            "header" => "employee_payslip",
            "permissions" => [
                "employee_payslip_create",
                "employee_payslip_update",
                "employee_payslip_view",
                "employee_payslip_delete",
            ],
        ],



        [
            "header" => "employee_notes",
            "permissions" => [
                "employee_note_create",
                "employee_note_update",
                "employee_note_view",
                "employee_note_delete",

            ],
        ],

        [
            "header" => "employee_education_history",
            "permissions" => [
                "employee_note_create",
                "employee_note_update",
                "employee_note_view",
                "employee_note_delete",

            ],
        ],




        [
            "header" => "employee_address_history",
            "permissions" => [
                "employee_address_history_create",
                "employee_address_history_update",
                "employee_address_history_view",
                "employee_address_history_delete",

            ],
        ],


        [
            "header" => "employee_passport_history",
            "permissions" => [

                "employee_passport_history_create",
                "employee_passport_history_update",
                "employee_passport_history_view",
                "employee_passport_history_delete",

            ],
        ],

        [
            "header" => "employee_visa_history",
            "permissions" => [
                "employee_visa_history_create",
                "employee_visa_history_update",
                "employee_visa_history_view",
                "employee_visa_history_delete",
            ],
        ],

        [
            "header" => "employee_right_to_work_history",
            "permissions" => [
                "employee_right_to_work_history_create",
                "employee_right_to_work_history_update",
                "employee_right_to_work_history_view",
                "employee_right_to_work_history_delete",
            ],
        ],



        [
            "header" => "employee_sponsorship_history",
            "permissions" => [

                "employee_sponsorship_history_create",
                "employee_sponsorship_history_update",
                "employee_sponsorship_history_view",
                "employee_sponsorship_history_delete",


            ],
        ],


        [
            "header" => "employee_pension_history",
            "permissions" => [

                "employee_pension_history_create",
                "employee_pension_history_update",
                "employee_pension_history_view",
                "employee_pension_history_delete",


            ],
        ],








        [
            "header" => "employee_asset",
            "permissions" => [
                "employee_asset_create",
                "employee_asset_update",
                "employee_asset_view",
                "employee_asset_delete",


            ],
        ],



        [
            "header" => "employee_social_site",
            "permissions" => [
                "employee_social_site_create",
                "employee_social_site_update",
                "employee_social_site_view",
                "employee_social_site_delete",

            ],
        ],




        [
            "header" => "role",
            "permissions" => [
                "role_create",
                "role_update",
                "role_view",
                "role_delete",

            ],
        ],

        [
            "header" => "business",
            "permissions" => [
                "business_create",
                "business_update",
                "business_view",
                "business_delete",

            ],
        ],


        [
            "header" => "template",
            "permissions" => [
                "template_create",
                "template_update",
                "template_view",
                "template_delete",

            ],
        ],


        [
            "header" => "payment_type",
            "permissions" => [
                "payment_type_create",
                "payment_type_update",
                "payment_type_view",
                "payment_type_delete",


            ],
        ],




        [
            "header" => "department",
            "permissions" => [
                "department_create",
                "department_update",
                "department_view",
                "department_delete",
            ],
        ],



        [
            "header" => "job_listing",
            "permissions" => [
                "job_listing_create",
                "job_listing_update",
                "job_listing_view",
                "job_listing_delete",



            ],
        ],

        [
            "header" => "project",
            "permissions" => [

                "project_create",
                "project_update",
                "project_view",
                "project_delete",


            ],
        ],


        [
            "header" => "task",
            "permissions" => [
                "task_create",
                "task_update",
                "task_view",
                "task_delete",

            ],
            "module" => "task_management"
        ],

        [
            "header" => "label",
            "permissions" => [
                "label_create",
                "label_update",
                "label_view",
                "label_delete",

            ],
            "module" => "task_management"
        ],



        [
            "header" => "comment",
            "permissions" => [
                "comment_create",
                "comment_update",
                "comment_view",
                "comment_delete"
            ],
            "module" => "task_management"
        ],

        [
            "header" => "task_category",
            "permissions" => [

                "task_category_create",
                "task_category_update",
                "task_category_activate",
                "task_category_view",
                "task_category_delete",
            ],
            "module" => "task_management"
        ],




        [
            "header" => "holiday",
            "permissions" => [
                "holiday_create",
                "holiday_update",
                "holiday_view",
                "holiday_delete",




            ],
        ],

        [
            "header" => "work_shift",
            "permissions" => [
                "work_shift_create",
                "work_shift_update",
                "work_shift_view",
                "work_shift_delete",



            ],
        ],

        [
            "header" => "employee_rota",
            "permissions" => [
                "employee_rota_create",
                "employee_rota_update",
                "employee_rota_view",
                "employee_rota_delete",

            ],
        ],






        [
            "header" => "announcement",
            "permissions" => [
                "announcement_create",
                "announcement_update",
                "announcement_view",
                "announcement_delete",



            ],
        ],







        [
            "header" => "job_platform",
            "permissions" => [

                "job_platform_create",
                "job_platform_update",
                "job_platform_activate",
                "job_platform_view",
                "job_platform_delete",
            ],
        ],






        [
            "header" => "social_site",
            "permissions" => [

                "social_site_create",
                "social_site_update",
                "social_site_view",
                "social_site_delete",



            ],
        ],





        [
            "header" => "designation",
            "permissions" => [
                "designation_create",
                "designation_update",
                "designation_activate",
                "designation_view",
                "designation_delete",
            ],
        ],

        [
            "header" => "termination_type",
            "permissions" => [
                "termination_type_create",
                "termination_type_update",
                "termination_type_activate",
                "termination_type_view",
                "termination_type_delete",
            ],
        ],

        [
            "header" => "termination_reason",
            "permissions" => [
                "termination_reason_create",
                "termination_reason_update",
                "termination_reason_activate",
                "termination_reason_view",
                "termination_reason_delete",
            ],
        ],

        [
            "header" => "bank",
            "permissions" => [
                "bank_create",
                "bank_update",
                "bank_activate",
                "bank_view",
                "bank_delete",
            ],
        ],



        [
            "header" => "job_type",
            "permissions" => [
                "job_type_create",
                "job_type_update",
                "job_type_activate",
                "job_type_view",
                "job_type_delete",
            ],
        ],

        [
            "header" => "work_location",
            "permissions" => [
                "work_location_create",
                "work_location_update",
                "work_location_activate",
                "work_location_view",
                "work_location_delete",
            ],
        ],

        [
            "header" => "recruitment_process",
            "permissions" => [
                "recruitment_process_create",
                "recruitment_process_update",
                "recruitment_process_activate",
                "recruitment_process_view",
                "recruitment_process_delete",
            ],
        ],







        [
            "header" => "employment_status",
            "permissions" => [
                "employment_status_create",
                "employment_status_update",
                "employment_status_activate",
                "employment_status_view",
                "employment_status_delete",

            ],
        ],
        [
            "header" => "asset_type",
            "permissions" => [
                "asset_type_create",
                "asset_type_update",
                "asset_type_activate",
                "asset_type_view",
                "asset_type_delete",
            ],
        ],


        [
            "header" => "setting_leave_type",
            "permissions" => [
                "setting_leave_type_create",
                "setting_leave_type_update",
                "setting_leave_type_activate",
                "setting_leave_type_view",
                "setting_leave_type_delete",
            ],
        ],
        [
            "header" => "setting_leave",
            "permissions" => [

                "setting_leave_create",





            ],
        ],

        [
            "header" => "leave",
            "permissions" => [

                "leave_create",
                "leave_update",
                "leave_approve",
                "leave_view",
                "leave_delete",





            ],
        ],

        [
            "header" => "candidate",
            "permissions" => [

                "candidate_create",
                "candidate_update",
                "candidate_view",
                "candidate_delete",





            ],
        ],



        [
            "header" => "setting_attendance",
            "permissions" => [

                "setting_attendance_create",




            ],
        ],

        [
            "header" => "attendance",
            "permissions" => [

                "attendance_create",
                "attendance_update",
                "attendance_approve",
                "attendance_view",
                "attendance_delete",




            ],
        ],

        [
            "header" => "setting_payroll",
            "permissions" => [

                "setting_payroll_create",



            ],
        ],






    ],





    "folder_locations" => ["pension_scheme_letters", "recruitment_processes", "candidate_files", "leave_attachments", "assets", "documents", "education_docs", "right_to_work_docs", "visa_docs", "payment_record_file", "pension_letters", "payslip_logo", "business_images", "user_images"],



    "temporary_files_location" => "temporary_files",





    "reminder_options"  => [

        [
            "entity_name" => "pension_expiry",
            "model_name" => "EmployeePensionHistory",
            "user_relationship" => "employee",
            "issue_date_column" => "pension_enrollment_issue_date",
            'expiry_date_column' => "pension_re_enrollment_due_date",
            "user_eligible_field" => "pension_eligible"
        ],


        [
            "entity_name" => "sponsorship_expiry",
            "model_name" => "EmployeeSponsorshipHistory",
            "user_relationship" => "employee",
            "issue_date_column" => "date_assigned",
            'expiry_date_column' => "expiry_date",
            "user_eligible_field" => "is_active"
        ],

        [
            "entity_name" => "passport_expiry",
            "model_name" => "EmployeePassportDetailHistory",
            "user_relationship" => "employee",
            "issue_date_column" => "passport_issue_date",
            'expiry_date_column' => "passport_expiry_date",
            "user_eligible_field" => "is_active"

        ],

        [
            "entity_name" => "visa_expiry",
            "model_name" => "EmployeeVisaDetailHistory",
            "user_relationship" => "employee",
            "issue_date_column" => "visa_issue_date",
            'expiry_date_column' => "visa_expiry_date",
            "user_eligible_field" => "is_active"

        ],

        [
            "entity_name" => "right_to_work_expiry",
            "model_name" => "EmployeeRightToWorkHistory",
            "user_relationship" => "employee",
            "issue_date_column" => "right_to_work_check_date",
            'expiry_date_column' => "right_to_work_expiry_date",
            "user_eligible_field" => "is_active"

        ]


    ],

    "system_modules" => [
        "task_management",
        "user_activity",
        "employee_login",
        "employee_location_attendance",
        "flexible_shifts",
        "rota",
        "letter_template"
    ]





];
