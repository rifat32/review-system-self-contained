# MySQL Authentication Error Fix Guide

## Error

```
SQLSTATE[HY000] [1524] Plugin 'mysql_native_password' is not loaded
```

## Cause

MySQL 8.0+ uses `caching_sha2_password` as the default authentication plugin, but your Laravel application or database user is configured to use `mysql_native_password` which is not loaded.

## Solutions (Choose One)

### ✅ Solution 1: Update Database User Authentication (Recommended)

Connect to MySQL and update your database user to use `caching_sha2_password`:

```bash
# Connect to MySQL
mysql -u root -p

# Then run these commands in MySQL:
ALTER USER 'root'@'localhost' IDENTIFIED WITH caching_sha2_password BY '12345678';
FLUSH PRIVILEGES;
EXIT;
```

Or if you're using a different user:

```sql
ALTER USER 'your_username'@'localhost' IDENTIFIED WITH caching_sha2_password BY 'your_password';
FLUSH PRIVILEGES;
```

### Solution 2: Create New User with Correct Authentication

```bash
# Connect to MySQL
mysql -u root -p

# Create new user or modify existing one
CREATE USER IF NOT EXISTS 'laravel_user'@'localhost' IDENTIFIED WITH caching_sha2_password BY 'your_password';
GRANT ALL PRIVILEGES ON review_system.* TO 'laravel_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Then update your `.env`:

```env
DB_USERNAME=laravel_user
DB_PASSWORD=your_password
```

### Solution 3: Enable mysql_native_password Plugin (If needed)

If you must use `mysql_native_password`:

```bash
# Connect to MySQL
mysql -u root -p

# Update user
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '12345678';
FLUSH PRIVILEGES;
EXIT;
```

### Solution 4: Add MySQL Options to Laravel Config

Update `config/database.php` to add PDO options:

```php
'mysql' => [
    'driver' => 'mysql',
    'url' => env('DATABASE_URL'),
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'forge'),
    'username' => env('DB_USERNAME', 'forge'),
    'password' => env('DB_PASSWORD', ''),
    'unix_socket' => env('DB_SOCKET', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'prefix_indexes' => true,
    'strict' => false,
    'engine' => null,
    'options' => extension_loaded('pdo_mysql') ? array_filter([
        PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
        PDO::ATTR_EMULATE_PREPARES => true,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode='NO_ENGINE_SUBSTITUTION'"
    ]) : [],
],
```

## Quick Fix Steps

### Step 1: Test Database Connection

```bash
# Test if you can connect to MySQL
mysql -u root -p -h 127.0.0.1 -P 3306

# If successful, check current authentication plugin
SELECT user, host, plugin FROM mysql.user WHERE user = 'root';
```

### Step 2: Fix Authentication (Choose method based on Step 1 results)

**If plugin is 'mysql_native_password' but not loaded:**

```sql
ALTER USER 'root'@'localhost' IDENTIFIED WITH caching_sha2_password BY '12345678';
FLUSH PRIVILEGES;
```

**If plugin is already 'caching_sha2_password':**
The issue might be with SSL. Add to your `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=review_system
DB_USERNAME=root
DB_PASSWORD=12345678
MYSQL_ATTR_SSL_CA=
```

### Step 3: Clear Laravel Config Cache

```bash
php artisan config:clear
php artisan cache:clear
```

### Step 4: Test Migration Again

```bash
php artisan migrate
```

## Alternative: Use SQLite for Development

If MySQL issues persist, you can temporarily use SQLite:

1. Update `.env`:

```env
DB_CONNECTION=sqlite
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=review_system
# DB_USERNAME=root
# DB_PASSWORD=12345678
```

2. Create database file:

```bash
touch database/database.sqlite
```

3. Run migrations:

```bash
php artisan migrate
```

## Verify MySQL Installation

Check your MySQL version:

```bash
mysql --version
```

If you're on Windows and using XAMPP/WAMP/Laragon, ensure MySQL service is running.

## Common Issues

### Issue: Can't connect to MySQL at all

**Solution**:

-   Check if MySQL service is running
-   Verify port 3306 is not blocked
-   Check credentials in `.env`

### Issue: Access denied for user

**Solution**:

-   Verify username and password in `.env`
-   Grant proper permissions to the user

### Issue: Database doesn't exist

**Solution**:

```sql
CREATE DATABASE review_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## After Fixing

Once the database connection is working:

1. Run migrations:

```bash
php artisan migrate
```

2. Seed data if needed:

```bash
php artisan db:seed
```

3. Install Passport:

```bash
php artisan passport:install
```

4. Test the application:

```bash
php artisan serve
```

## Need More Help?

Check:

-   MySQL error logs
-   Laravel logs in `storage/logs/laravel.log`
-   PHP PDO MySQL extension is installed: `php -m | grep pdo_mysql`
