# JAMES GAMEROOM – Project Structure Report

## 1. Overview

**JAMES GAMEROOM** is a PHP-based casino/gaming platform with:
- **No database**: all persistence is JSON files in `/json/`.
- **Sessions**: PHP sessions for user/admin/staff auth.
- **Roles**: end users, staff (permissions), admin (full access).
- **Features**: user registration, deposits/withdrawals, game accounts, in-house games (Mines, Pick-a-Path), PayGate/card deposits, support chat, spin wheel, referral system, weekly leaderboard, profile photos.

---

## 2. Folder Structure

```
htdocs/
├── .htaccess                    # Apache config (if any)
├── config.php                   # Core: paths, auth, helpers, game/support/leaderboard logic
├── index.php                    # Landing: login/register links, redirect if logged in
├── dashboard.php                # User home: balance, spin wheel, games, leaderboard widget, withdraw modal
├── deposit.php                  # User deposit: payment methods, form, Card/PayGate logs table
├── profile.php                  # User profile: email, referral code, profile photo, account info, game accounts, activity logs
├── leaderboard.php              # Weekly leaderboard: top 100 by score/deposit/referrals, profile photos
├── games.php                    # Games list: "Our Games" (Mines, Pick-a-Path) + provider games from json/games.json
├── support.php                  # Support: ticket list, create ticket, chat UI (CSS/JS inlined for compatibility)
├── save.php                     # PayGate: receives JSON from cards_deposit.html, appends to json/paygatetx.json (id, status, server_time)
├── cards_deposit.html           # Standalone PayGate link generator: form → PayGate API → save.php; dark theme, responsive
├── paygate.html                 # Alternate PayGate form (guest)
│
├── auth/
│   ├── login.php                # User login (username/password → session, redirect dashboard or profile if no email)
│   ├── register.php             # User registration + optional referral code; sets referral_code, referred_by, referral_bonus_paid
│   └── logout.php               # User logout
│
├── admin/
│   ├── _header.php              # Admin layout: sidebar nav, pending badges, includes CSS/JS
│   ├── _footer.php              # Admin footer, confirm buttons, toasts, optional $adminExtraScript
│   ├── login.php                # Admin/staff login
│   ├── logout.php               # Admin logout
│   ├── dashboard.php           # Stats, quick actions, recent activity (deposits, PayGate, withdrawals, etc.), PayGate Approve/Reject
│   ├── users.php                # User list, balance, game accounts; AJAX update balance
│   ├── payments.php             # Tabs: deposits, withdrawals, game withdrawals; approve/reject; referral bonus on first deposit approve
│   ├── games.php                # Game account requests, game deposit/withdrawal requests; approve/reject; rollover logic
│   ├── game_catalog.php         # Edit json/games.json (name, slug, logo, link, our_game)
│   ├── game_settings.php        # Min/max main withdrawal, game rollover, deposit bonus %, mines/pickpath min/max bet
│   ├── payment_methods.php      # PayPal, Chime, PayGate config (enabled, instructions, wallet, etc.)
│   ├── activity.php             # Spin logs, activity
│   ├── support.php              # Support chat (staff view), list chats, reply
│   └── staff.php                # Staff accounts and permissions (payments, withdrawals, game_accounts, payment_methods)
│
├── api/
│   ├── me.php                   # Current user info (JSON) for frontend
│   ├── withdraw.php             # POST: withdrawal request (amount, method, account_info, optional QR); validations, write withdrawal_requests.json
│   ├── spin.php                 # POST: free or paid spin; weighted reward; update balance, spin_logs, last_spin_date, spin_streak
│   ├── mines.php                # Mines game: start/cashout/reveal; reads/writes mines_games.json, mines_active.json
│   ├── pickpath.php             # Pick-a-Path game: start/next/cashout; reads/writes pickpath_games.json
│   ├── paygate_tx_status.php    # POST: admin approve/reject PayGate tx; on approve credit user balance + referral 50% to referrer; referral_bonus_history
│   ├── paygate_create.php       # Logged-in: create PayGate payment link (wallet API → checkout URL)
│   ├── paygate_callback.php     # PayGate webhook (payment status)
│   ├── paygate_wallet.php       # Returns PayGate payout wallet address
│   ├── paygate_guest.php        # Guest PayGate flow
│   ├── paygate_start.php        # Alternative PayGate start
│   ├── cards_deposit_log.php    # (Legacy) logs to cards_deposit_log.json
│   ├── support_create.php       # Create support ticket (reason, message, image)
│   ├── support_messages.php     # Get messages for a chat
│   ├── support_send.php         # Send message (user or staff)
│   ├── support_status.php      # Support chat status
│   ├── admin_update_user.php    # Admin: update user (e.g. balance)
│   ├── realtime_poll.php        # GET: return new events from realtime_event_log.json for current user/admin (last_id)
│   └── realtime_stream.php      # SSE stream (alternative to poll)
│
├── games/
│   ├── play.php                 # Router: g=mines → mines.php, g=pickapath → pickpath.php; else _game_page.php for provider games
│   ├── _game_page.php           # Shared provider game page: request account, deposit to game, withdraw from game, reset password; rollover
│   ├── mines.php                # Mines in-house game UI + logic
│   ├── pickpath.php             # Pick-a-Path in-house game UI + logic
│   └── [orion|milkyway|firekirin|...].php   # Thin wrappers: set $gameName, include _game_page.php
│
├── assets/
│   ├── css/
│   │   ├── style.css            # Global: variables, layout, forms, cards, buttons
│   │   ├── admin.css            # Admin sidebar and panels
│   │   ├── user-dashboard.css   # User dashboard, cards, nav, tables, leaderboard, modals
│   │   ├── realtime.css         # Realtime/notification styles
│   │   ├── mines.css            # Mines game
│   │   ├── pickpath.css         # Pick-a-Path game
│   │   └── support-chat.css     # Support chat UI
│   └── js/
│       ├── main.js              # Global utilities, AJAX forms
│       ├── toasts.js            # Toast notifications (success, error, confirm)
│       ├── realtime.js          # Realtime client (WebSocket or fallback poll)
│       ├── dashboard-realtime.js # Dashboard balance/notification updates
│       ├── spin-wheel.js        # Spin wheel UI and API call
│       ├── mines.js             # Mines game frontend
│       ├── pickpath.js          # Pick-a-Path game frontend
│       └── support-chat.js      # Support chat polling and send
│
├── json/                        # All persistent data (with automatic .backup.* on write)
│   ├── users.json               # Users: id, username, email, password_hash, balance, game_accounts, deposit_history, referral_code, referred_by, referral_bonus_paid, referral_bonus_history, profile_photo, spin fields
│   ├── payments.json            # Deposit requests: user_id, amount, method, status, date
│   ├── withdrawal_requests.json # Main balance withdrawal requests
│   ├── game_requests.json       # Game deposit requests (user → game)
│   ├── game_withdrawals.json    # Game withdrawal requests (game → main)
│   ├── game_account_requests.json # Requests for new game username/password
│   ├── password_reset_requests.json # Game password reset requests
│   ├── payment_methods.json     # paypal, chime, paygate config
│   ├── games.json               # Game catalog: name, slug, logo, link, our_game
│   ├── game_settings.json       # Min/max withdrawal, rollover, bonus %, bet limits
│   ├── staff.json               # Staff users and permissions
│   ├── paygatetx.json           # PayGate/card deposits: email, amount, tracking_id, link, server_time, id, status
│   ├── spin_logs.json           # Spin wheel history
│   ├── mines_games.json         # Mines game records
│   ├── mines_active.json       # Active mines game per user
│   ├── pickpath_games.json      # Pick-a-Path game records
│   ├── support_chats.json       # Support tickets and messages
│   ├── realtime_queue.json      # Queue for WebSocket server (if used)
│   └── realtime_event_log.json  # Events for polling (balance, support, notifications)
│
├── uploads/
│   ├── profile_photos/          # User profile photos (leaderboard + profile)
│   ├── user_qr_codes/           # Withdrawal QR uploads
│   ├── support/                 # Support ticket images
│   └── qr_codes/                # Admin QR images (e.g. Chime)
│
├── gameslogo/                   # Game logos (referenced in games.json)
│
└── realtime-server/
    └── server.js                # Node.js WebSocket server: reads realtime_queue.json, broadcasts to clients by user_id/role
```

---

## 3. Major Modules

### 3.1 config.php (core)
- **BASE_PATH**: derived from SCRIPT_NAME for subfolder deployment.
- **Constants**: all JSON file paths (USERS_FILE, PAYMENTS_FILE, etc.), ADMIN_USERNAME, ADMIN_PASSWORD.
- **Auth**: `readJSON`/`writeJSON` (with backup), `isLoggedIn`, `isAdmin`, `isStaff`, `getCurrentStaff`, `canAccess(area)`, `requireStaffOrAdmin`, `requireLogin`, `requireAdmin`, `getCurrentUser`, `generateId`.
- **Referral**: `generateReferralCode`, `findUserByReferralCode`, `ensureUserReferralCode`.
- **Leaderboard**: `getWeeklyLeaderboardStart` (7 days), `getWeeklyDepositTotal`, `getReferralCount`, `getWeeklyLeaderboard`, `getWeeklyLeaderboardData`, `getProfilePhotoUrl`, `LEADERBOARD_REFERRAL_POINTS` (50).
- **Spin**: `getSpinRewardsConfig`, `getSpinWheelWeights`, `getWeightedSpinIndex`, `canUserSpinToday`, `getUserSpinStreak`.
- **Games**: `getDefaultGamesConfig`, `getGamesConfig`, `saveGamesConfig`, `getGameBySlug`, `getGameLink`, `getGameLogo`, `getGameSettings`, `saveGameSettings`, `getUserGameRolloverInfo`.
- **Support**: `getSupportReasons`, `getSupportChats`, `getSupportChatById`, `getSupportChatsByUserId`, `createSupportChat`, `addSupportMessage`, close/reopen/seen helpers, display names.

### 3.2 User flow
- **index.php** → login/register → **auth/login.php** or **auth/register.php** → **dashboard.php** (or profile if no email).
- **dashboard.php**: balance card, spin wheel (free 1/day or $5), games grid (our games + provider games), leaderboard top 3, withdraw modal (AJAX **api/withdraw.php**).
- **deposit.php**: payment method select (PayPal, Chime, PayGate); PayGate option links to **cards_deposit.html** or paygate_create; regular deposit POSTs to self and writes **payments.json**; table of Card/PayGate logs (tracking, date, status, amount, link).
- **profile.php**: email update, profile photo upload (to uploads/profile_photos), referral code + copy, account info, game accounts, activity logs (deposits, withdrawals, game, referral bonus).
- **leaderboard.php**: top 100, sort by score / weekly deposit / referrals; profile photo, username, score, weekly deposit, referrals.

### 3.3 Games
- **games.php**: reads **games.json**; splits “our_game” (Mines, Pick-a-Path) and provider games; links to `/games/play.php?g=slug` or mines/pickpath directly.
- **games/play.php**: if slug mines/pickapath → redirect to mines.php/pickpath.php; else load game from catalog and **games/_game_page.php**.
- **_game_page.php**: request account (game_account_requests), deposit to game (game_requests), withdraw from game (rollover rules, game_withdrawals), reset password; uses **getUserGameRolloverInfo**.
- **Mines / Pick-a-Path**: own PHP pages and **api/mines.php**, **api/pickpath.php**; state in mines_games, mines_active, pickpath_games.

### 3.4 Payments and PayGate
- **Deposits**: User submits on deposit.php → **payments.json** (pending). Admin approves in **admin/payments.php** → user balance += amount; if user has `referred_by` and !`referral_bonus_paid`, referrer gets 50% and `referral_bonus_history` updated.
- **PayGate**: **cards_deposit.html** (or paygate flow) POSTs to **save.php** → **paygatetx.json** (id, status pending). Admin approves in dashboard or **api/paygate_tx_status.php** → user balance += amount; same referral 50% and history. **api/paygate_tx_status.php** is POST id + action (approve/reject).
- **Withdrawals**: User submits via **api/withdraw.php** → **withdrawal_requests.json**; admin approves in **admin/payments.php** (balance check, then approved).

### 3.5 Admin
- **admin/_header.php**: sidebar with Dashboard, Users, Activity, Support, Payments, Game Accounts, Payment Methods, Game Catalog, Game Settings, Staff; pending badges; uses **canAccess(area)** for staff.
- **admin/payments.php**: deposit approve/reject (with referral bonus), withdrawal approve/reject, game withdrawal approve; **admin/games.php**: game deposit/account requests approve, rollover and balance updates.
- **admin/dashboard.php**: PayGate/Card deposits with Approve/Reject buttons (calls **api/paygate_tx_status.php** via fetch).

### 3.6 Support
- **support.php**: user creates ticket (reason, message, image) → **api/support_create.php** → **support_chats.json**; chat UI polls **api/support_messages.php** and sends via **api/support_send.php**.
- **admin/support.php**: list chats, reply; support_chats store messages array and status.

### 3.7 Realtime
- **realtime_queue.json**: PHP pushes events (e.g. user_balance_updated, support_message); **realtime-server/server.js** (Node + ws) polls and broadcasts to WebSocket clients.
- **api/realtime_poll.php**: alternative; returns events from **realtime_event_log.json** for current user/admin (last_id). Frontend uses **realtime.js** (WebSocket or poll fallback) and **dashboard-realtime.js** for balance/notifications.

---

## 4. Data Flow Summary

| Feature           | User action           | Data written                    | Admin/API follow-up                    |
|------------------|------------------------|----------------------------------|----------------------------------------|
| Register         | Register with referral | users.json (referred_by, etc.)  | —                                       |
| Deposit          | Submit deposit         | payments.json                   | admin/payments approve → balance + ref |
| PayGate deposit  | cards_deposit.html     | save.php → paygatetx.json       | paygate_tx_status approve → balance+ref|
| Withdraw         | Modal submit           | api/withdraw → withdrawal_requests | admin/payments approve/reject        |
| Game account     | Request account        | game_account_requests.json     | admin/games approve → user.game_accounts |
| Game deposit     | Deposit to game        | game_requests.json              | admin/games approve (rollover)        |
| Game withdraw    | Withdraw from game     | game_withdrawals.json           | admin/games approve                    |
| Spin             | Free or $5 spin        | api/spin → balance, spin_logs   | —                                       |
| Support          | Create/send            | support_chats.json              | admin/support reply                    |
| Profile photo    | Upload                 | uploads/profile_photos, user   | —                                       |
| Leaderboard      | —                      | Read-only from users, payments, paygatetx | —                              |

---

## 5. Interconnections

- **config.php** is required by almost every PHP page and API; it defines paths and all shared helpers.
- **admin/*.php** require `../config.php` and `requireStaffOrAdmin()`; use _header/_footer.
- **auth/*.php** require `../config.php`; login/register use USERS_FILE.
- **api/*.php** require `__DIR__ . '/../config.php'`; return JSON; use requireLogin() or requireStaffOrAdmin() where needed.
- **games/play.php** and **_game_page.php** require `__DIR__ . '/../config.php'`; game pages include shared _game_page for provider games.
- **Frontend**: dashboard, profile, support, deposit, leaderboard use **assets/css/style.css**, **user-dashboard.css**, **realtime.css**; **main.js**, **toasts.js**; support inlines support-chat.css/js to avoid 403 on some hosts.
- **Navigation**: User nav links (dashboard, deposit, games, leaderboard, profile, support) appear on dashboard, deposit, games, leaderboard, profile, support.

---

## 6. Security Notes

- Passwords hashed with `password_hash(..., PASSWORD_DEFAULT)`.
- Admin password in config (default `password`); staff in staff.json with permissions.
- No SQL; all storage is JSON (no SQL injection; file write and path handling should be validated).
- Profile photo and support uploads: type/size checks; filenames use user id or safe names.
- API endpoints check session and roles; paygate_tx_status and payments approve only for staff/admin with payments access.

---

*End of Project Structure Report*
