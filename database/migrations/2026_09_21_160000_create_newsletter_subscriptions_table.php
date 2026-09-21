<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('newsletter_subscriptions')) {
            Schema::create('newsletter_subscriptions', function (Blueprint $table) {
                $table->id();
                // 191 keeps the unique index compatible with older utf8mb4 MySQL/MariaDB limits.
                $table->string('email', 191);
                $table->timestamp('subscribed_at');
                $table->timestamps();
                $table->unique('email');
            });

            return;
        }

        // A previous deployment can leave the table behind if MySQL commits the
        // CREATE TABLE before a later index/migration bookkeeping step fails.
        // Reconcile that partial state instead of trying to create the table again.
        $hasEmail = Schema::hasColumn('newsletter_subscriptions', 'email');
        $hasSubscribedAt = Schema::hasColumn('newsletter_subscriptions', 'subscribed_at');
        $hasCreatedAt = Schema::hasColumn('newsletter_subscriptions', 'created_at');
        $hasUpdatedAt = Schema::hasColumn('newsletter_subscriptions', 'updated_at');

        Schema::table('newsletter_subscriptions', function (Blueprint $table) use (
            $hasEmail,
            $hasSubscribedAt,
            $hasCreatedAt,
            $hasUpdatedAt,
        ) {
            if (! $hasEmail) {
                $table->string('email', 191);
            }
            if (! $hasSubscribedAt) {
                $table->timestamp('subscribed_at')->nullable();
            }
            if (! $hasCreatedAt) {
                $table->timestamp('created_at')->nullable();
            }
            if (! $hasUpdatedAt) {
                $table->timestamp('updated_at')->nullable();
            }
        });

        $this->ensureEmailUniqueIndex();
    }

    private function ensureEmailUniqueIndex(): void
    {
        if (! Schema::hasColumn('newsletter_subscriptions', 'email')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            $hasUniqueEmailIndex = collect(DB::select('SHOW INDEX FROM `newsletter_subscriptions`'))
                ->contains(
                    fn (object $index): bool =>
                        (int) ($index->Non_unique ?? 1) === 0
                        && ($index->Column_name ?? null) === 'email',
                );

            if (! $hasUniqueEmailIndex) {
                // Prefix indexing avoids the legacy 767-byte utf8mb4 index ceiling
                // while keeping the existing varchar column/data intact.
                DB::statement(
                    'ALTER TABLE `newsletter_subscriptions` '
                    .'ADD UNIQUE INDEX `newsletter_subscriptions_email_unique` (`email`(191))'
                );
            }

            return;
        }

        $hasUniqueEmailIndex = collect(Schema::getIndexes('newsletter_subscriptions'))
            ->contains(
                fn (array $index): bool =>
                    ($index['unique'] ?? false) === true
                    && in_array('email', $index['columns'] ?? [], true),
            );

        if (! $hasUniqueEmailIndex) {
            Schema::table('newsletter_subscriptions', function (Blueprint $table) {
                $table->unique('email');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscriptions');
    }
};
