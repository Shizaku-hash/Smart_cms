<div class="cms-card p-5 text-center my-5 mx-auto" style="max-width:520px;">
    <div class="display-4 text-danger fw-bold mb-2">403</div>
    <h4 class="fw-bold mb-2">Access Restricted</h4>
    <p class="text-muted mb-4">
        <?= e(__('messages.unauthorized')) ?>
        <?php if (!empty($requiredPermission)): ?>
            <br><code class="small">Required: <?= e($requiredPermission) ?></code>
        <?php endif; ?>
    </p>
    <a href="/dashboard" class="btn btn-primary">Return to Dashboard</a>
</div>
