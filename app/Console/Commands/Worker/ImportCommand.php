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

    public function handle()
    {
        $dir = storage_path('app/db_redispo/');
        $pattern = $dir . DIRECTORY_SEPARATOR . 'accountInfos*.tsv';
        $files = glob($pattern);

        if (empty($files)) {
            $this->warn("No files found matching accountInfos*.tsv in {$dir}");

            return 0;
        }

        $fillable = (new Worker())->getFillable();
        $summary = [];

        foreach ($files as $file) {
            $base = basename($file, '.tsv');              // e.g. "accountInfos-accounts:active"
            $keyPart = substr($base, strlen('accountInfos-')); // "accounts:active"
            $suffix = preg_replace('/[^A-Za-z0-9]+/', '_', $keyPart);
            $imported = $skipped = 0;

            // count total data rows (minus header)
            $fo = new \SplFileObject($file, 'r');
            $fo->seek(PHP_INT_MAX);
            $totalLines = $fo->key() + 1;
            $totalRows = max(0, $totalLines - 1);

            $this->info("\nProcessing {$base} ({$totalRows} rows)...");
            $bar = $this->output->createProgressBar($totalRows);
            $bar->start();

            if (! $handle = fopen($file, 'r')) {
                $this->error("Unable to open {$file}");

                continue;
            }

            // read & discard header
            $headers = fgetcsv($handle, 0, "\t");
            if (! is_array($headers)) {
                $this->error("Invalid TSV header in {$file}");
                fclose($handle);

                continue;
            }

            while (($row = fgetcsv($handle, 0, "\t")) !== false) {
                $data = array_combine($headers, $row);
                if ($data === false) {
                    $this->error("Malformed row in {$file}, skipping");
                    $bar->advance();

                    continue;
                }

                // only fillable + derived fields
                $attrs = Arr::only($data, $fillable);
                $attrs['status'] = 'redispo_' . $suffix;
                $attrs['code'] = 'redispo_' . date('d-m') . '_' . $suffix;
                $attrs['followers_count'] = (int) ($data['follower_count'] ?? 0);
                $attrs['following_count'] = (int) ($data['following_count'] ?? 0);

                if (
                    empty($attrs['username']) ||
                    Worker::where('username', $attrs['username'])->exists()
                ) {
                    $skipped++;
                } else {
                    Worker::create($attrs);
                    $imported++;
                }

                $bar->advance();
            }

            fclose($handle);

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

