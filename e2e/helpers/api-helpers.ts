import { exec } from 'child_process';
import { promisify } from 'util';

const execAsync = promisify(exec);

const APP_BASE_URL = process.env.APP_BASE_URL || 'http://localhost:8000';

/**
 * Reset the database to a clean state before test suites.
 * Uses artisan CLI to run fresh migrations with seed data.
 */
export async function resetDatabase(): Promise<void> {
  await execAsync('php artisan migrate:fresh --seed --force --no-interaction', {
    cwd: process.cwd().replace('/e2e', ''),
  });
}

/**
 * Seed specific test data (vendors, sessions, etc.)
 */
export async function seedTestVendors(): Promise<void> {
  await execAsync('php artisan db:seed --class=TestVendorSeeder --force --no-interaction', {
    cwd: process.cwd().replace('/e2e', ''),
  });
}

/**
 * Create a lunch session for today via artisan tinker or a custom command.
 */
export async function createTestSession(channelId: string, deadlineMinutes = 60): Promise<void> {
  const command = `php artisan tinker --execute="
    \\App\\Actions\\LunchSession\\CreateLunchSession::run(
      now()->toDateString(),
      '${channelId}',
      now()->addMinutes(${deadlineMinutes})
    );
  "`;
  await execAsync(command, {
    cwd: process.cwd().replace('/e2e', ''),
  });
}

/**
 * Lock a session by setting its deadline to the past.
 */
export async function forceSessionLock(): Promise<void> {
  const command = `php artisan tinker --execute="
    \\App\\Models\\LunchSession::query()
      ->where('status', 'open')
      ->update(['deadline_at' => now()->subMinutes(5)]);
    app(\\App\\Actions\\LunchSession\\LockExpiredSessions::class)->handle();
  "`;
  await execAsync(command, {
    cwd: process.cwd().replace('/e2e', ''),
  });
}

/**
 * Deactivate all vendors to test empty catalog fallback.
 */
export async function deactivateAllVendors(): Promise<void> {
  const command = `php artisan tinker --execute="
    \\App\\Models\\Vendor::query()->update(['active' => false]);
  "`;
  await execAsync(command, {
    cwd: process.cwd().replace('/e2e', ''),
  });
}

/**
 * Run the scheduler to trigger lock expired sessions/quickruns.
 */
export async function runScheduler(): Promise<void> {
  await execAsync('php artisan schedule:run --no-interaction', {
    cwd: process.cwd().replace('/e2e', ''),
  });
}
