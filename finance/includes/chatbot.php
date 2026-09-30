<?php if (!empty($_SESSION['user_id'])): ?>
<link rel="stylesheet" href="assets/css/chatbot.css?v=20260929-finance-v5">
<div id="bhSystemChat" data-endpoint="assistant/chat.php" data-finance-chat="1">
  <button type="button" id="bhChatLauncher" class="bh-chat-launcher" aria-label="Open Boba House Finance Assistant" aria-expanded="false"><span class="bh-launch-logo">🧋</span></button>
  <section id="bhChatPanel" class="bh-chat-panel" aria-label="Boba House Finance Assistant">
    <div class="bh-chat-head">
      <div class="bh-chat-avatar bh-brand-logo" aria-label="Boba House"><span>🧋</span></div>
      <div><div class="bh-chat-title">Boba House Assistant</div><div class="bh-chat-sub">Finance · <?= e($user['role_label'] ?? $user['role_name'] ?? 'User') ?></div></div>
      <button type="button" id="bhChatClose" class="bh-chat-close" aria-label="Close"><i class="bi bi-x-lg"></i></button>
    </div>
    <div id="bhChatBody" class="bh-chat-body">
      <div class="bh-msg bot"><div class="bh-bubble">Hi! I can explain Finance and read the live Finance data your account is allowed to use. Try asking “How much are today’s sales?”, “How many active branches?”, or “How does Finance connect to HRMS and POS?”</div></div>
    </div>
    <div id="bhChatQuick" class="bh-quick">
      <button type="button" class="bh-chip" data-bh-question="What can I do in Finance?">Finance functions</button>
      <button type="button" class="bh-chip" data-bh-question="How much are today's sales?">Today's sales</button>
      <button type="button" class="bh-chip" data-bh-question="How does Finance connect to HRMS and POS?">Integration</button>
    </div>
    <form id="bhChatForm" class="bh-chat-inputrow">
      <textarea id="bhChatInput" class="bh-chat-input" rows="1" maxlength="500" placeholder="Ask about Finance…" aria-label="Ask the Finance assistant"></textarea>
      <button class="bh-chat-send" type="submit" aria-label="Send"><i class="bi bi-send-fill"></i></button>
    </form>
  </section>
</div>
<script src="assets/js/chatbot.js?v=20260929-finance-v5"></script>
<?php endif; ?>