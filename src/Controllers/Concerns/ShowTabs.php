<?php

declare(strict_types=1);

namespace App\Controllers\Concerns;

use App\Core\Response;

/** Shared header + inner tabs for every page inside a show. */
trait ShowTabs
{
    /** Render one of the show's inner tabs. */
    protected function showView(string $tab, array $show, array $data): Response
    {
        $id = (int) $show['id'];
        $counts = [
            'items' => (int) $this->db->value('SELECT COUNT(*) FROM show_items WHERE show_id = ? AND parent_id IS NULL', [$id]),
            'messages' => (int) $this->db->value("SELECT COUNT(*) FROM messages WHERE show_id = ? AND direction = 'show'", [$id]),
            'documents' => (int) $this->db->value('SELECT COUNT(*) FROM documents WHERE show_id = ?', [$id]),
            'team' => (int) $this->db->value('SELECT COUNT(*) FROM show_contacts WHERE show_id = ?', [$id]),
        ];

        return $this->view('shows/' . $tab, $data + [
            'title' => $show['title'],
            'module' => 'shows',
            'tab' => match ($show['status']) {
                'out' => 'out',
                'returned', 'closed', 'cancelled' => 'past',
                default => 'upcoming',
            },
            'show' => $show,
            'showTab' => $tab,
            'counts' => $counts,
        ]);
    }
}
