# Laravel 9 to 10 Upgrade Guide

## Prerequisites Completed ✓

-   PHP version updated to ^8.1
-   composer.json dependencies updated

## Step-by-Step Upgrade Process

### 1. **Update PHP (if needed)**

Ensure your system has PHP 8.1 or higher installed:

```bash
php -v
```

If you need to install PHP 8.1+, download it from php.net or use a package manager.

### 2. **Clear All Caches**

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### 3. **Update Composer Dependencies**

```bash
composer update
```

**Note**: This may take several minutes. If you encounter conflicts, try:

```bash
composer update --with-all-dependencies
```

### 4. **Update Configuration Files**

#### a. Update `config/database.php`

Laravel 10 uses different configuration for database connections. No changes needed if using standard setup.

#### b. Update `config/sanctum.php` (if exists)

Sanctum 3.x has new configuration options. Publish the new config:

```bash
php artisan vendor:publish --tag=sanctum-config --force
```

### 5. **Code Changes Required**

#### a. **Route Model Binding**

Laravel 10 uses different implicit binding. If you have custom route model binding, check:

-   `app/Providers/RouteServiceProvider.php`

#### b. **String Helper Functions**

Some string helpers were deprecated. Replace if used:

-   `Str::of()` is preferred over `str_*` functions

#### c. **Validation Rules**

Check for any custom validation rules, they should be compatible.

#### d. **Middleware Changes**

Update `app/Http/Kernel.php` - no changes needed for standard setup.

### 6. **Update PHPUnit Configuration**

Check if `phpunit.xml` needs updates for PHPUnit 10:

```bash
# Backup first
cp phpunit.xml phpunit.xml.backup
```

Update the XML schema in `phpunit.xml` if needed.

### 7. **Test Database Connections**

Update your `.env` file if needed. No breaking changes for database drivers.

### 8. **Run Database Migrations**

```bash
php artisan migrate
```

### 9. **Update Passport (if using)**

```bash
php artisan passport:install --force
```

### 10. **Clear and Rebuild Caches**

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 11. **Update Swagger Documentation**

```bash
php artisan l5-swagger:generate
```

### 12. **Run Tests**

```bash
php artisan test
```

### 13. **Start the Development Server**

```bash
php artisan serve
```

## Breaking Changes to Watch For

### 1. **Minimum PHP Version**

-   Laravel 10 requires PHP 8.1+
-   Update your server/hosting PHP version

### 2. **Sanctum Changes**

-   Sanctum 3.x has new features
-   Token abilities are handled differently

### 3. **Service Providers**

-   Check all custom service providers
-   Update any deprecated methods

### 4. **Database Driver Support**

-   MySQL 5.7+ or MariaDB 10.3+
-   PostgreSQL 10.0+
-   SQLite 3.8.8+
-   SQL Server 2017+

### 5. **Deprecated Features Removed**

-   `Lang::get()` → use `__()` or `trans()`
-   String and array helpers (if not using `Illuminate\Support\Str`)

## Testing Checklist

-   [ ] Application starts without errors
-   [ ] Database connections work
-   [ ] Authentication works (Passport/Sanctum)
-   [ ] API endpoints respond correctly
-   [ ] File uploads work
-   [ ] Email sending works
-   [ ] Scheduled tasks run
-   [ ] Queue workers function
-   [ ] All tests pass

## Rollback Plan

If issues occur:

```bash
# Restore composer.json from git
git checkout composer.json composer.lock

# Reinstall old dependencies
composer install

# Clear caches
php artisan optimize:clear
```

## Additional Resources

-   [Official Laravel 10 Upgrade Guide](https://laravel.com/docs/10.x/upgrade)
-   [Laravel 10 Release Notes](https://laravel.com/docs/10.x/releases)

## Common Issues and Solutions

### Issue: Composer update fails

**Solution**: Try updating with `--no-scripts` flag:

```bash
composer update --no-scripts
composer install
```

### Issue: Class not found errors

**Solution**: Regenerate autoload files:

```bash
composer dump-autoload
```

### Issue: Config cache errors

**Solution**: Clear all caches:

```bash
php artisan optimize:clear
```

### Issue: Passport token errors

**Solution**: Reinstall Passport:

```bash
php artisan passport:install --force
```

## Next Steps After Upgrade

1. Review all deprecated warnings
2. Update any third-party packages
3. Test thoroughly in staging environment
4. Update documentation
5. Deploy to production with rollback plan ready
