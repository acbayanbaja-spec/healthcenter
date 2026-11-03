<?php
// ---- Pulse assistant settings ----
// 'local'     = built-in assistant, no account needed (answers from your own data + how-to help)
// 'anthropic' = also answers open-ended questions with Claude (needs an API key)
define('AI_PROVIDER', 'local');
define('ANTHROPIC_API_KEY', '');
define('AI_MODEL', 'claude-sonnet-5-5');
// Privacy: patient names, phone numbers and appointment details are answered on this server only.
// They are never sent to the AI provider. Only the staff member's typed question and a few
// system totals (counts) are sent.
