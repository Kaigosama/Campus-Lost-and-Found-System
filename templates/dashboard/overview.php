<?php defined('APP_ROOT') || exit; /* Dashboard section, included by public/index.php: overview. */ ?>
<div class="page-header">
    <div>
        <h1>Hello, <?= e($user['first_name']) ?> <span aria-hidden="true">👋</span></h1>
        <p>
            <?php if (is_admin()): ?>Administrator overview.
            <?php elseif (is_staff()): ?>Here's what needs your attention at the Lost &amp; Found office.
            <?php else: ?>Track your lost-item reports and claims from here.<?php endif; ?>
        </p>
    </div>
    <div class="btn-row">
        <?php if (has_role('staff')): ?>
            <a class="btn btn-primary" href="<?= e(url('/report.php?type=found')) ?>">+ Log found item</a>
        <?php elseif (has_role('user')): ?>
            <a class="btn btn-outline" href="<?= e(url('/report.php?type=found')) ?>">+ I found something</a>
            <a class="btn btn-primary" href="<?= e(url('/report.php')) ?>">+ Report lost item</a>
        <?php endif; ?>
    </div>
</div>

    <?php if (is_admin()): ?>
        <?php
        $activityFilter = isset(ACTIVITY_FILTERS[$_GET['activity'] ?? '']) ? ($_GET['activity'] ?? '') : '';
        $attention = accounts_needing_attention();
        $counts24h = db()->query("SELECT
                COUNT(*) AS events,
                SUM(event_type = 'report_created') AS reports,
                SUM(event_type IN ('login_failed', 'report_rate_limited', 'report_duplicate_blocked')) AS blocked
            FROM security_events WHERE created_at > NOW() - INTERVAL 1 DAY")->fetch();
        ?>
        <div class="stat-grid">
            <div class="stat-card success">
                <span class="stat-label">Active users</span>
                <span class="stat-value"><?= count_where(all_users(), 'is_active', 1) ?></span>
                <a href="<?= e(url('/?tab=users')) ?>">Manage users &rarr;</a>
            </div>
            <div class="stat-card info">
                <span class="stat-label">Reports filed (24 h)</span>
                <span class="stat-value"><?= (int) $counts24h['reports'] ?></span>
                <span class="stat-note"><?= (int) $counts24h['events'] ?> events in total</span>
            </div>
            <div class="stat-card warning">
                <span class="stat-label">Refused attempts (24 h)</span>
                <span class="stat-value"><?= (int) $counts24h['blocked'] ?></span>
                <span class="stat-note">failed log-ins, limit and duplicate hits</span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Accounts needing attention</span>
                <span class="stat-value"><?= count($attention) ?></span>
                <a href="#attention">Review list &darr;</a>
            </div>
        </div>

        <section class="section">
            <div class="section-title">
                <h2>Item monitoring</h2>
            </div>
            <div class="stat-grid">
                <div class="stat-card warning">
                    <span class="stat-label">Claims awaiting review</span>
                    <span class="stat-value"><?= count(array_filter(all_claims(), 'awaiting_review')) ?></span>
                    <a href="<?= e(url('/?tab=queue')) ?>">Claims &rarr;</a>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Items in storage</span>
                    <span class="stat-value"><?= $storedCount ?></span>
                    <a href="<?= e(dashboard_url('items')) ?>">Found items &rarr;</a>
                </div>
                <div class="stat-card info">
                    <span class="stat-label">Open lost reports</span>
                    <span class="stat-value"><?= count_where(all_lost_reports(), 'status', 'open') ?></span>
                    <a href="<?= e(url('/?tab=reports')) ?>">Lost reports &rarr;</a>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Disposed items</span>
                    <span class="stat-value"><?= count_where(all_found_items(), 'status', 'disposed') ?></span>
                    <a href="<?= e(url('/?tab=disposed')) ?>">Disposed items &rarr;</a>
                </div>
            </div>
        </section>

        <?= dashboard_returns() ?>

        <section class="section">
            <div class="section-title">
                <h2>Recent activity</h2>
                <a href="<?= e(url('/?tab=logs')) ?>">Full security logs &rarr;</a>
            </div>
            <?php
            echo pill_tabs(array_map(fn ($f) => $f[0], ACTIVITY_FILTERS), [], $activityFilter, 'activity');
            $events = activity_events($activityFilter, null, 20);
            ?>
            <div class="table-tools">
                <input type="search" placeholder="Filter by name, role, action…" aria-label="Filter activity" data-table-filter="#activityTable">
                <span class="text-sm text-muted">Showing <span data-filter-count="#activityTable"><?= count($events) ?></span> (latest 20)</span>
            </div>
            <?= activity_table($events) ?>
            <p class="filter-empty" data-filter-empty hidden>No activity matches that filter.</p>
        </section>

        <section class="section" id="attention">
            <div class="section-title"><h2>Accounts needing attention</h2></div>
            <?php if ($attention): ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Account</th><th>Status</th><th>False reports</th><th>Spam reports</th><th>Blocked submissions (7 days)</th><th>Note</th><th class="actions"></th></tr></thead>
                    <tbody>
                    <?php foreach ($attention as $u): ?>
                        <tr>
                            <td><span class="table-title"><?= e(full_name($u)) ?></span><span class="table-sub"><?= e($u['email']) ?></span></td>
                            <td>
                                <span class="badge badge-<?= $u['is_active'] ? 'active' : 'inactive' ?>"><?= $u['is_active'] ? 'Active' : 'Deactivated' ?></span>
                                <?php if (account_locked($u)): ?><span class="badge badge-rejected">Locked</span><?php endif; ?>
                            </td>
                            <td><?= (int) $u['violations'] ?> / <?= FALSE_REPORTS_TO_DEACTIVATE ?></td>
                            <td><?= (int) $u['spam_reports'] ?></td>
                            <td><?= (int) $u['blocked_submissions'] ?></td>
                            <td class="text-sm"><?= match (true) {
                                $u['name_matches_deactivated'] => 'Same name as an account deactivated for false reports. Check it is not a re-registration.',
                                $u['deactivation_reason'] === 'false_reports' => 'Deactivated for repeated false reports.',
                                (bool) $u['no_school_record'] => 'Not linked to a school record: registered before school records and no record matches its email. Link it to the right student or employee number.',
                                default => '',
                            } ?></td>
                            <td class="actions"><a class="btn btn-outline btn-sm" href="<?= e(url('/?tab=users&id=' . $u['user_id'])) ?>">Review</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <p class="text-muted">No student or faculty account needs attention right now.</p>
            <?php endif; ?>
        </section>

    <?php elseif (is_staff()): ?>
        <?php
        $pendingClaims = newest_first(array_values(array_filter(all_claims(), 'awaiting_review')));
        $openReports   = count_where(all_lost_reports(), 'status', 'open');
        ?>
        <div class="stat-grid">
            <div class="stat-card warning">
                <span class="stat-label">Claims awaiting review</span>
                <span class="stat-value"><?= count($pendingClaims) ?></span>
                <a href="<?= e(url('/?tab=queue')) ?>">Review claims &rarr;</a>
            </div>
            <div class="stat-card">
                <span class="stat-label">Items in storage</span>
                <span class="stat-value"><?= $storedCount ?></span>
                <a href="<?= e(dashboard_url('items')) ?>">Found items &rarr;</a>
            </div>
            <div class="stat-card info">
                <span class="stat-label">Open lost reports</span>
                <span class="stat-value"><?= $openReports ?></span>
                <a href="<?= e(url('/?tab=reports&status=open')) ?>">Find matches &rarr;</a>
            </div>
            <div class="stat-card success">
                <span class="stat-label">Returned to owners</span>
                <span class="stat-value"><?= count_where(all_found_items(), 'status', 'returned') ?></span>
                <a href="<?= e(dashboard_url('returned')) ?>">Returned items &rarr;</a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Claims awaiting review</h2>
                <a href="<?= e(url('/?tab=queue')) ?>" class="btn btn-outline btn-sm">All claims</a>
            </div>
            <?php if ($pendingClaims): ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Item</th><th>Claimant</th><th>Submitted</th><th class="actions"></th></tr></thead>
                    <tbody>
                    <?php foreach ($pendingClaims as $claim): $item = find_found_item($claim['item_id']); $claimant = find_user($claim['claimant_id']); ?>
                        <tr>
                            <td><span class="table-title"><?= e($item['item_name']) ?></span><span class="table-sub"><?= e($item['category']) ?></span></td>
                            <td><?= e(full_name($claimant)) ?></td>
                            <td class="nowrap"><?= e(format_date($claim['date_claimed'])) ?></td>
                            <td class="actions"><a class="btn btn-primary btn-sm" href="<?= e(item_url('found', $item['item_id'], 'queue') . '#claim-' . $claim['claim_id']) ?>">Review</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <p class="text-muted mb-0">No pending claims. <span aria-hidden="true">🎉</span></p>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Posts awaiting review</h2>
                <a href="<?= e(url('/?tab=moderation')) ?>" class="btn btn-outline btn-sm">All posts</a>
            </div>
            <?php if ($pendingPosts): ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Item</th><th>Posted by</th><th>Submitted</th><th class="actions"></th></tr></thead>
                    <tbody>
                    <?php foreach ($pendingPosts as $post): ?>
                        <tr>
                            <td><span class="table-title"><?= e($post['item_name']) ?></span><span class="table-sub"><?= e($post['category']) ?></span></td>
                            <td><?= e(full_name(find_user($post['added_by']))) ?></td>
                            <td class="nowrap"><?= e(format_datetime($post['created_at'])) ?></td>
                            <td class="actions"><a class="btn btn-primary btn-sm" href="<?= e(item_url('found', $post['item_id'])) ?>">Review</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <p class="text-muted mb-0">No posts waiting for review.</p>
            <?php endif; ?>
        </div>

        <?= dashboard_returns() ?>

    <?php else: ?>
        <?php $myApprovedClaims = count(array_filter($myClaims, fn ($c) => in_array($c['status'], ['approved', 'verified'], true))); ?>
        <?= false_report_notice($user) ?>
        <div class="stat-grid">
            <div class="stat-card">
                <span class="stat-label">My open reports</span>
                <span class="stat-value"><?= count_where($myReports, 'status', 'open') ?></span>
                <a href="<?= e(url('/?tab=reports')) ?>">View reports &rarr;</a>
            </div>
            <div class="stat-card warning">
                <span class="stat-label">Pending claims</span>
                <span class="stat-value"><?= count_where($myClaims, 'status', 'pending') ?></span>
                <span class="stat-note"><?= count(array_filter($myClaims, 'is_rejected_claim')) ?> with a rejection</span>
                <a href="<?= e(url('/?tab=my_claims')) ?>">View claims &rarr;</a>
            </div>
            <div class="stat-card success">
                <span class="stat-label">Approved — ready for pickup</span>
                <span class="stat-value"><?= $myApprovedClaims ?></span>
                <span class="stat-note">bring your ID to the Admin Office</span>
            </div>
            <div class="stat-card info">
                <span class="stat-label">Items in storage</span>
                <span class="stat-value"><?= $storedCount ?></span>
                <a href="<?= e(url('/browse.php')) ?>">Browse now &rarr;</a>
            </div>
        </div>

        <?php if ($myApprovedClaims): ?>
            <div class="alert alert-success">
                <strong>Good news!</strong> One of your claims was approved. Check <a href="<?= e(url('/?tab=my_claims')) ?>">My Claims</a> for pickup instructions.
            </div>
        <?php endif; ?>

        <div class="grid grid-2">
            <div class="card">
                <div class="card-header">
                    <h2>Recent lost reports</h2>
                    <a href="<?= e(url('/?tab=reports')) ?>" class="text-sm">See all</a>
                </div>
                <?php if ($myReports): ?>
                    <ul class="timeline">
                        <?php foreach (array_slice($myReports, 0, 4) as $report): ?>
                            <li>
                                <time><?= e(format_date($report['date_lost'])) ?></time>
                                <div>
                                    <a href="<?= e(item_url('lost', $report['report_id'])) ?>" class="fw-600"><?= e($report['item_name']) ?></a>
                                    <div><?= status_badge($report['status']) ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted">You haven't reported anything yet.</p>
                    <a class="btn btn-outline btn-sm" href="<?= e(url('/report.php')) ?>">Report a lost item</a>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Recent claims</h2>
                    <a href="<?= e(url('/?tab=my_claims')) ?>" class="text-sm">See all</a>
                </div>
                <?php if ($myClaims): ?>
                    <ul class="timeline">
                        <?php foreach (array_slice($myClaims, 0, 4) as $claim): $item = find_found_item($claim['item_id']); ?>
                            <li>
                                <time><?= e(format_date($claim['date_claimed'])) ?></time>
                                <div>
                                    <a href="<?= e(item_url('found', $item['item_id'])) ?>" class="fw-600"><?= e($item['item_name']) ?></a>
                                    <div><?= claim_badges($claim) ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted">No claims yet. Spot your item in the found list? Claim it there.</p>
                    <a class="btn btn-outline btn-sm" href="<?= e(url('/browse.php')) ?>">Browse found items</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
