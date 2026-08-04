# ActivityLog End-to-End Audit Report

**Date:** August 4, 2026
**Model Audited:** `App\Models\ActivityLog`

---

## 1. Is it already migrated?
**No.** 
There is no migration file for `activity_logs` in the `database/migrations` directory. A database query confirms that the `activity_logs` table does not exist in the database.

## 2. Is it a separate DB?
**Yes.** 
The model explicitly sets `protected $connection = 'logs';`. This means Laravel expects the data to be in a separate database connection named `logs` (configured in your `config/database.php`), rather than the main MySQL database. The user relation correctly uses `.setConnection('mysql')` to bridge back to the main DB.

## 3. How many columns are there?
While there is no database table or migration to confirm the physical schema, based on the `$fillable` array defined in the `ActivityLog` model, there are **13 custom columns** intended for this table:

1. `api_url`
2. `token`
3. `fields`
4. `user`
5. `user_id`
6. `activity`
7. `description`
8. `ip_address`
9. `request_method`
10. `device`
11. `is_error`
12. `message`
13. `status_code`

Accounting for Laravel's default Eloquent columns (`id`, `created_at`, and `updated_at`), the intended table structure has a total of **16 columns**.

## 4. Is it already implemented?
**Only Partially (Read-Only).** 
The implementation is incomplete. There is code set up to **read** the logs (via an API endpoint), but there is **no code in the entire project that writes or saves new logs** (i.e., no `ActivityLog::create()` calls, Observers, or Middleware are currently logging to it). Because the database table is missing, any attempt to read from this model right now will throw a SQL exception.

## 5. How many files use this code logic?
The `ActivityLog` logic is currently used in only **2 files**:

1. **`app/Http/Controllers/SetupController.php` (Line 229)**: 
   Contains the `getActivityLogs` method. It queries the `ActivityLog` model and allows filtering the results by `status_code`.
   
2. **`routes/web.php` (Line 192)**: 
   Defines the HTTP route (`GET /activity-log`) which is mapped to the `getActivityLogs` method in the SetupController.
