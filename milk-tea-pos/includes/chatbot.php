<?php if (!empty($_SESSION['user_id'])): ?>
<link rel="stylesheet" href="<?= appUrl('assets/css/chatbot.css') ?>">
<div id="bhSystemChat" data-endpoint="<?= appUrl('assistant/chat.php') ?>">
  <button type="button" id="bhChatLauncher" class="bh-chat-launcher" aria-label="Open Boba House Assistant" aria-expanded="false"><i class="bi bi-robot"></i></button>
  <section id="bhChatPanel" class="bh-chat-panel" aria-label="Boba House System Assistant">
    <div class="bh-chat-head"><div class="bh-chat-avatar"><i class="bi bi-robot"></i></div><div><div class="bh-chat-title">Boba House Assistant</div><div class="bh-chat-sub">System-aware · <?= htmlspecialchars($roleLabels[$role] ?? ucfirst($role)) ?></div></div><button type="button" id="bhChatClose" class="bh-chat-close" aria-label="Close"><i class="bi bi-x-lg"></i></button></div>
    <div id="bhChatBody" class="bh-chat-body"><div class="bh-msg bot"><div class="bh-bubble">Hi! I can explain the system and read live data you are allowed to see. Try asking “What reports are available?”, “How many branches are active?”, or “How does attendance affect payroll?”</div></div></div>
    <div id="bhChatQuick" class="bh-quick"><button type="button" class="bh-chip" data-bh-question="What reports are available?">Reports</button><button type="button" class="bh-chip" data-bh-question="What can I do in this system?">System functions</button><button type="button" class="bh-chip" data-bh-question="How many active branches are there?">Branches</button></div>
    <form id="bhChatForm" class="bh-chat-inputrow"><textarea id="bhChatInput" class="bh-chat-input" rows="1" maxlength="500" placeholder="Ask about Boba House…" aria-label="Ask the system assistant"></textarea><button class="bh-chat-send" type="submit" aria-label="Send"><i class="bi bi-send-fill"></i></button></form>
  </section>
</div>
<script src="<?= appUrl('assets/js/chatbot.js') ?>"></script>
<?php endif; ?>