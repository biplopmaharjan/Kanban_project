<?php
/**
 * Reusable board chat UI for portal pages (timeline, calendar, workspace).
 * Requires session, cg_portal_includes_base(), and Font Awesome (same as kanban).
 */

if (!function_exists('cg_kanban_board_chat_embed_user_id')) {
    function cg_kanban_board_chat_embed_user_id(): int
    {
        return (int)($_SESSION['user_id'] ?? 0);
    }

    function cg_kanban_board_chat_embed_echo_css_and_user(): void
    {
        if (cg_kanban_board_chat_embed_user_id() <= 0) {
            return;
        }
        $base = cg_portal_includes_base();
        echo '<link rel="stylesheet" href="' . htmlspecialchars($base . 'cg_kanban_board_chat.css', ENT_QUOTES, 'UTF-8') . '">' . "\n";
        $name = $_SESSION['user_name'] ?? 'User';
        echo '<script>window.CG_BOARD_CHAT_USER = { id: ' . (int)cg_kanban_board_chat_embed_user_id()
            . ', name: ' . json_encode($name, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)
            . ', inline: true };</script>' . "\n";
    }

    function cg_kanban_board_chat_embed_echo_deferred_js(): void
    {
        if (cg_kanban_board_chat_embed_user_id() <= 0) {
            return;
        }
        $base = cg_portal_includes_base();
        echo '<script defer src="' . htmlspecialchars($base . 'cg_kanban_board_chat.js', ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";
    }

    function cg_kanban_board_chat_embed_echo_bottom_nav_chat_button(): void
    {
        if (cg_kanban_board_chat_embed_user_id() <= 0) {
            return;
        }
        echo '<button type="button" class="cg-bottom-nav__btn" id="cgBottomNavChat" title="Board chat" aria-label="Show or hide board chat" aria-expanded="false" aria-controls="cgBoardChatPanel">' . "\n";
        echo '    <i class="fas fa-comments" aria-hidden="true"></i>' . "\n";
        echo '    <span>Chat</span>' . "\n";
        echo '</button>' . "\n";
    }

    function cg_kanban_board_chat_embed_echo_panel_and_fab(): void
    {
        if (cg_kanban_board_chat_embed_user_id() <= 0) {
            return;
        }
        echo '<aside id="cgBoardChatPanel" class="cg-board-chat-panel-root cg-board-chat--inline" aria-hidden="true" aria-label="Board chat">' . "\n";
        echo '    <div class="cg-board-chat__header">' . "\n";
        echo '        <div class="cg-board-chat__header-main" id="cgBoardChatHeaderMain" title="Drag to move">' . "\n";
        echo '            <div class="cg-board-chat__header-text">' . "\n";
        echo '                <h3 class="cg-board-chat__title"><i class="fas fa-comments me-2" aria-hidden="true"></i>Board chat</h3>' . "\n";
        echo '                <p class="cg-board-chat__subtitle">Collaborators on this board. Drag a card onto the composer to reference it.</p>' . "\n";
        echo '            </div>' . "\n";
        echo '        </div>' . "\n";
        echo '        <button type="button" class="cg-board-chat__close" id="cgBoardChatClose" aria-label="Close board chat"><i class="fas fa-times" aria-hidden="true"></i></button>' . "\n";
        echo '    </div>' . "\n";
        echo '    <div class="cg-board-chat__online">' . "\n";
        echo '        <span class="cg-board-chat__online-label">Online</span>' . "\n";
        echo '        <div class="cg-board-chat__online-list" id="cgBoardChatOnlineList"></div>' . "\n";
        echo '    </div>' . "\n";
        echo '    <div class="cg-board-chat__messages" id="cgBoardChatMessages"></div>' . "\n";
        echo '    <div class="cg-board-chat__composer-wrap" id="cgBoardChatComposer">' . "\n";
        echo '        <div class="cg-board-chat__typing" id="cgBoardChatTyping" hidden></div>' . "\n";
        echo '        <div class="cg-board-chat__drop-hint" id="cgBoardChatDropHint">Drop a card on this area to attach context</div>' . "\n";
        echo '        <div class="cg-board-chat__attach-row" id="cgBoardChatAttachRow"></div>' . "\n";
        echo '        <div class="cg-board-chat__input-row">' . "\n";
        echo '            <textarea class="cg-board-chat__textarea" id="cgBoardChatTextarea" rows="1" placeholder="Type your message..." maxlength="4000" autocomplete="off"></textarea>' . "\n";
        echo '            <button type="button" class="cg-board-chat__send" id="cgBoardChatSend" title="Send" aria-label="Send message"><i class="fas fa-paper-plane" aria-hidden="true"></i></button>' . "\n";
        echo '            <div id="cgBoardChatMentionDropdown" class="cg-board-chat__mention-dropdown" hidden role="listbox" aria-label="Tag collaborator"></div>' . "\n";
        echo '        </div>' . "\n";
        echo '    </div>' . "\n";
        echo '</aside>' . "\n";
        echo '<button type="button" id="cgBoardChatFab" class="is-hidden" hidden aria-hidden="true" aria-label="Board chat"></button>' . "\n";
    }
}
