<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** A simple timeline of what happened on each show. */
final class Activity
{
    public static function log(Database $db, ?int $showId, ?int $userId, string $description): void
    {
        $db->insert('activity', [
            'show_id' => $showId,
            'user_id' => $userId,
            'description' => mb_substr($description, 0, 500),
            'created_at' => now(),
        ]);
    }
}
