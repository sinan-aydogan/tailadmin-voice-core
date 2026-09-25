<?php

namespace App\Services;

use App\Models\VoiceTask;
use App\Jobs\GenerateTtsJob;
use App\Jobs\TranscribeSttJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class QueueWorkerService
{
    /**
     * Check if a queue worker process is currently active.
     */
    public static function isRunning(): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            try {
                $cmd = 'powershell -NoProfile -Command "Get-CimInstance Win32_Process -Filter \"Name = \'php.exe\'\" | Where-Object { $_.CommandLine -like \'*queue:work*\' -or $_.CommandLine -like \'*queue:listen*\' } | Select-Object -ExpandProperty ProcessId"';
                $result = Process::timeout(3)->run($cmd);
                return !empty(trim($result->output()));
            } catch (\Throwable $e) {
                return false;
            }
        }

        try {
            $result = Process::timeout(3)->run('pgrep -f "artisan queue:work|artisan queue:listen"');
            return !empty(trim($result->output()));
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Kill all currently running queue worker processes.
     */
    public static function killWorkers(): int
    {
        Log::info("Stopping queue worker processes...");

        if (PHP_OS_FAMILY === 'Windows') {
            try {
                $cmd = 'powershell -NoProfile -Command "$procs = Get-CimInstance Win32_Process -Filter \"Name = \'php.exe\'\" | Where-Object { $_.CommandLine -like \'*queue:work*\' -or $_.CommandLine -like \'*queue:listen*\' }; $count = $procs.Count; if ($count -gt 0) { $procs | ForEach-Object { Stop-Process -Id $_.ProcessId -Force } }; Write-Output $count"';
                $result = Process::timeout(5)->run($cmd);
                return (int) trim($result->output());
            } catch (\Throwable $e) {
                Log::warning("Failed to kill queue workers on Windows: " . $e->getMessage());
                return 0;
            }
        }

        try {
            $result = Process::timeout(3)->run('pkill -f "artisan queue:work|artisan queue:listen"');
            return $result->successful() ? 1 : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Ensure the queue worker is running in the background.
     */
    public static function ensureRunning(): void
    {
        if (self::isRunning()) {
            return;
        }

        Log::info("Starting background Queue Worker...");

        $phpBin = PHP_BINARY;
        $artisan = base_path('artisan');

        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen("start /B \"\" \"{$phpBin}\" \"{$artisan}\" queue:work --queue=default,tts,stt,downloads --sleep=2 --timeout=3600 --tries=1", "r"));
        } else {
            exec("{$phpBin} {$artisan} queue:work --queue=default,tts,stt,downloads --sleep=2 --timeout=3600 --tries=1 > /dev/null 2>&1 &");
        }
    }

    /**
     * Fully repair and restart queue:
     * 1. Kill any stuck worker/listen processes.
     * 2. Clear stale reserved jobs.
     * 3. Re-queue any stuck pending/running voice tasks.
     * 4. Start fresh queue worker.
     */
    public static function repair(): array
    {
        Log::info("Repairing and restarting queue worker system...");

        // 1. Kill hung worker processes
        $killed = self::killWorkers();

        // Give OS time to release file locks
        usleep(400000);

        // 2. Clear stale reserved jobs in the database
        $clearedJobs = 0;
        try {
            $clearedJobs = DB::table('jobs')->whereNotNull('reserved_at')->update([
                'reserved_at' => null,
                'attempts' => 0,
            ]);
        } catch (\Throwable $e) {
            Log::warning("Could not reset reserved_at in jobs table: " . $e->getMessage());
        }

        // 3. Find any stuck pending or running tasks and re-dispatch them
        $requeued = 0;
        try {
            $stuckTasks = VoiceTask::whereIn('status', ['pending', 'running'])->get();
            foreach ($stuckTasks as $task) {
                $task->update([
                    'status' => 'pending',
                    'started_at' => null,
                ]);

                if ($task->type === 'tts') {
                    GenerateTtsJob::dispatch($task->id)->onQueue('default');
                    $requeued++;
                } elseif ($task->type === 'stt') {
                    TranscribeSttJob::dispatch($task->id)->onQueue('default');
                    $requeued++;
                }
            }
        } catch (\Throwable $e) {
            Log::error("Error re-queueing stuck tasks: " . $e->getMessage());
        }

        // 4. Start fresh queue worker
        self::ensureRunning();

        // 5. Verify worker status
        usleep(500000);
        $isActive = self::isRunning();

        return [
            'success' => true,
            'is_running' => $isActive,
            'killed_processes' => $killed,
            'cleared_reserved_jobs' => $clearedJobs,
            'requeued_tasks' => $requeued,
            'message' => "Kuyruk başarıyla onarıldı ve yeniden başlatıldı." . ($requeued > 0 ? " {$requeued} bekleyen işlem yeniden kuyruğa alındı." : "")
        ];
    }
}
