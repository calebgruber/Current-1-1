<?php

namespace App\Domain\Authentication\Services;

use App\Domain\Administration\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lists and revokes a user's sessions stored by the database session driver.
 * Session IDs are bearer secrets, so they are never sent to the browser;
 * an HMAC of the ID identifies a session instead.
 */
class ActiveSessions
{
    public function supported(): bool
    {
        return config('session.driver') === 'database';
    }

    /** @return list<array{id: string, ip_address: ?string, user_agent: ?string, browser: string, platform: string, is_mobile: bool, last_active_at: string, is_current: bool}> */
    public function forUser(User $user, ?string $currentSessionId = null): array
    {
        return $this->query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(function ($row) use ($currentSessionId) {
                $agent = $this->parseAgent((string) $row->user_agent);

                return [
                    'id' => $this->publicId((string) $row->id),
                    'ip_address' => $row->ip_address,
                    'user_agent' => $row->user_agent ? Str::limit($row->user_agent, 200) : null,
                    'browser' => $agent['browser'],
                    'platform' => $agent['platform'],
                    'is_mobile' => $agent['mobile'],
                    'last_active_at' => Carbon::createFromTimestamp((int) $row->last_activity)->toIso8601String(),
                    'is_current' => $currentSessionId !== null && hash_equals((string) $row->id, $currentSessionId),
                ];
            })
            ->values()
            ->all();
    }

    public function revoke(User $user, string $publicId, ?string $currentSessionId = null): bool
    {
        foreach ($this->query()->where('user_id', $user->getKey())->pluck('id') as $id) {
            if (hash_equals($this->publicId((string) $id), $publicId) && $id !== $currentSessionId) {
                $this->query()->where('id', $id)->delete();
                AuditLog::record('session.revoked', $user, [], [], 'Signed out one session');

                return true;
            }
        }

        return false;
    }

    public function revokeOthers(User $user, ?string $currentSessionId): int
    {
        $query = $this->query()->where('user_id', $user->getKey());
        if ($currentSessionId) {
            $query->where('id', '!=', $currentSessionId);
        }
        $count = $query->delete();
        $this->cycleRememberToken($user);
        AuditLog::record('session.revoked', $user, [], [], "Signed out {$count} other session(s)");

        return $count;
    }

    public function revokeAll(User $user): int
    {
        $count = $this->query()->where('user_id', $user->getKey())->delete();
        $this->cycleRememberToken($user);
        AuditLog::record('session.revoked', $user, [], [], "Signed out of all {$count} session(s)");

        return $count;
    }

    public function publicId(string $sessionId): string
    {
        return substr(hash_hmac('sha256', $sessionId, (string) config('app.key')), 0, 32);
    }

    /** @return array{browser: string, platform: string, mobile: bool} */
    public function parseAgent(string $agent): array
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') || str_contains($agent, 'Opera') => 'Opera',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') || str_contains($agent, 'CriOS/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            $agent === '' => 'Unknown',
            default => 'Other',
        };
        $platform = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Mac OS X') || str_contains($agent, 'Macintosh') => 'macOS',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'CrOS') => 'ChromeOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'Unknown',
        };

        return ['browser' => $browser, 'platform' => $platform, 'mobile' => (bool) preg_match('/Mobile|Android|iPhone|iPad/i', $agent)];
    }

    private function cycleRememberToken(User $user): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
    }

    private function query(): Builder
    {
        return DB::connection(config('session.connection'))->table(config('session.table', 'sessions'));
    }
}
