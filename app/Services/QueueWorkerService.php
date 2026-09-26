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
    private static function heartbeatFile(): string
    {
        return storage_path('framework/worker_heartbeat.json');
    }

    /**
     * Check if a specific PID is alive.
     */
    public static function isPidAlive(int $pid): bool
    {
        if ($pid <= 0) return false;

        if (PHP_OS_FAMILY === 'Windows') {
            exec("tasklist /FI \"PID eq {$pid}\" /NH 2>nul", $output, $code);
            $joined = implode(' ', $output);
            return stripos($joined, 'php.exe') !== false;
        }

        return function_exists('posix_kill') ? @posix_kill($pid, 0) : true;
    }

    /**
     * Resolve the PowerShell executable path on Windows.
     */
    public static function getPowerShellBinary(): string
    {
        $systemRoot = getenv('SystemRoot') ?: 'C:\\Windows';
        $candidates = [
            $systemRoot . '\\System32\\WindowsPowerShell\\v1.0\\powershell.exe',
            'C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe',
            'C:\\Program Files\\PowerShell\\7\\pwsh.exe',
            'powershell.exe',
            'powershell',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === 'powershell' || $candidate === 'powershell.exe' || file_exists($candidate)) {
                return $candidate;
            }
        }

        return 'powershell';
    }

    /**
     * Check if a queue worker process is currently active.
     */
    public static function isRunning(): bool
    {
        // 1. Instant check: Heartbeat file continuously touched by Queue::looping / Queue::before / Queue::after
        $heartbeatFile = self::heartbeatFile();
        if (file_exists($heartbeatFile)) {
            $content = @file_get_contents($heartbeatFile);
            $data = $content ? @json_decode($content, true) : null;
            if (is_array($data) && !empty($data['timestamp'])) {
                $age = time() - (int)$data['timestamp'];
                $pid = (int)($data['pid'] ?? 0);

                // Fresh heartbeat (worker looped within last 12 seconds)
                if ($age <= 12) {
                    return true;
                }

                // If running a longer task, verify PID is still running
                if ($pid > 0 && $age <= 300) {
                    if (self::isPidAlive($pid)) {
                        return true;
                    }
                }
            }
        }

        // 2. Secondary check: Process lookup
        if (PHP_OS_FAMILY === 'Windows') {
            try {
                $ps = self::getPowerShellBinary();
                $result = Process::timeout(4)->run([
                    $ps,
                    '-NoProfile',
                    '-Command',
                    'Get-CimInstance Win32_Process -Filter "Name = \'php.exe\'" | Where-Object { $_.CommandLine -like \'*queue:work*\' -or $_.CommandLine -like \'*queue:listen*\' } | Select-Object -ExpandProperty ProcessId'
                ]);
                $output = trim($result->output());
                if (!empty($output)) {
                    $lines = preg_split('/[\r\n]+/', $output);
                    $firstPid = (int)trim($lines[0]);
                    if ($firstPid > 0) {
                        @file_put_contents(self::heartbeatFile(), json_encode([
                            'pid' => $firstPid,
                            'timestamp' => time(),
                        ]), LOCK_EX);
                    }
                    return true;
                }
                return false;
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

        $killed = 0;

        // 1. If heartbeat file has PID, kill that specific worker first
        $heartbeatFile = self::heartbeatFile();
        if (file_exists($heartbeatFile)) {
            $data = @json_decode(file_get_contents($heartbeatFile), true);
            $pid = (int)($data['pid'] ?? 0);
            if ($pid > 0 && self::isPidAlive($pid)) {
                if (PHP_OS_FAMILY === 'Windows') {
                    exec("taskkill /F /PID {$pid} 2>nul", $kOut, $kCode);
                    if ($kCode === 0) $killed++;
                } else {
                    if (function_exists('posix_kill')) {
                        @posix_kill($pid, 9);
                    }
                    $killed++;
                }
            }
            @unlink($heartbeatFile);
        }

        // 2. Kill any remaining queue worker processes
        if (PHP_OS_FAMILY === 'Windows') {
            try {
                $ps = self::getPowerShellBinary();
                $result = Process::timeout(6)->run([
                    $ps,
                    '-NoProfile',
                    '-Command',
                    '$procs = Get-CimInstance Win32_Process -Filter "Name = \'php.exe\'" | Where-Object { $_.CommandLine -like \'*queue:work*\' -or $_.CommandLine -like \'*queue:listen*\' }; $count = $procs.Count; if ($count -gt 0) { $procs | ForEach-Object { Stop-Process -Id $_.ProcessId -Force } }; Write-Output $count'
                ]);
                $count = (int) trim($result->output());
                return max($killed, $count);
            } catch (\Throwable $e) {
                return $killed;
            }
        }

        try {
            $result = Process::timeout(3)->run('pkill -f "artisan queue:work|artisan queue:listen"');
            return $result->successful() ? max(1, $killed) : $killed;
        } catch (\Throwable $e) {
            return $killed;
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
            $vbs = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'launch_voice_worker.vbs';
            $cmd = "\"{$phpBin}\" \"{$artisan}\" queue:work --queue=default,tts,stt,downloads --sleep=2 --timeout=3600 --tries=1";
            @file_put_contents($vbs, 'CreateObject("Wscript.Shell").Run "' . str_replace('"', '""', $cmd) . '", 0, False');
            pclose(popen("wscript.exe \"{$vbs}\"", "r"));
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
