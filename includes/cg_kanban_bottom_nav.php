<?php
/**
 * Shared bottom navigation items (boards home) and header procurement control.
 */

if (!function_exists('cg_kanban_bottom_nav_boards_href')) {
    function cg_kanban_bottom_nav_boards_href(): string
    {
        return '/boards';
    }

    function cg_kanban_echo_bottom_nav_boards_button(bool $active = false): void
    {
        $cls = 'cg-bottom-nav__btn' . ($active ? ' is-active' : '');
        $current = $active ? ' aria-current="page"' : '';
        echo '<a href="' . htmlspecialchars(cg_kanban_bottom_nav_boards_href(), ENT_QUOTES, 'UTF-8') . '" class="' . $cls . '" id="cgBottomNavBoards" title="All boards" aria-label="All boards"' . $current . '>' . "\n";
        echo '    <i class="fas fa-grip" aria-hidden="true"></i>' . "\n";
        echo '    <span>Boards</span>' . "\n";
        echo '</a>' . "\n";
    }

    function cg_kanban_echo_workspace_dock_boards_button(): void
    {
        echo '<a href="' . htmlspecialchars(cg_kanban_bottom_nav_boards_href(), ENT_QUOTES, 'UTF-8') . '" class="cg-workspace-dock-btn" id="cgBottomNavBoards" data-label="Boards" title="All boards" aria-label="All boards">' . "\n";
        echo '    <i class="fas fa-grip" aria-hidden="true"></i>' . "\n";
        echo '</a>' . "\n";
    }

    function cg_kanban_procurement_url(?int $boardId = null): string
    {
        $base = 'https://procure.cinegrid.net/';
        $bid = $boardId !== null ? (int) $boardId : 0;
        if ($bid <= 0 && isset($_GET['board_id'])) {
            $bid = (int) $_GET['board_id'];
        }
        return $bid > 0 ? ($base . '?board_id=' . $bid) : $base;
    }

    /** Red icon button for header actions (beside Automations / Share). */
    function cg_kanban_echo_header_procurement_button(?int $boardId = null): void
    {
        $href = cg_kanban_procurement_url($boardId);
        echo '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" class="btn btn-danger" id="kanbanArtDeptBtn" target="_blank" rel="noopener" title="Open Procure for this board" aria-label="Open Procure">' . "\n";
        echo '    <i class="fas fa-film text-white" aria-hidden="true"></i>' . "\n";
        echo '</a>' . "\n";
    }
}
