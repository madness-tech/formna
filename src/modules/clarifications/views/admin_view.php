<!-- Admin: View Clarification Request Details -->
<?php
/**
 * @var array<string, mixed> $clarification
 * @var array<string, mixed> $submission
 * @var array<string, mixed> $form
 * @var array<string, mixed> $submitter
 * @var array<int, array<string, mixed>> $questions
 * @var array<string, mixed> $answers
 * @var array<string, mixed>|null $requester
 * @var string $title
 */
?>

<div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="page-title">Clarification Request Details</h1>
                <p class="body-text mt-1">
                    Review the clarification request and user responses
                </p>
            </div>
            <a href="/admin/forms/<?= $form['uuid'] ?>/submissions/<?= $submission['uuid'] ?>" 
               class="btn btn-secondary">
                ← Back to Submission
            </a>
        </div>
    </div>

    <!-- Status Card -->
    <div class="card mb-6 p-6">
        <div class="flex items-start justify-between mb-4">
            <div class="flex-1">
                <h2 class="section-title"><?= sanitize($form['name']) ?></h2>
                <p class="body-text mt-1">
                    Submitted by <?= sanitize($submitter['name']) ?>
                </p>
                <p class="body-text mt-1">
                    Request created by <?= sanitize($requester['name'] ?? 'Unknown') ?> on <?= format_datetime($clarification['created_at'], 'long') ?>
                </p>
            </div>
            <span class="px-3 py-1 rounded-full text-sm font-medium
                <?= $clarification['status'] == 'resolved' ? 'bg-green-100 text-green-800' :
                   ($clarification['status'] == 'responded' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800') ?>">
                <?= ucfirst($clarification['status']) ?>
            </span>
        </div>

        <!-- Overall Message -->
        <?php if (!empty($clarification['message'])): ?>
        <div class="alert alert-info">
            <div class="flex">
                <svg class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                <div class="ml-3 flex-1">
                    <h3 class="text-sm font-medium text-blue-900">Message to User</h3>
                    <div class="mt-2 text-sm text-blue-800">
                        <?= nl2br(sanitize($clarification['message'])) ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Questions -->
    <div class="space-y-6">
        <?php foreach ($clarification['items'] as $index => $item): ?>
            <?php 
                $question = $questions[$item['question_id']] ?? null;
                if (!$question) continue;
                
                $config = $question['config'];
                $options = $config['options'] ?? [];
                $option_map = [];
                if (!empty($options)) {
                    foreach ($options as $opt) {
                        $option_map[$opt['value']] = $opt['label'];
                    }
                }
                $original_answer = $answers[$item['question_id']] ?? null;
                $has_response = !empty($item['response']);
            ?>
            
            <div class="card">
                <!-- Question Header -->
                <div class="p-6 bg-gray-50 border-b border-gray-200">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="px-2 py-1 rounded text-xs font-medium
                                    <?= $item['type'] === 'rectification' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800' ?>">
                                    <?= ucfirst($item['type']) ?>
                                </span>
                                <?php if ($has_response): ?>
                                    <span class="px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">
                                        ✓ Responded
                                    </span>
                                <?php endif; ?>
                            </div>
                            <h3 class="section-title">
                                <?= sanitize($config['label']) ?>
                            </h3>
                            <?php if (!empty($config['description'])): ?>
                                <p class="body-text mt-1">
                                    <?= sanitize($config['description']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="p-6 space-y-4">
                    <!-- Admin's Guidance -->
                    <div>
                        <label>
                            <?= $item['type'] === 'rectification' ? 'Correction Required' : 'Clarification Needed' ?>
                        </label>
                        <div class="alert alert-warning">
                            <div class="text-sm text-gray-900">
                                <?= nl2br(sanitize($item['reason'] ?? '')) ?>
                            </div>
                        </div>
                    </div>

                    <!-- Original Answer -->
                    <div>
                        <label>
                            Original Answer
                        </label>
                        <div class="p-4 bg-gray-50 border border-gray-200 rounded-lg">
                            <div class="text-sm text-gray-900">
                                <?php if (is_array($original_answer)): ?>
                                    <?php
                                    // Map answer values to labels for select/radio/checkbox fields
                                    if (!empty($option_map)) {
                                        // Convert answer values to labels
                                        $answer_labels = array_map(function($val) use ($option_map) {
                                            return $option_map[$val] ?? $val;
                                        }, $original_answer);
                                        echo sanitize(implode(', ', $answer_labels));
                                    } else {
                                        echo sanitize(implode(', ', $original_answer));
                                    }
                                    ?>
                                <?php elseif ($original_answer): ?>
                                    <?php
                                    if ($question['type'] === 'datetime') {
                                        echo sanitize(format_datetime($original_answer, 'long'));
                                    } elseif ($question['type'] === 'date') {
                                        echo sanitize(format_datetime($original_answer, 'date'));
                                    } elseif (!empty($option_map)) {
                                        echo sanitize($option_map[$original_answer] ?? $original_answer);
                                    } else {
                                        echo sanitize($original_answer);
                                    }
                                    ?>
                                <?php else: ?>
                                    <em class="text-gray-400">No answer provided</em>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- User's Response -->
                    <?php if ($has_response): ?>
                        <div>
                            <label>
                                User's Response
                                <?php if (!empty($item['responded_at'])): ?>
                                    <span class="help-text">
                                        (<?= format_datetime($item['responded_at'], 'short') ?>)
                                    </span>
                                <?php endif; ?>
                            </label>
                            <div class="alert alert-success">
                                <div class="text-sm text-gray-900">
                                    <?php if (is_array($item['response'])): ?>
                                        <?php
                                        if (!empty($option_map)) {
                                            $response_labels = array_map(function($val) use ($option_map) {
                                                return $option_map[$val] ?? $val;
                                            }, $item['response']);
                                            echo sanitize(implode(', ', $response_labels));
                                        } else {
                                            echo sanitize(implode(', ', $item['response']));
                                        }
                                        ?>
                                    <?php else: ?>
                                        <?php
                                        if ($question['type'] === 'datetime') {
                                            echo sanitize(format_datetime($item['response'], 'long'));
                                        } elseif ($question['type'] === 'date') {
                                            echo sanitize(format_datetime($item['response'], 'date'));
                                        } elseif (!empty($option_map)) {
                                            echo sanitize($option_map[$item['response']] ?? $item['response']);
                                        } else {
                                            echo nl2br(sanitize($item['response']));
                                        }
                                        ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg">
                            <p class="text-sm text-amber-800">
                                ⏳ Waiting for user response
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Actions -->
    <?php if ($clarification['status'] === 'responded'): ?>
        <div class="card mt-6 p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-4">Review Actions</h3>
            <p class="body-text mb-4">
                The user has responded to all questions. Review the responses above and decide whether to accept or reject them.
            </p>
            <div class="flex gap-3">
                <a href="/admin/forms/<?= sanitize($form['uuid']) ?>/submissions/<?= sanitize($submission['uuid']) ?>"
                   class="btn btn-primary">
                    Go to Submission to Review
                </a>
            </div>
        </div>
    <?php elseif ($clarification['status'] === 'open'): ?>
        <div class="card mt-6 p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-4">Request Status</h3>
            <p class="body-text mb-4">
                This clarification request is still open. The user has not responded yet.
            </p>
            <div class="flex gap-3">
                <button
                    data-uuid="<?= sanitize($clarification['uuid']) ?>"
                    data-redirect="/admin/forms/<?= sanitize($form['uuid']) ?>/submissions/<?= sanitize($submission['uuid']) ?>"
                    onclick="cancelClarification(this.dataset.uuid, this.dataset.redirect)"
                    class="text-sm text-gray-500 hover:text-red-600 font-medium"
                >
                    Cancel Request
                </button>
            </div>
        </div>

        <script>
        function cancelClarification(uuid, redirectUrl) {
            if (!confirm('Cancel this clarification request?')) return;

            fetch('/admin/clarifications/' + uuid + '/cancel', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': '<?= csrf_token() ?>'
                }
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    window.location.href = redirectUrl;
                } else {
                    alert('Could not cancel: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(function() {
                alert('Request failed. Please try again.');
            });
        }
        </script>
    <?php endif; ?>
</div>
