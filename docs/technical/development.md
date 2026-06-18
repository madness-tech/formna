# Development and Extension

## Table of contents

1. [Development approach](#development-approach)
2. [Adding a new module](#adding-a-new-module)
3. [Database helpers](#database-helpers)
4. [Migrations](#migrations)
5. [Cron tasks](#cron-tasks)
6. [Translations](#translations)
7. [Testing guidance](#testing-guidance)

---

## Development approach

FORMNA favors explicit functions and modular files over framework conventions. When extending the system, try to match these existing patterns:

- small controllers that orchestrate work
- model functions for database access
- dedicated validation logic when inputs are complex
- direct, explicit route registration
- minimal hidden magic

---

## Adding a new module

1. Create `src/modules/<module>/`.
2. Add `controllers.php`.
3. Add `models.php`.
4. Add a `views/` directory.
5. Register routes in `src/config/routes.php`.

### Example controller

```php
function reports_index(): void {
    require_auth();
    require_role('admin');

    require __DIR__ . '/models.php';
    $data = reports_get_all();

    ob_start();
    require __DIR__ . '/views/index.php';
    $content = ob_get_clean();

    require __DIR__ . '/../../layouts/admin.php';
}
```

### Example model

```php
function reports_get_all(): array {
    return db_query("SELECT * FROM submissions ORDER BY created_at DESC");
}
```

---

## Database helpers

Prefer the shared helpers in `core/db.php` over raw PDO use.

| Helper | Use |
|---|---|
| `db_query()` | fetch multiple rows |
| `db_one()` | fetch one row |
| `db_insert()` | insert a record |
| `db_update()` | update records |
| `db_exec()` | arbitrary write query |
| `db_transaction()` | wrap related writes in a transaction |
| `paginate()` | paginated results |

### Example usage

```php
$users = db_query("SELECT * FROM users WHERE role = ?", ['admin']);
$user = db_one("SELECT * FROM users WHERE id = ?", [$id]);

$newId = db_insert('users', [
    'email' => 'user@example.com',
    'password' => password_hash($password, PASSWORD_BCRYPT),
    'role' => 'user',
]);
```

---

## Migrations

Generate migrations with Phinx:

```bash
vendor/bin/phinx create MyNewFeature
vendor/bin/phinx migrate
```

Use migrations for all schema changes that should be reproducible across environments.

---

## Cron tasks

To add a new scheduled task:

1. create a task script in `src/cron/tasks/`
2. add an entry to `src/cron/config.php`
3. use locking if overlap is unsafe
4. write to a task-specific log where useful

Example config:

```php
[
    'name' => 'my_task',
    'script' => 'my_task.php',
    'schedule' => '0 * * * *',
    'enabled' => true,
    'timeout' => 300,
    'description' => 'My custom task',
]
```

---

## Translations

High-level translation workflow:

1. add UI keys in the source language files
2. use translation helpers in templates
3. export templates through the admin panel when needed
4. translate imported key sets
5. re-import and validate rendering

Keep UI translations and help-content translations conceptually separate.

---

## Testing guidance

FORMNA includes PHPUnit test coverage for core functionality and cron operations. At minimum, the following areas should be validated when extending the system:

- route and permission coverage for new features
- database query correctness
- cron dispatch behavior for background tasks
- webhook and email side effects
- UI checks for builder, submission, and admin flows

### Running tests

Run the test suite:

```bash
./vendor/bin/phpunit --configuration phpunit.xml
```

Test coverage includes:

- cron expression parsing
- schedule config integrity
- router duplicate prevention
- lock behavior and dispatch simulation
- core helpers and validation
- authentication and middleware
- form scoring and validation
- MFA functionality
