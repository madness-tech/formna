<?php

/**
 * Webhooks Module - Admin Controllers
 *
 * Super Admin UI for managing webhooks
 */

require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/models.php';

/**
 * List all webhooks
 * GET /admin/webhooks
 */
function webhooks_index(): void {
    require_auth();
    require_role('super_admin');
    
    $filter = (isset($_GET['filter']) && $_GET['filter'] === 'deleted') ? 'deleted' : 'active';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $result = get_webhooks($filter, $page, 20);
    $webhooks = $result['rows'];
    
    // Get stats for each webhook
    foreach ($webhooks as &$webhook) {
        $webhook['stats'] = get_webhook_stats($webhook['id']);
        $webhook['form'] = get_form_for_webhook($webhook['id']);
    }
    unset($webhook);
    // Write enriched rows back for the view
    $result['rows'] = $webhooks;
    
    // Get global stats
    $global_stats = get_global_webhook_stats();
    
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Webhooks']
    ];
    
    ob_start();
    require __DIR__ . '/views/index.php';
    $content = ob_get_clean();
    
    require __DIR__ . '/../../layouts/admin.php';
}

/**
 * Show create webhook form
 * GET /admin/webhooks/create
 */
function webhooks_create(): void {
    require_auth();
    require_role('super_admin');
    
    // Get all forms for selection
    $forms = db_query("SELECT id, name FROM forms WHERE deleted_at IS NULL ORDER BY name ASC");
    
    // Get old form data if validation failed
    $old = $_SESSION['webhook_form_data'] ?? [];
    unset($_SESSION['webhook_form_data']);
    
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Webhooks', 'url' => '/admin/webhooks'],
        ['label' => 'Create']
    ];
    
    ob_start();
    require __DIR__ . '/views/create.php';
    $content = ob_get_clean();
    
    require __DIR__ . '/../../layouts/admin.php';
}

/**
 * Store new webhook
 * POST /admin/webhooks
 */
function webhooks_store(): void {
    require_auth();
    require_role('super_admin');
    csrf_check();
    
    $name = trim($_POST['name'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $bearer_token = trim($_POST['bearer_token'] ?? '');
    $is_active = isset($_POST['is_active']);
    $include_user_data = isset($_POST['include_user_data']);
    $form_id = !empty($_POST['form_id']) ? (int)$_POST['form_id'] : null;
    
    // Validation
    if (empty($name)) {
        flash('error', 'Webhook name is required');
        $_SESSION['webhook_form_data'] = $_POST;
        redirect('/admin/webhooks/create');
    }
    
    if (empty($url)) {
        flash('error', 'Webhook URL is required');
        $_SESSION['webhook_form_data'] = $_POST;
        redirect('/admin/webhooks/create');
    }
    
    if (empty($bearer_token)) {
        flash('error', 'Bearer token is required');
        $_SESSION['webhook_form_data'] = $_POST;
        redirect('/admin/webhooks/create');
    }
    
    // Validate URL
    try {
        validate_webhook_url($url);
    } catch (Exception $e) {
        flash('error', $e->getMessage());
        $_SESSION['webhook_form_data'] = $_POST;
        redirect('/admin/webhooks/create');
    }
    
    try {
        $result = db_transaction(function() use ($name, $url, $bearer_token, $is_active, $include_user_data, $form_id) {
            $webhook_id = create_webhook(
                $name,
                $url,
                $bearer_token,
                $is_active,
                $include_user_data,
                current_user()['id']
            );
            
            if (!$webhook_id) {
                throw new Exception('Could not create webhook in database');
            }
            
            if ($form_id) {
                attach_webhook_to_form($webhook_id, $form_id);
            }
            
            return $webhook_id;
        });
        
        flash('success', 'Webhook created successfully');
        redirect('/admin/webhooks');
        
    } catch (Exception $e) {
        flash('error', $e->getMessage());
        $_SESSION['webhook_form_data'] = $_POST;
        redirect('/admin/webhooks/create');
    }
}

/**
 * Show edit webhook form
 * GET /admin/webhooks/{id}/edit
 */
function webhooks_edit(int $id): void {
    require_auth();
    require_role('super_admin');
    
    $webhook = get_webhook($id, false);
    
    if (!$webhook) {
        flash('error', 'Webhook not found');
        redirect('/admin/webhooks');
    }

    if ($webhook['deleted_at'] !== null) {
        flash('error', 'Cannot modify a deleted webhook');
        redirect('/admin/webhooks');
    }
    
    // Get all forms for selection
    $forms = db_query("SELECT id, name FROM forms WHERE deleted_at IS NULL ORDER BY name ASC");
    
    // Get attached form
    $attached_form = get_form_for_webhook($id);
    
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Webhooks', 'url' => '/admin/webhooks'],
        ['label' => 'Webhook Details']
    ];
    
    ob_start();
    require __DIR__ . '/views/edit.php';
    $content = ob_get_clean();
    
    require __DIR__ . '/../../layouts/admin.php';
}

/**
 * Update webhook
 * POST /admin/webhooks/{id}
 */
function webhooks_update(int $id): void {
    require_auth();
    require_role('super_admin');
    csrf_check();
    
    $webhook = get_webhook($id, false);
    
    if (!$webhook) {
        flash('error', 'Webhook not found');
        redirect('/admin/webhooks');
    }

    if ($webhook['deleted_at'] !== null) {
        flash('error', 'Cannot modify a deleted webhook');
        redirect('/admin/webhooks');
    }
    
    $name = trim($_POST['name'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $bearer_token = trim($_POST['bearer_token'] ?? '');
    $is_active = isset($_POST['is_active']);
    $include_user_data = isset($_POST['include_user_data']);
    $form_id = !empty($_POST['form_id']) ? (int)$_POST['form_id'] : null;
    
    // Validation
    if (empty($name)) {
        flash('error', 'Webhook name is required');
        redirect("/admin/webhooks/{$id}/edit");
    }
    
    if (empty($url)) {
        flash('error', 'Webhook URL is required');
        redirect("/admin/webhooks/{$id}/edit");
    }
    
    // Validate URL
    try {
        validate_webhook_url($url);
    } catch (Exception $e) {
        flash('error', $e->getMessage());
        redirect("/admin/webhooks/{$id}/edit");
    }
    
    try {
        // Prepare update data
        $update_data = [
            'name' => $name,
            'url' => $url,
            'is_active' => $is_active,
            'include_user_data' => $include_user_data
        ];
        
        // Only update bearer token if provided
        if (!empty($bearer_token)) {
            $update_data['bearer_token'] = $bearer_token;
        }
        
        // Update webhook
        update_webhook($id, $update_data);
        
        // Handle form attachment
        $current_form = get_form_for_webhook($id);
        
        if ($form_id) {
            // Attach to new form
            if (!$current_form || $current_form['id'] !== $form_id) {
                // Detach from current form first
                if ($current_form) {
                    detach_webhook_from_form($current_form['id'], $id);
                }
                attach_webhook_to_form($id, $form_id);
            }
        } else {
            // Detach from current form if no form selected
            if ($current_form) {
                detach_webhook_from_form($current_form['id'], $id);
            }
        }
        
        flash('success', 'Webhook updated successfully');
        redirect('/admin/webhooks');
        
    } catch (Exception $e) {
        flash('error', 'Failed to update webhook: ' . $e->getMessage());
        redirect("/admin/webhooks/{$id}/edit");
    }
}

/**
 * Delete webhook
 * POST /admin/webhooks/{id}/delete
 */
function webhooks_delete(int $id): void {
    require_auth();
    require_role('super_admin');
    csrf_check();
    
    $webhook = get_webhook($id, false);
    
    if (!$webhook) {
        flash('error', 'Webhook not found');
        redirect('/admin/webhooks');
    }
    
    try {
        delete_webhook($id);
        flash('success', 'Webhook deleted successfully');
    } catch (Exception $e) {
        flash('error', 'Failed to delete webhook: ' . $e->getMessage());
    }
    
    redirect('/admin/webhooks');
}

/**
 * Toggle webhook active status
 * POST /admin/webhooks/{id}/toggle
 */
function webhooks_toggle(int $id): void {
    require_auth();
    require_role('super_admin');
    csrf_check();
    
    $webhook = get_webhook($id, false);
    
    if (!$webhook) {
        flash('error', 'Webhook not found');
        redirect('/admin/webhooks');
    }

    if ($webhook['deleted_at'] !== null) {
        flash('error', 'Cannot modify a deleted webhook');
        redirect('/admin/webhooks');
    }
    
    try {
        $new_status = toggle_webhook_active($id);
        $status_text = $new_status ? 'activated' : 'deactivated';
        flash('success', "Webhook {$status_text} successfully");
    } catch (Exception $e) {
        flash('error', 'Failed to toggle webhook: ' . $e->getMessage());
    }
    
    redirect('/admin/webhooks');
}

/**
 * View webhook logs
 * GET /admin/webhooks/{id}/logs
 */
function webhooks_logs(int $id): void {
    require_auth();
    require_role('super_admin');
    
    $webhook = get_webhook($id, false);
    
    if (!$webhook) {
        flash('error', 'Webhook not found');
        redirect('/admin/webhooks');
    }
    
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $status_filter = $_GET['status'] ?? null;
    
    $logs = get_webhook_logs($id, $page, 50, $status_filter);
    $stats = get_webhook_stats($id);
    
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Webhooks', 'url' => '/admin/webhooks'],
        ['label' => 'Webhook Details', 'url' => '/admin/webhooks/' . $id . '/edit'],
        ['label' => 'Logs']
    ];
    
    ob_start();
    require __DIR__ . '/views/logs.php';
    $content = ob_get_clean();
    
    require __DIR__ . '/../../layouts/admin.php';
}
