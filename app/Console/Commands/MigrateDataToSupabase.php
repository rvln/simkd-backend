<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateDataToSupabase extends Command
{
    protected $signature   = 'db:migrate-to-supabase';
    protected $description = 'Migrate all data from local MySQL to Supabase (PostgreSQL)';

    /**
     * Tables to transfer, ordered to satisfy FK constraints (parents first).
     * cache, jobs, failed_jobs, and migrations are intentionally excluded.
     */
    protected array $tables = [
        'users',
        'capacities',
        'inventories',
        'personal_access_tokens',
        'visits',
        'donations',
        'item_donations',
        'distributions',
        'rejected_logs',
        'visit_reports',
    ];

    public function handle(): int
    {
        $source = DB::connection('mysql_local');
        $target = DB::connection('pgsql_supabase');

        $this->info('');
        $this->info('╔══════════════════════════════════════════════════╗');
        $this->info('║   MySQL (Local)  ─►  Supabase (PostgreSQL)       ║');
        $this->info('╚══════════════════════════════════════════════════╝');
        $this->info('');

        // Verify both connections are reachable
        try {
            $source->getPdo();
            $this->line('  <fg=green>✓</> Source: MySQL local connected');
        } catch (\Exception $e) {
            $this->error('  ✗ Source (MySQL local) could not connect: ' . $e->getMessage());
            $this->line('  → Pastikan MySQL local sedang berjalan (XAMPP/Laragon).');
            return Command::FAILURE;
        }

        try {
            $target->getPdo();
            $this->line('  <fg=green>✓</> Target: Supabase connected');
        } catch (\Exception $e) {
            $this->error('  ✗ Target (Supabase) could not connect: ' . $e->getMessage());
            $this->line('  → Pastikan project Supabase tidak dalam status Paused.');
            return Command::FAILURE;
        }

        $this->info('');

        // Disable FK checks on target (PostgreSQL) for safe truncate
        $target->statement('SET session_replication_role = replica;');

        foreach ($this->tables as $table) {
            $this->transferTable($source, $target, $table);
        }

        // Re-enable FK checks
        $target->statement('SET session_replication_role = DEFAULT;');

        $this->info('');
        $this->info('  <fg=green>✓ Selesai!</> Semua data berhasil dipindahkan ke Supabase.');
        $this->info('');

        return Command::SUCCESS;
    }

    protected function transferTable($source, $target, string $table): void
    {
        $rows  = $source->table($table)->get();
        $count = $rows->count();

        if ($count === 0) {
            $this->line("  <fg=yellow>SKIP</>  {$table} — tidak ada data");
            return;
        }

        // Wipe target table cleanly (CASCADE handles FK deps)
        $target->statement("TRUNCATE TABLE \"{$table}\" CASCADE");

        // Insert in chunks of 200 to avoid oversized query
        $data   = $rows->map(fn($row) => (array) $row)->chunk(200);
        $bar    = $this->output->createProgressBar($count);
        $this->output->write("  <fg=cyan>COPY</>  {$table} ({$count} rows) ");
        $bar->start();

        foreach ($data as $chunk) {
            $target->table($table)->insert($chunk->toArray());
            $bar->advance($chunk->count());
        }

        $bar->finish();
        $this->line('  <fg=green>✓</>');
    }
}
