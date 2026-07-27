---
trigger: always_on
---

# PHP Laravel Rules

1. **Route Grouping Convention**: Always organize API routes using `Route::controller()`. Strictly separate public and protected routes:
   - Group public routes by their respective controller at the top level.
   - For protected routes (e.g., requiring authentication), place them inside a single `Route::middleware(['auth:api'])->group(...)` block. Inside that middleware block, group the protected routes by their respective controller using `Route::controller(...)`. Do not nest middleware inside the controller blocks.
2. **Auth Facade**: Prefer using the `Illuminate\Support\Facades\Auth` facade (e.g., `Auth::user()`) over the global `auth()` helper function to prevent "Undefined method 'user'." IDE warnings.
3. **Route Versioning**: All API routes must include a version prefix (e.g., `v1.0/`) in their paths.
4. **Inline Comments**: Use inline uppercase comments (e.g., `// GET AUTHENTICATED USER`, `// TOTAL MENU COUNT`) before logical code blocks and database queries inside controller methods to clarify what is being fetched or calculated.
5. **PHP Laravel Response Pattern**: All API JSON responses must follow a standard format containing `success` (boolean), `message` (string), and `data` (array/object). Furthermore, always use the `Symfony\Component\HttpFoundation\Response` class constants (e.g., `Response::HTTP_OK`) for HTTP status codes instead of raw integers.
