<?php

namespace App\Console\Commands\Worker;

use App\Models\Worker;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class ImportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'worker:imports';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import workers from all accountInfos*.tsv files';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $dir = storage_path('app/db_redispo/');
        $files = glob($dir.DIRECTORY_SEPARATOR.'accountInfos*.tsv');

        if (empty($files)) {
            $this->warn("No files found matching accountInfos*.tsv in {$dir}");

            return 0;
        }

        $fillable = (new Worker)->getFillable();

        /* ---------------------------------------------------------
         * 0.  Load ALL existing usernames once (hash map)
         * --------------------------------------------------------- */
        $existingUsernames = Worker::pluck('username')
            ->flip()   // username => 1
            ->all();

        $summary = [];

        foreach ($files as $file) {
            $base = basename($file, '.tsv');               // accountInfos-accounts:active
            $keyPart = substr($base, strlen('accountInfos-')); // accounts:active
            $suffix = preg_replace('/[^A-Za-z0-9]+/', '_', $keyPart);

            /* -----------------------------------------------------
             * 1.  Read file headers
             * ----------------------------------------------------- */
            $handle = fopen($file, 'r');
            if (! $handle) {
                $this->error("Unable to open {$file}");

                continue;
            }

            $headers = fgetcsv($handle, 0, "\t");
            if (! is_array($headers)) {
                $this->error("Invalid TSV header in {$file}");
                fclose($handle);

                continue;
            }

            $usernameIdx = array_search('username', $headers);
            if ($usernameIdx === false) {
                $this->error("Column 'username' not found in {$file}");
                fclose($handle);

                continue;
            }

            /* -----------------------------------------------------
             * 2.  Stream rows and build bulk-insert payload
             * ----------------------------------------------------- */
            $insertPayload = [];
            $imported = $skipped = 0;

            // Count total rows for progress bar
            $fo = new \SplFileObject($file, 'r');
            $fo->seek(PHP_INT_MAX);
            $totalRows = max(0, $fo->key()); // minus header

            $this->info("\nProcessing {$base} ({$totalRows} rows)...");
            $bar = $this->output->createProgressBar($totalRows);
            $bar->start();

            while (($row = fgetcsv($handle, 0, "\t")) !== false) {
                $data = array_combine($headers, $row);
                if ($data === false) {
                    $this->error("Malformed row in {$file}, skipping");
                    $bar->advance();

                    continue;
                }

                $username = $data['username'] ?? null;
                if (empty($username) || isset($existingUsernames[$username])) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                $attrs = Arr::only($data, $fillable);
                $attrs['status'] = 'redispo_'.$suffix;
                $attrs['code'] = 'redispo_'.date('d-m').'_'.$suffix;
                $attrs['followers_count'] = (int) ($data['follower_count'] ?? 0);
                $attrs['following_count'] = (int) ($data['following_count'] ?? 0);

                $insertPayload[] = $attrs;
                $existingUsernames[$username] = true; // mark as now existing
                $imported++;
                $bar->advance();
            }
            fclose($handle);

            /* ---------------------------------------------------------
             * 3.  Chunked bulk INSERT
             * --------------------------------------------------------- */
            if ($insertPayload) {
                foreach (array_chunk($insertPayload, 1000) as $chunk) {
                    Worker::insert($chunk);
                }
            }

            $bar->finish();
            $this->newLine();

            $summary[$base] = compact('imported', 'skipped');
            $this->info(sprintf(
                '%s → imported: %d, skipped: %d',
                $base,
                $imported,
                $skipped
            ));
        }

        /* ---------------------------------------------------------
         * 4.  Final summary & log
         * --------------------------------------------------------- */
        $this->line("\nImport summary:");
        foreach ($summary as $fileKey => $counts) {
            $this->line(sprintf(
                '%s: imported=%d, skipped=%d',
                $fileKey,
                $counts['imported'],
                $counts['skipped']
            ));
        }

        Log::info('ImportWorkers summary', $summary);

        return 0;
    }
}
