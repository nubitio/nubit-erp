<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Dashboard performance: add missing indexes that caused full-table scans on
 * every dashboard load for tenants with large data sets.
 *
 * Problems fixed:
 *
 * 1. time_dimension.date has no secondary index.
 *    All dashboard queries JOIN summary tables ON td.date = bds.summary_date,
 *    so every JOIN required a full scan of ~3 650 rows (10 years of dates).
 *
 * 2. branch_daily_summary PRIMARY KEY is (summary_date, branch_id).
 *    The leading column is summary_date, so WHERE branch_id = :id can only use
 *    the existing idx_branch_daily_date index (summary_date-first), which is not
 *    selective enough. Adding (branch_id, summary_date) allows all per-branch
 *    range queries to be served from a covering index.
 *
 * 3. sales table: the customers COUNT(DISTINCT customer_id) query filters on
 *    branch_id + issued_at range but the existing idx_sales_branch_issued is
 *    (branch_id, issued_at) which already covers this case — no action needed.
 *
 * 4. purchases_daily_summary already has idx_purchases_daily_branch_date
 *    (branch_id, summary_date) — no action needed.
 */
final class Version20260303300000_dashboard_perf_indexes extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Dashboard perf: add index on time_dimension(date) and branch_daily_summary(branch_id, summary_date)';
    }

    public function up(Schema $schema): void
    {
        // Allow tenant DBs that may already have been patched manually.
        $this->addSql('CREATE INDEX idx_time_dimension_date ON time_dimension (date)');
        $this->addSql('CREATE INDEX idx_branch_daily_branch_date ON branch_daily_summary (branch_id, summary_date)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_time_dimension_date ON time_dimension');
        $this->addSql('DROP INDEX idx_branch_daily_branch_date ON branch_daily_summary');
    }
}
