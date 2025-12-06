# Database Migration Consolidation Guide

## Overview

This document outlines the consolidated migrations that replace multiple separate migration files for each table.

## Consolidated Migration Files Created

### 1. **users** table

-   **New file:** `2014_10_12_000000_create_users_table_consolidated.php`
-   **Replaces these migrations:**
    -   `2014_10_12_000000_create_users_table.php`
    -   `2025_10_27_173747_add_business_id_to_users_table.php`
    -   `2025_11_05_150430_add_date_of_birth_columns_to_users_table.php`
    -   `2025_11_11_072227_add_image_columns_to_users_table.php`
    -   `2025_11_24_110441_add_job_title_columns_to_users_table.php`
    -   `2025_11_29_121335_add_tenure_fields_to_users_table.php`

### 2. **businesses** table

-   **New file:** `2022_03_08_074409_create_businesses_table_consolidated.php`
-   **Replaces these migrations:**
    -   `2022_03_08_074409_create_businesses_table.php`
    -   `2025_09_11_150159_add_time_zone_to_businesses_table.php`
    -   `2025_11_05_110223_add_review_and_stuff_fields_to_businesses_table.php`
    -   `2025_11_12_080617_add_review_rules_to_businesses_table.php`
    -   `2025_11_13_074504_add_threshold_rating_to_businesses_table.php`
    -   `2025_11_13_130643_add_review_labels_to_businesses_table.php`
    -   `2025_11_25_130626_add_two_field_to_businesses_table.php`
    -   `2025_12_02_183258_add_customer_flow_settings_to_businesses_table.php`

### 3. **review_news** table

-   **New file:** `2022_03_29_142333_create_review_news_table_consolidated.php`
-   **Replaces these migrations:**
    -   `2022_03_29_142333_create_review_news_table.php`
    -   `2025_10_30_052128_add_enhancement_columns_to_review_news_table.php`
    -   `2025_11_08_103127_add_ai_fields_to_review_news_table.php`
    -   `2025_11_12_135450_add_staff_id_to_review_news_table.php`
    -   `2025_11_13_073945_add_status_to_review_news_table.php`
    -   `2025_11_28_174654_add_order_no_to_review_news_table.php`
    -   `2025_11_29_074807_add_ai_more_fields_to_review_news_table.php`
    -   `2025_11_29_150718_add_survey_id_to_review_news_table.php`
    -   `2025_12_02_183257_add_voice_review_fields_to_review_news_table.php`
    -   `2025_12_03_164839_add_is_private_to_review_news_table.php`

### 4. **questions** table

-   **New file:** `2022_03_29_123651_create_questions_table_consolidated.php`
-   **Replaces these migrations:**
    -   `2022_03_29_123651_create_questions_table.php`
    -   `2025_10_30_081408_add_fields_to_questions_table.php`
    -   `2025_11_17_135003_add_survey_name_to_questions_table.php`
    -   `2025_12_02_102757_add_order_no_to_questions.php`
    -   `2025_12_04_094119_add_is_staff_to_questions_table.php`

### 5. **tags** table

-   **New file:** `2022_03_29_142428_create_tags_table_consolidated.php`
-   **Replaces these migrations:**
    -   `2022_03_29_142428_create_tags_table.php`
    -   `2025_03_12_173809_add_is_active_to_tags_table.php`
    -   `2025_10_30_081316_add_fields_to_tags_table.php`

### 6. **surveys** table

-   **New file:** `2025_11_17_172554_create_surveys_table_consolidated.php`
-   **Replaces these migrations:**
    -   `2025_11_17_172554_create_surveys_table.php`
    -   `2025_11_28_181546_add_order_no_to_surveys_table.php`

### 7. **guest_users** table

-   **New file:** `2023_01_25_131737_create_guest_users_table_consolidated.php`
-   **Replaces these migrations:**
    -   `2023_01_25_131737_create_guest_users_table.php`
    -   `2025_11_24_143901_make_full_name_optional_to_guest_users_table.php`
    -   `2025_12_03_170226_add_email_to_guest_users_table.php`

## Tables with NO alterations (keep as-is)

These tables only have one migration file and don't need consolidation:

-   `branches`
-   `leaflets`
-   `stars`
-   `review_value_news`
-   `review_values`
-   `reviews`
-   `tag_reviews`
-   `star_tag_questions`
-   `qusetion_stars`
-   `star_tags`
-   `survey_questions`
-   `daily_views`
-   `notifications`
-   `business_days`
-   `business_time_slots`
-   `email_template_wrappers`
-   `email_templates`
-   `payment_types`
-   OAuth tables (5 files)
-   Stripe tables (3 files)
-   Password resets
-   Failed jobs
-   Personal access tokens
-   Permission tables
-   AI-related tables (ai_insights, staff_performance_snapshots)

## Steps to Apply Consolidated Migrations

### Option 1: Fresh Migration (RECOMMENDED if resetting database)

1. **Backup your current database** (if needed)

    ```bash
    php artisan db:backup  # or use your backup method
    ```

2. **Delete OLD migration files** (the ones being replaced):

    ```bash
    # Delete all the individual migration files listed above under "Replaces these migrations"
    ```

3. **Rename consolidated files** (remove `_consolidated` suffix):

    ```bash
    # Rename files like:
    # 2014_10_12_000000_create_users_table_consolidated.php
    # to:
    # 2014_10_12_000000_create_users_table.php
    ```

4. **Run fresh migration**:
    ```bash
    php artisan migrate:fresh --seed
    ```

### Option 2: Clean Slate (Complete Reset)

1. **Drop all tables**:

    ```bash
    php artisan db:wipe
    ```

2. **Delete OLD migration files** (listed above)

3. **Rename consolidated files** (remove `_consolidated` suffix)

4. **Run migrations**:
    ```bash
    php artisan migrate --seed
    ```

## Important Notes

⚠️ **Before proceeding:**

1. Ensure you have a complete backup of your database
2. Test on a development environment first
3. Review the consolidated migrations to ensure all fields are correct
4. Check foreign key dependencies and order of migrations

✅ **Benefits:**

-   Cleaner migration folder
-   Faster migration execution
-   Easier to understand table structure
-   Simpler database reset process

## Migration Order Dependencies

The consolidated migrations maintain the correct order:

1. `users` (2014_10_12_000000)
2. `businesses` (2022_03_08_074409) - depends on users
3. `surveys` (2025_11_17_172554) - depends on businesses
4. `guest_users` (2023_01_25_131737)
5. `tags` (2022_03_29_142428) - depends on businesses
6. `questions` (2022_03_29_123651) - depends on businesses
7. `review_news` (2022_03_29_142333) - depends on businesses, users, guest_users, surveys

## Verification

After migration, verify:

```sql
-- Check table structure
DESCRIBE users;
DESCRIBE businesses;
DESCRIBE review_news;
DESCRIBE questions;
DESCRIBE tags;
DESCRIBE surveys;
DESCRIBE guest_users;

-- Verify foreign keys
SELECT
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'your_database_name'
AND REFERENCED_TABLE_NAME IS NOT NULL;
```

## Rollback Plan

If something goes wrong:

1. Restore from backup
2. Keep old migration files as backup
3. You can always revert to the original separate migrations

---

**Created:** December 6, 2025
**Purpose:** Database migration consolidation for cleaner structure
